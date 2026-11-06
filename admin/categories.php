<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_permission('categories.manage');

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
    $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug !== '' ? $slug : $name), '-');
    if ($name === '' || strlen($name) > 80) $errors[] = 'Enter a section name of up to 80 characters.';
    if ($description !== '' && strlen($description) > 255) $errors[] = 'Keep the section description under 255 characters.';
    if ($slug === '' || strlen($slug) > 100) $errors[] = 'Enter a valid section address.';
    if ($errors === []) {
        try {
            $insert = db()->prepare('INSERT INTO categories (name, slug, description) VALUES (:name, :slug, :description)');
            $insert->execute(['name' => $name, 'slug' => $slug, 'description' => $description]);
            record_audit((int) current_user()['id'], 'category.created', 'category', (int) db()->lastInsertId());
            redirect('categories.php');
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '23000') throw $exception;
            $errors[] = 'That section address is already in use.';
        }
    }
}
$categories = db()->query(
    'SELECT c.id, c.name, c.slug, c.description, COUNT(a.id) AS story_count
     FROM categories c LEFT JOIN articles a ON a.category_id = c.id
     GROUP BY c.id ORDER BY c.sort_order, c.name'
)->fetchAll();
$adminPageTitle = 'Sections';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">HOW READERS EXPLORE</p><h1>Sections</h1><p>Keep the paper organized around the topics readers care about.</p></section>
<?php foreach ($errors as $error): ?><p class="form-error"><?= h($error) ?></p><?php endforeach; ?>
<section class="admin-section"><h2>Add a section</h2><form class="editor-form compact-form" method="post"><input type="hidden" name="csrf_token" value="<?= h(csrf_token()) ?>">
    <div class="form-columns"><label>Section name<input name="name" maxlength="80" required></label><label>Section address <span class="field-note">Optional; generated from the name.</span><input name="slug" maxlength="100" placeholder="local-culture"></label></div>
    <label>Short description <textarea name="description" maxlength="255" rows="2"></textarea></label><button class="button button-dark" type="submit">Add section</button>
</form></section>
<section class="admin-section"><div class="section-heading"><h2>Reader sections</h2><span><?= count($categories) ?> SECTIONS</span></div>
<div class="table-wrap"><table class="data-table"><thead><tr><th>Section</th><th>Address</th><th>Description</th><th>Stories</th></tr></thead><tbody>
<?php foreach ($categories as $category): ?><tr><td><strong><?= h($category['name']) ?></strong></td><td><?= h($category['slug']) ?></td><td><?= h($category['description']) ?></td><td><?= (int) $category['story_count'] ?></td></tr><?php endforeach; ?>
</tbody></table></div></section>
<?php require __DIR__ . '/_footer.php'; ?>
