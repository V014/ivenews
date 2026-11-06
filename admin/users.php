<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_permission('users.manage');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $role = (string) ($_POST['role'] ?? '');
    if ($name === '' || strlen($name) > 120) $errors[] = 'Enter a name of up to 120 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 12) $errors[] = 'Use a password with at least 12 characters.';
    if (!in_array($role, ['editor', 'author'], true)) $errors[] = 'Choose an editor or author role.';
    if ($errors === []) {
        $roleQuery = db()->prepare('SELECT id FROM roles WHERE name = :name');
        $roleQuery->execute(['name' => $role]);
        $roleId = $roleQuery->fetchColumn();
        try {
            $insert = db()->prepare('INSERT INTO users (role_id, name, email, password_hash) VALUES (:role, :name, :email, :password)');
            $insert->execute(['role' => $roleId, 'name' => $name, 'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT)]);
            record_audit((int) current_user()['id'], 'user.created', 'user', (int) db()->lastInsertId(), ['role' => $role]);
            redirect('users.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') throw $exception;
            $errors[] = 'That email address already belongs to an account.';
        }
    }
}
$users = db()->query('SELECT u.id, u.name, u.email, u.is_active, u.created_at, r.label AS role_label, r.name AS role_name FROM users u JOIN roles r ON r.id = u.role_id ORDER BY u.created_at')->fetchAll();
$adminPageTitle = 'Staff accounts';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">PEOPLE BEHIND THE PAPER</p><h1>Staff accounts</h1><p>Give each newsroom contributor the access their role needs.</p></section>
<?php foreach ($errors as $error): ?><p class="form-error"><?= h($error) ?></p><?php endforeach; ?>
<section class="admin-section"><h2>Add a newsroom colleague</h2><form class="editor-form compact-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-columns"><label>Name<input name="name" maxlength="120" required></label><label>Email<input name="email" type="email" maxlength="190" required></label></div>
    <div class="form-columns"><label>Temporary password <span class="field-note">Share securely; ask them to change it.</span><input name="password" type="password" minlength="12" required></label><label>Role<select name="role"><option value="author">Author — drafts and own stories</option><option value="editor">Editor — reviews and publishes</option></select></label></div>
    <button class="button button-dark" type="submit">Create account</button></form></section>
<section class="admin-section"><div class="section-heading"><h2>Newsroom directory</h2><span><?= count($users) ?> ACCOUNTS</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th></tr></thead><tbody>
<?php foreach ($users as $member): ?><tr><td><strong><?= h($member['name']) ?></strong></td><td><?= h($member['email']) ?></td><td><?= h($member['role_label']) ?></td><td><?= $member['is_active'] ? 'Active' : 'Disabled' ?></td><td><?= h(date('M j, Y', strtotime($member['created_at']))) ?></td></tr><?php endforeach; ?>
</tbody></table></div><p class="field-note">To disable an account, set is_active to 0 in the users table. Administrators are intentionally not assignable from this form.</p></section>
<?php require __DIR__ . '/_footer.php'; ?>
