<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
if (current_user() === null) {
    redirect('login.php');
}

$user = current_user();
$countsQuery = has_permission('articles.manage')
    ? db()->query('SELECT status, COUNT(*) AS total FROM articles GROUP BY status')
    : (function () use ($user): PDOStatement {
        $statement = db()->prepare('SELECT status, COUNT(*) AS total FROM articles WHERE author_id = :author_id GROUP BY status');
        $statement->execute(['author_id' => $user['id']]);
        return $statement;
    })();
$counts = $countsQuery->fetchAll(PDO::FETCH_KEY_PAIR);
$latest = has_permission('articles.manage')
    ? db()->query('SELECT a.id, a.title, a.slug, a.status, a.updated_at, u.name AS author_name FROM articles a JOIN users u ON u.id = a.author_id ORDER BY a.updated_at DESC LIMIT 8')->fetchAll()
    : (function () use ($user): array {
        $statement = db()->prepare('SELECT id, title, slug, status, updated_at, :author_name AS author_name FROM articles WHERE author_id = :author_id ORDER BY updated_at DESC LIMIT 8');
        $statement->execute(['author_name' => $user['name'], 'author_id' => $user['id']]);
        return $statement->fetchAll();
    })();
$adminPageTitle = 'Newsroom overview';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">YOUR NEWSROOM</p><h1>Good work starts here.</h1><p>Welcome back, <?= h($user['name']) ?>. Here’s the latest from your desk.</p></section>
<div class="stat-grid">
    <div class="stat-card"><span>Published</span><strong><?= (int) ($counts['published'] ?? 0) ?></strong></div>
    <div class="stat-card"><span>Drafts</span><strong><?= (int) ($counts['draft'] ?? 0) ?></strong></div>
    <div class="stat-card"><span>Archived</span><strong><?= (int) ($counts['archived'] ?? 0) ?></strong></div>
</div>
<section class="admin-section"><div class="section-heading"><div><p class="eyebrow">RECENTLY TOUCHED</p><h2>Your latest stories</h2></div><?php if (has_permission('articles.create')): ?><a class="button button-dark" href="article-edit.php">＋ New story</a><?php endif; ?></div>
    <?php if ($latest === []): ?><div class="empty-state"><h3>Your desk is clear</h3><p>Start a story and give readers something worth thinking about.</p></div><?php else: ?>
    <div class="table-wrap"><table class="data-table"><thead><tr><th>Story</th><th>Author</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
    <?php foreach ($latest as $item): ?><tr><td><strong><?= h($item['title']) ?></strong></td><td><?= h($item['author_name']) ?></td><td><span class="status status-<?= h($item['status']) ?>"><?= h(ucfirst($item['status'])) ?></span></td><td><?= h(date('M j, Y', strtotime($item['updated_at']))) ?></td><td><a class="table-link" href="article-edit.php?id=<?= (int) $item['id'] ?>">Edit ↗</a></td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php require __DIR__ . '/_footer.php'; ?>
