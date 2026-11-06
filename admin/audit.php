<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
require_permission('audit.view');
$logs = db()->query(
    'SELECT l.action, l.entity_type, l.entity_id, l.details, l.ip_address, l.created_at, u.name AS user_name
     FROM audit_logs l LEFT JOIN users u ON u.id = l.user_id ORDER BY l.created_at DESC LIMIT 100'
)->fetchAll();
$adminPageTitle = 'Audit history';
require __DIR__ . '/_header.php';
?>
<section class="admin-heading"><p class="eyebrow">ACCOUNTABILITY</p><h1>Audit history</h1><p>The latest 100 editorial and account changes.</p></section>
<div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Who</th><th>Action</th><th>Record</th><th>Details</th></tr></thead><tbody>
<?php foreach ($logs as $log): ?><tr><td><?= h(date('M j, Y H:i', strtotime($log['created_at']))) ?></td><td><?= h($log['user_name'] ?? 'Former account') ?></td><td><?= h($log['action']) ?></td><td><?= h($log['entity_type']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td><td><?= h($log['details']) ?></td></tr><?php endforeach; ?>
</tbody></table></div>
<?php require __DIR__ . '/_footer.php'; ?>
