<?php
$adminPageTitle = $adminPageTitle ?? 'Newsroom';
$signedInUser = current_user();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($adminPageTitle) ?> · Newsroom · I’ve News</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body class="admin-body">
<header class="admin-topbar"><a class="wordmark" href="../index.php">I’VE <span>NEWS</span></a><span>NEWSROOM / <?= h(strtoupper($signedInUser['role_label'])) ?></span><form action="logout.php" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>"><button class="text-button" type="submit">Sign out ↗</button></form></header>
<div class="admin-layout">
    <aside class="admin-sidebar">
        <p class="eyebrow">YOUR DESK</p>
        <a href="index.php">Overview</a>
        <?php if (has_permission('articles.create')): ?><a href="articles.php">Stories</a><a href="article-edit.php">Write a story ＋</a><?php endif; ?>
        <?php if (has_permission('categories.manage')): ?><a href="categories.php">Sections</a><?php endif; ?>
        <?php if (has_permission('users.manage')): ?><a href="users.php">Staff accounts</a><?php endif; ?>
        <?php if (has_permission('audit.view')): ?><a href="audit.php">Audit history</a><?php endif; ?>
        <a href="../index.php">← View publication</a>
    </aside>
    <main class="admin-main">
