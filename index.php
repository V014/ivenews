<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$topicSlug = trim((string) ($_GET['topic'] ?? ''));
$articleSlug = trim((string) ($_GET['article'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1);
$perPage = 9;
$category = null;
$article = null;
$categories = db()->query('SELECT id, name, slug, description FROM categories ORDER BY sort_order, name')->fetchAll();

if ($topicSlug !== '') {
    $statement = db()->prepare('SELECT id, name, slug, description FROM categories WHERE slug = :slug');
    $statement->execute(['slug' => $topicSlug]);
    $category = $statement->fetch() ?: null;
    if ($category === null) {
        http_response_code(404);
    }
}

if ($articleSlug !== '') {
    $statement = db()->prepare(
        'SELECT a.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
         FROM articles a
         JOIN categories c ON c.id = a.category_id
         JOIN users u ON u.id = a.author_id
         WHERE a.slug = :slug AND a.status = "published" AND a.published_at <= NOW()'
    );
    $statement->execute(['slug' => $articleSlug]);
    $article = $statement->fetch() ?: null;
    if ($article === null) {
        http_response_code(404);
    }
}

$where = ['a.status = "published"', 'a.published_at <= NOW()'];
$params = [];
if ($category !== null) {
    $where[] = 'a.category_id = :category_id';
    $params['category_id'] = $category['id'];
}
if ($search !== '') {
    $where[] = '(a.title LIKE :search_title OR a.excerpt LIKE :search_excerpt OR a.body LIKE :search_body)';
    $needle = '%' . $search . '%';
    $params['search_title'] = $needle;
    $params['search_excerpt'] = $needle;
    $params['search_body'] = $needle;
}
$countQuery = 'SELECT COUNT(*) FROM articles a WHERE ' . implode(' AND ', $where);
$countStatement = db()->prepare($countQuery);
$countStatement->execute($params);
$totalArticles = (int) $countStatement->fetchColumn();
$totalPages = max(1, (int) ceil($totalArticles / $perPage));
$page = min($page, $totalPages);
$query = 'SELECT a.id, a.title, a.slug, a.excerpt, a.body, a.image_path, a.image_alt, a.is_featured,
                 a.published_at, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
          FROM articles a JOIN categories c ON c.id = a.category_id JOIN users u ON u.id = a.author_id
          WHERE ' . implode(' AND ', $where) . ' ORDER BY a.is_featured DESC, a.published_at DESC
          LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage);
$statement = db()->prepare($query);
$statement->execute($params);
$articles = $statement->fetchAll();
$featured = array_values(array_filter($articles, static fn(array $item): bool => (bool) $item['is_featured']));

$pageTitle = $article !== null
    ? $article['title']
    : ($category !== null ? $category['name'] . ' — Stories and analysis' : ($search !== '' ? 'Search results' : 'Independent stories. Carefully read.'));
$pageDescription = $article !== null
    ? $article['excerpt']
    : ($category['description'] ?? 'Thoughtful reporting on business, technology, health and culture in Malawi and beyond.');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= h($pageDescription) ?>">
    <title><?= h($pageTitle) ?> · I’ve News</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to stories</a>
<header class="masthead">
    <div class="masthead-meta"><span>THE MALAWI EDITION</span><span><?= date('l, F j, Y') ?></span><button class="theme-toggle" type="button" aria-label="Toggle dark mode">◐ <span>Reading mode</span></button></div>
    <a class="wordmark" href="index.php" aria-label="I've News home">I’VE <span>NEWS</span></a>
    <p class="masthead-tagline">Curious minds. Clear perspectives.</p>
</header>
<nav class="site-nav" aria-label="Main navigation">
    <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="topic-nav">☰ <span>Sections</span></button>
    <div class="nav-inner" id="topic-nav">
        <a class="<?= $category === null && $article === null ? 'active' : '' ?>" href="index.php">Front page</a>
        <?php foreach ($categories as $navCategory): ?>
            <a class="<?= $category !== null && $category['id'] === $navCategory['id'] ? 'active' : '' ?>" href="<?= h(url('index.php', ['topic' => $navCategory['slug']])) ?>"><?= h($navCategory['name']) ?></a>
        <?php endforeach; ?>
        <a href="admin/login.php">Newsroom</a>
        <form class="search-form" action="index.php" method="get" role="search">
            <label class="sr-only" for="site-search">Search stories</label>
            <input id="site-search" type="search" name="q" placeholder="Search the paper" value="<?= h($search) ?>">
            <button type="submit" aria-label="Search">⌕</button>
        </form>
    </div>
</nav>
<main id="main" class="page-shell">
<?php if ($article !== null): ?>
    <article class="story-page">
        <a class="back-link" href="<?= h(url('index.php', ['topic' => $article['category_slug']])) ?>">← <?= h($article['category_name']) ?></a>
        <header class="story-heading">
            <p class="eyebrow"><?= h($article['category_name']) ?> <span>·</span> <?= h(date('F j, Y', strtotime($article['published_at']))) ?></p>
            <h1><?= h($article['title']) ?></h1>
            <p class="story-deck"><?= h($article['excerpt']) ?></p>
            <div class="byline"><span>By <strong><?= h($article['author_name']) ?></strong></span><span><?= reading_minutes($article['body']) ?> min read</span></div>
        </header>
        <?php if ($article['image_path'] !== null && $article['image_path'] !== ''): ?>
            <figure class="story-hero-image"><img src="<?= h($article['image_path']) ?>" alt="<?= h($article['image_alt']) ?>"><figcaption><?= h($article['image_alt']) ?></figcaption></figure>
        <?php endif; ?>
        <div class="reading-progress" aria-hidden="true"><span></span></div>
        <div class="story-body"><div class="story-tools"><button class="share-button" type="button">Share this story ↗</button><span><?= reading_minutes($article['body']) ?> MIN READ</span></div><div class="story-copy"><?= nl2br(h($article['body'])) ?></div>
            <?php if ($article['source_url'] !== null && filter_var($article['source_url'], FILTER_VALIDATE_URL)): ?>
                <p class="source-note">Further reading: <a href="<?= h($article['source_url']) ?>" target="_blank" rel="noopener noreferrer">Read the source ↗</a></p>
            <?php endif; ?>
        </div>
    </article>
<?php else: ?>
    <section class="section-intro">
        <p class="eyebrow"><?= $category !== null ? 'THE ' . h(strtoupper($category['name'])) . ' DESK' : ($search !== '' ? 'SEARCH THE ARCHIVE' : 'INDEPENDENT JOURNALISM · ISSUE NO. 01') ?></p>
        <h1><?= $category !== null ? h($category['name']) : ($search !== '' ? 'Search results' : 'A little more <em>perspective.</em>') ?></h1>
        <p><?= $category !== null ? h($category['description']) : ($search !== '' ? 'Stories matching “' . h($search) . '”.' : 'The stories behind the headlines, told with context and care.') ?></p>
    </section>
    <?php if ($category === null && $search === '' && $page === 1 && isset($articles[0])): $lead = $articles[0]; ?>
        <section class="lead-story" aria-label="Lead story">
            <a class="lead-image" href="<?= h(url('index.php', ['article' => $lead['slug']])) ?>">
                <?php if ($lead['image_path']): ?><img src="<?= h($lead['image_path']) ?>" alt="<?= h($lead['image_alt']) ?>"><?php else: ?><span class="image-placeholder">THE DAILY EDITION</span><?php endif; ?>
                <span class="image-caption">THE DAILY EDITION · <?= h(strtoupper($lead['category_name'])) ?></span>
            </a>
            <div class="lead-copy">
                <p class="eyebrow"><?= h($lead['category_name']) ?> <span>·</span> <?= h(date('F j, Y', strtotime($lead['published_at']))) ?></p>
                <h2><a href="<?= h(url('index.php', ['article' => $lead['slug']])) ?>"><?= h($lead['title']) ?></a></h2>
                <p class="lead-deck"><?= h($lead['excerpt']) ?></p>
                <div class="byline"><span>By <strong><?= h($lead['author_name']) ?></strong></span><span><?= reading_minutes($lead['body']) ?> min read</span></div>
                <a class="text-link" href="<?= h(url('index.php', ['article' => $lead['slug']])) ?>">Read the full story <span>↗</span></a>
            </div>
        </section>
    <?php endif; ?>
    <?php if ($featured !== [] && $category === null && $search === '' && $page === 1): ?>
        <aside class="editors-picks">
            <div class="strip-heading"><span>THE EDITOR’S PICKS</span><span>Worth your time</span></div>
            <div class="pick-list">
                <?php foreach (array_slice($featured, 0, 3) as $pick): ?>
                    <a class="pick-item" href="<?= h(url('index.php', ['article' => $pick['slug']])) ?>"><span class="pick-category"><?= h($pick['category_name']) ?></span><strong><?= h($pick['title']) ?></strong><span class="pick-arrow">↗</span></a>
                <?php endforeach; ?>
            </div>
        </aside>
    <?php endif; ?>
    <section class="latest-section">
        <div class="section-heading"><div><p class="eyebrow"><?= $category !== null ? 'FROM THE ' . h(strtoupper($category['name'])) . ' DESK' : 'THE NEWSROOM' ?></p><h2><?= $category !== null ? 'More in ' . h($category['name']) : ($search !== '' ? 'Stories found' : 'The latest stories') ?></h2></div><span class="section-count"><?= $totalArticles ?> STORIES</span></div>
        <?php if ($articles === []): ?>
            <div class="empty-state"><span class="empty-mark">∅</span><h3>No stories just yet</h3><p>Try another section or check back soon for fresh reporting.</p><a class="text-link" href="index.php">Return to the front page ↗</a></div>
        <?php else: ?>
            <div class="story-grid">
                <?php foreach ($articles as $item): ?>
                    <article class="story-card">
                        <a class="card-image" href="<?= h(url('index.php', ['article' => $item['slug']])) ?>">
                            <?php if ($item['image_path']): ?><img src="<?= h($item['image_path']) ?>" alt="<?= h($item['image_alt']) ?>" loading="lazy"><?php else: ?><span class="image-placeholder">I’VE NEWS</span><?php endif; ?>
                        </a>
                        <div class="card-copy"><p class="eyebrow"><?= h($item['category_name']) ?> <span>·</span> <?= h(date('M j, Y', strtotime($item['published_at']))) ?></p>
                            <h3><a href="<?= h(url('index.php', ['article' => $item['slug']])) ?>"><?= h($item['title']) ?></a></h3>
                            <p><?= h($item['excerpt']) ?></p><div class="card-meta"><span><?= h($item['author_name']) ?></span><span><?= reading_minutes($item['body']) ?> MIN</span></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Story pages">
                    <?php if ($page > 1): ?><a href="<?= h(url('index.php', array_filter(['topic' => $category['slug'] ?? null, 'q' => $search ?: null, 'page' => $page - 1]))) ?>">← Newer stories</a><?php endif; ?>
                    <span>PAGE <?= $page ?> OF <?= $totalPages ?></span>
                    <?php if ($page < $totalPages): ?><a href="<?= h(url('index.php', array_filter(['topic' => $category['slug'] ?? null, 'q' => $search ?: null, 'page' => $page + 1]))) ?>">Older stories →</a><?php endif; ?>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <section class="topic-directory"><p class="eyebrow">EXPLORE THE PAPER</p><h2>Find your next read.</h2><div class="topic-links">
        <?php foreach ($categories as $topic): ?><a href="<?= h(url('index.php', ['topic' => $topic['slug']])) ?>"><span><?= h($topic['name']) ?></span><span>↗</span></a><?php endforeach; ?>
    </div></section>
<?php endif; ?>
</main>
<footer class="site-footer"><a class="wordmark" href="index.php">I’VE <span>NEWS</span></a><p>Curious minds. Clear perspectives.</p><span>© <?= date('Y') ?> I’ve News</span></footer>
<script src="js/site.js" defer></script>
</body>
</html>
