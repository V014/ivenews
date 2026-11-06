<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_permission('articles.create');

$user = current_user();
$canManage = has_permission('articles.manage');
$canPublish = has_permission('articles.publish');
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$article = null;
if ($id > 0) {
    $statement = db()->prepare('SELECT * FROM articles WHERE id = :id');
    $statement->execute(['id' => $id]);
    $article = $statement->fetch() ?: null;
    if ($article === null) {
        http_response_code(404);
        exit('Story not found.');
    }
    if (!$canManage && ((int) $article['author_id'] !== (int) $user['id'] || $article['status'] !== 'draft')) {
        http_response_code(403);
        exit('You may only edit your own unpublished stories.');
    }
}
$categories = db()->query('SELECT id, name FROM categories ORDER BY sort_order, name')->fetchAll();
$errors = [];
$values = $article ?? [
    'title' => '', 'slug' => '', 'excerpt' => '', 'body' => '', 'category_id' => '',
    'image_path' => '', 'image_alt' => '', 'source_url' => '', 'status' => 'draft', 'is_featured' => 0,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $values = array_merge($values, [
        'title' => trim((string) ($_POST['title'] ?? '')),
        'slug' => trim((string) ($_POST['slug'] ?? '')),
        'excerpt' => trim((string) ($_POST['excerpt'] ?? '')),
        'body' => trim((string) ($_POST['body'] ?? '')),
        'category_id' => (int) ($_POST['category_id'] ?? 0),
        'image_path' => trim((string) ($_POST['image_path'] ?? '')),
        'image_alt' => trim((string) ($_POST['image_alt'] ?? '')),
        'source_url' => trim((string) ($_POST['source_url'] ?? '')),
        'status' => (string) ($_POST['status'] ?? 'draft'),
        'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
    ]);
    $slug = strtolower(trim($values['slug'] !== '' ? $values['slug'] : $values['title']));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');
    $values['slug'] = $slug;
    if ($values['title'] === '' || strlen($values['title']) > 220) $errors[] = 'Enter a headline of up to 220 characters.';
    if ($slug === '' || strlen($slug) > 240) $errors[] = 'Enter a headline that can make a valid story address.';
    if ($values['excerpt'] === '' || strlen($values['excerpt']) > 500) $errors[] = 'Write a short summary of up to 500 characters.';
    if ($values['body'] === '') $errors[] = 'Add the full story before saving.';
    $categoryCheck = db()->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
    $categoryCheck->execute(['id' => $values['category_id']]);
    if ((int) $categoryCheck->fetchColumn() === 0) $errors[] = 'Choose a valid section.';
    if ($values['image_path'] !== '' && (str_contains($values['image_path'], '..') || !preg_match('~^img/[A-Za-z0-9_./-]+\.(jpg|jpeg|png|webp)$~i', $values['image_path']))) {
        $errors[] = 'Use an image path inside img/ (JPG, PNG or WebP).';
    }
    if ($values['source_url'] !== '' && !filter_var($values['source_url'], FILTER_VALIDATE_URL)) $errors[] = 'Enter a complete source URL or leave it blank.';
    if (!in_array($values['status'], ['draft', 'published', 'archived'], true)) $errors[] = 'Choose a valid story status.';
    if (!$canPublish && $values['status'] !== 'draft') {
        $errors[] = 'Only editors and administrators can publish or archive stories.';
    }
    if (!$canManage) $values['is_featured'] = 0;
    $duplicate = db()->prepare('SELECT id FROM articles WHERE slug = :slug AND id != :id');
    $duplicate->execute(['slug' => $slug, 'id' => $id]);
    if ($duplicate->fetch()) $errors[] = 'That story address is already in use.';

    if ($errors === []) {
        $publishedAt = $values['status'] === 'published'
            ? (($article !== null && $article['published_at'] !== null) ? $article['published_at'] : date('Y-m-d H:i:s'))
            : null;
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($article === null) {
                $save = $pdo->prepare(
                    'INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, image_path, image_alt, source_url, status, is_featured, published_at)
                     VALUES (:category_id, :author_id, :title, :slug, :excerpt, :body, :image_path, :image_alt, :source_url, :status, :featured, :published_at)'
                );
                $save->execute([
                    'category_id' => $values['category_id'], 'author_id' => $user['id'], 'title' => $values['title'],
                    'slug' => $slug, 'excerpt' => $values['excerpt'], 'body' => $values['body'],
                    'image_path' => $values['image_path'] ?: null, 'image_alt' => $values['image_alt'],
                    'source_url' => $values['source_url'] ?: null, 'status' => $values['status'],
                    'featured' => $values['is_featured'], 'published_at' => $publishedAt,
                ]);
                $id = (int) $pdo->lastInsertId();
                record_audit((int) $user['id'], 'article.created', 'article', $id, ['status' => $values['status']]);
            } else {
                $revision = $pdo->prepare(
                    'INSERT INTO article_revisions (article_id, editor_id, title, excerpt, body, status)
                     VALUES (:article_id, :editor_id, :title, :excerpt, :body, :status)'
                );
                $revision->execute([
                    'article_id' => $id, 'editor_id' => $user['id'], 'title' => $article['title'],
                    'excerpt' => $article['excerpt'], 'body' => $article['body'], 'status' => $article['status'],
                ]);
                $save = $pdo->prepare(
                    'UPDATE articles SET category_id = :category_id, title = :title, slug = :slug, excerpt = :excerpt,
                     body = :body, image_path = :image_path, image_alt = :image_alt, source_url = :source_url,
                     status = :status, is_featured = :featured, published_at = :published_at WHERE id = :id'
                );
                $save->execute([
                    'category_id' => $values['category_id'], 'title' => $values['title'], 'slug' => $slug,
                    'excerpt' => $values['excerpt'], 'body' => $values['body'],
                    'image_path' => $values['image_path'] ?: null, 'image_alt' => $values['image_alt'],
                    'source_url' => $values['source_url'] ?: null, 'status' => $values['status'],
                    'featured' => $values['is_featured'], 'published_at' => $publishedAt, 'id' => $id,
                ]);
                record_audit((int) $user['id'], 'article.updated', 'article', $id, ['status' => $values['status']]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            $pdo->rollBack();
            throw $exception;
        }
        redirect('articles.php');
    }
}
$adminPageTitle = $article === null ? 'Write a story' : 'Edit story';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">THE STORY DESK</p><h1><?= $article === null ? 'Start with a good headline.' : 'Make it even better.' ?></h1><p>Tell the full story. You can save a draft and come back any time.</p></section>
<?php foreach ($errors as $error): ?><p class="form-error"><?= h($error) ?></p><?php endforeach; ?>
<form class="editor-form" method="post">
    <input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-columns"><label>Headline<input name="title" maxlength="220" required value="<?= h($values['title']) ?>"></label><label>Story address <span class="field-note">Leave blank to make it from the headline.</span><input name="slug" maxlength="240" value="<?= h($article === null && $_SERVER['REQUEST_METHOD'] !== 'POST' ? '' : $values['slug']) ?>" placeholder="a-clear-story-address"></label></div>
    <label>Short summary <span class="field-note">Shown on the front page (500 characters max).</span><textarea name="excerpt" maxlength="500" rows="3" required><?= h($values['excerpt']) ?></textarea></label>
    <label>Full story <span class="field-note">Plain text is safest. Paragraph breaks are preserved.</span><textarea name="body" rows="16" required><?= h($values['body']) ?></textarea></label>
    <div class="form-columns"><label>Section<select name="category_id" required><option value="">Choose a section</option><?php foreach ($categories as $cat): ?><option value="<?= (int) $cat['id'] ?>" <?= (int) $values['category_id'] === (int) $cat['id'] ? 'selected' : '' ?>><?= h($cat['name']) ?></option><?php endforeach; ?></select></label><label>Image path <span class="field-note">Place a JPG, PNG or WebP inside img/.</span><input name="image_path" value="<?= h($values['image_path']) ?>" placeholder="img/story-photo.jpg"></label></div>
    <label>Image description <span class="field-note">Describe the image for readers using assistive technology.</span><input name="image_alt" maxlength="250" value="<?= h($values['image_alt']) ?>"></label>
    <label>Source or further reading URL <span class="field-note">Optional; opens in a new tab.</span><input name="source_url" type="url" maxlength="1000" value="<?= h($values['source_url']) ?>"></label>
    <div class="form-columns"><label>Story status<select name="status"><option value="draft" <?= $values['status'] === 'draft' ? 'selected' : '' ?>>Save as draft</option><?php if ($canPublish): ?><option value="published" <?= $values['status'] === 'published' ? 'selected' : '' ?>>Publish now</option><option value="archived" <?= $values['status'] === 'archived' ? 'selected' : '' ?>>Archive</option><?php endif; ?></select></label>
        <?php if ($canManage): ?><label class="checkbox-label"><input type="checkbox" name="is_featured" value="1" <?= $values['is_featured'] ? 'checked' : '' ?>> Feature this story in the editor’s picks</label><?php endif; ?></div>
    <div class="form-actions"><a class="back-link" href="articles.php">Cancel</a><button class="button button-dark" type="submit">Save story →</button></div>
</form>
<?php require __DIR__ . '/_footer.php'; ?>
