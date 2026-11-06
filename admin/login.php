<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';

if (current_user() !== null) {
    redirect('index.php');
}
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ipHash = hash_hmac('sha256', $ip, getenv('IVENews_APP_KEY') ?: session_name());
    $attempts = db()->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE) AND (email = :email OR ip_hash = :ip_hash)'
    );
    $attempts->execute(['email' => $email, 'ip_hash' => $ipHash]);
    if ((int) $attempts->fetchColumn() >= 10) {
        http_response_code(429);
        $errors[] = 'Too many sign-in attempts. Please wait 15 minutes before trying again.';
    } else {
        $statement = db()->prepare('SELECT id, password_hash, failed_login_attempts, locked_until FROM users WHERE email = :email AND is_active = 1');
        $statement->execute(['email' => $email]);
        $user = $statement->fetch();
        $valid = $user !== false
            && ($user['locked_until'] === null || strtotime($user['locked_until']) <= time())
            && password_verify($password, $user['password_hash']);
        db()->prepare('INSERT INTO login_attempts (email, ip_hash, succeeded) VALUES (:email, :ip, :succeeded)')
            ->execute(['email' => $email, 'ip' => $ipHash, 'succeeded' => $valid ? 1 : 0]);

        if ($valid) {
            db()->prepare('UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => $user['id']]);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            record_audit((int) $user['id'], 'auth.login', 'user', (int) $user['id']);
            redirect('index.php');
        }
        if ($user !== false) {
            $failures = (int) $user['failed_login_attempts'] + 1;
            db()->prepare(
                'UPDATE users SET failed_login_attempts = :failures, locked_until = :locked_until WHERE id = :id'
            )->execute([
                'failures' => $failures,
                'locked_until' => $failures >= 5 ? date('Y-m-d H:i:s', time() + 900) : null,
                'id' => $user['id'],
            ]);
        }
        $errors[] = 'The email or password was not recognized.';
    }
}
?>
<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sign in · I’ve News</title><link rel="stylesheet" href="../css/style.css"></head>
<body class="admin-body">
<main class="auth-card">
    <a class="wordmark" href="../index.php">I’VE <span>NEWS</span></a>
    <p class="eyebrow">THE NEWSROOM</p><h1>Good to have you back.</h1><p>Sign in to manage stories and the front page.</p>
    <?php foreach ($errors as $error): ?><p class="form-error"><?= h($error) ?></p><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Email address<input name="email" type="email" autocomplete="username" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
        <button class="button button-dark" type="submit">Sign in</button>
    </form>
    <a class="back-link" href="../index.php">← Back to the paper</a>
</main>
</body>
</html>
