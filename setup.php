<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
if ($count > 0) {
    http_response_code(403);
    exit('Initial setup is already complete. Sign in at admin/login.php.');
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    if ($name === '' || strlen($name) > 120) {
        $errors[] = 'Enter a name of up to 120 characters.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    }
    if (strlen($password) < 12) {
        $errors[] = 'Choose a password with at least 12 characters.';
    }
    if ($errors === []) {
        $adminRole = db()->query("SELECT id FROM roles WHERE name = 'admin'")->fetchColumn();
        if ($adminRole === false) {
            http_response_code(500);
            exit('Role data is missing. Import schema.sql and retry setup.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
        $statement = $pdo->prepare(
            'INSERT INTO users (role_id, name, email, password_hash) VALUES (:role_id, :name, :email, :password)'
        );
        $statement->execute([
            'role_id' => $adminRole,
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $adminId = (int) $pdo->lastInsertId();
        record_audit($adminId, 'setup.admin_created', 'user', $adminId);

        $starterStories = [
            [
                'category' => 'business', 'title' => 'The Downfall of the Kwacha',
                'slug' => 'the-downfall-of-the-kwacha',
                'excerpt' => 'A look at the forces behind the Kwacha’s long decline and what it means for everyday life.',
                'body' => 'There used to be a time when the American dollar would exchange for about 100 Kwacha in the 2000s. Now that is a long-gone daydream. Many factors have contributed to this devaluation, from economic policy to environmental pressures and recurring wars. The original article also raised questions about public accountability and how economic decisions affect ordinary people.',
                'image_path' => 'img/usd-mwk-trend.png',
                'image_alt' => 'United States Dollar to Malawi Kwacha trend since 2003',
                'source_url' => 'https://www.bbc.com/news/world-africa-67367983',
            ],
            [
                'category' => 'technology', 'title' => 'The mobile phone’s evolution',
                'slug' => 'the-mobile-phones-evolution',
                'excerpt' => 'From simple wired experiments to foldable screens, mobile technology has changed alongside us.',
                'body' => 'How it looks, feels and works has drastically evolved. From two tin cans connected with a string to a pair of goggles attached to your head, the mobile phone has come a long way and changed as much as humanity has. As we shift from slab phones with six-inch displays to foldable devices and virtual reality headsets, new interfaces continue to challenge what a phone can be.',
                'image_path' => 'img/phone_evolution.jpg',
                'image_alt' => 'A picture of the mobile phone’s evolution',
                'source_url' => '',
            ],
            [
                'category' => 'health', 'title' => 'A breakthrough cancer vaccine?',
                'slug' => 'a-breakthrough-cancer-vaccine',
                'excerpt' => 'A reported research milestone offers a chance to examine the promises and questions around cancer vaccines.',
                'body' => 'A report about a proposed cancer vaccine prompted big questions about medical research, access and the difference between early announcements and treatments that are proven to work. Any claim about a new treatment should be checked against reliable medical and scientific evidence before it is presented as a breakthrough.',
                'image_path' => 'img/cancer_vaccine_russia.jpg',
                'image_alt' => 'Illustration accompanying a story about cancer vaccine research',
                'source_url' => '',
            ],
            [
                'category' => 'health', 'title' => 'The rising cost of everyday health',
                'slug' => 'the-rising-cost-of-everyday-health',
                'excerpt' => 'The cost of basic personal-care products can add up as household budgets come under pressure.',
                'body' => 'Grooming yourself is getting even costlier as the Kwacha keeps losing value. Basic products that people need to keep themselves clean can take a significant share of a monthly budget. The original story asked how inflation and currency changes shape the price of everyday essentials.',
                'image_path' => 'img/money-toothpaste.jpg',
                'image_alt' => 'Money beside a tube of toothpaste',
                'source_url' => '',
            ],
        ];
        $categoryLookup = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug');
        $storyInsert = $pdo->prepare(
            'INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, image_path, image_alt, source_url, status)
             VALUES (:category_id, :author_id, :title, :slug, :excerpt, :body, :image_path, :image_alt, :source_url, "draft")'
        );
        foreach ($starterStories as $story) {
            $categoryLookup->execute(['slug' => $story['category']]);
            $categoryId = $categoryLookup->fetchColumn();
            if ($categoryId === false) {
                throw new RuntimeException('Starter story category is missing: ' . $story['category']);
            }
            $storyInsert->execute([
                'category_id' => $categoryId, 'author_id' => $adminId, 'title' => $story['title'],
                'slug' => $story['slug'], 'excerpt' => $story['excerpt'], 'body' => $story['body'],
                'image_path' => $story['image_path'], 'image_alt' => $story['image_alt'],
                'source_url' => $story['source_url'] ?: null,
            ]);
        }
        record_audit($adminId, 'setup.starter_drafts_created', 'article', null, ['count' => count($starterStories)]);
        $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = $adminId;
        redirect('admin/index.php');
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set up the newsroom · I've News</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="admin-body">
<main class="auth-card">
    <a class="wordmark" href="index.php">I’VE <span>NEWS</span></a>
    <p class="eyebrow">FIRST-RUN SETUP</p>
    <h1>Create your administrator account</h1>
    <p>This page disables itself as soon as the first staff account exists.</p>
    <?php foreach ($errors as $error): ?><p class="form-error"><?= h($error) ?></p><?php endforeach; ?>
    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
        <label>Your name<input name="name" autocomplete="name" maxlength="120" required value="<?= h($_POST['name'] ?? '') ?>"></label>
        <label>Email address<input name="email" type="email" autocomplete="email" maxlength="190" required value="<?= h($_POST['email'] ?? '') ?>"></label>
        <label>Password <span class="field-note">12 characters minimum</span><input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
        <button class="button button-dark" type="submit">Create administrator</button>
    </form>
</main>
</body>
</html>
