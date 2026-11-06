<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_permission('articles.create');

$canManage = has_permission('articles.manage');
$statement = $canManage
    ? db()->query('SELECT a.id, a.title, a.slug, a.status, a.is_featured, a.updated_at, c.name AS category_name, u.name AS author_name FROM articles a JOIN categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id ORDER BY a.updated_at DESC')
    : db()->prepare('SELECT a.id, a.title, a.slug, a.status, a.is_featured, a.updated_at, c.name AS category_name, u.name AS author_name FROM articles a JOIN categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id WHERE a.author_id = :author_id ORDER BY a.updated_at DESC');
if ($statement instanceof PDOStatement && !$canManage) {
    $statement->execute(['author_id' => current_user()['id']]);
}
$articles = $statement instanceof PDOStatement ? $statement->fetchAll() : $statement;
$adminPageTitle = 'Stories';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">THE ARCHIVE</p><h1>Stories</h1><p>Draft, polish and publish thoughtful reporting.</p></section>
<div class="section-heading admin-list-heading"><span><?= count($articles) ?> STORIES ON YOUR DESK</span><a class="button button-dark" href="article-edit.php">＋ Write a story</a></div>
<?php if ($articles === []): ?><div class="empty-state"><h3>No stories here yet</h3><p>Your next story can start right here.</p></div><?php else: ?>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Story</th><th>Section</th><th>Author</th><th>Status</th><th>Updated</th><th></th></tr></thead><tbody>
<?php foreach ($articles as $item): ?><tr><td><strong><?= h($item['title']) ?></strong><?= $item['is_featured'] ? '<span class="featured-badge"> PICK</span>' : '' ?></td><td><?= h($item['category_name']) ?></td><td><?= h($item['author_name']) ?></td><td><span class="status status-<?= h($item['status']) ?>"><?= h(ucfirst($item['status'])) ?></span></td><td><?= h(date('M j, Y', strtotime($item['updated_at']))) ?></td><td><a class="table-link" href="article-edit.php?id=<?= (int) $item['id'] ?>">Edit ↗</a></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?>
<?php require __DIR__ . '/_footer.php'; ?>
