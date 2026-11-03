<?php
require __DIR__ . '/lib.php';
require_login();
$args = []; $where = notice_filters($args);
$per = 20; $page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)q("SELECT COUNT(*) FROM notices n $where", $args)->fetchColumn();
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$rows = q("SELECT n.*, SUM(l.status IN ('delivered','read')) AS delivered, SUM(l.status IN ('failed','invalid')) AS failed
           FROM notices n LEFT JOIN message_logs l ON l.notice_id = n.id $where
           GROUP BY n.id ORDER BY n.id DESC LIMIT $per OFFSET " . (($page - 1) * $per), $args)->fetchAll();
$title = 'All Notices'; $active = 'notices';
include 'partials/head.php';
?>
<header class="head"><h1>All Notices</h1><a class="btn" href="compose.php">Send New Notice</a></header>
<?php filter_bar('notices.php'); ?>
<section class="panel">
  <p class="muted" style="margin-top:0"><?= $total ?> notice(s) found</p>
  <?php if ($rows): ?>
  <div class="scroll"><table>
    <tr><th>Notice</th><th>Sent To</th><th>Total</th><th>Delivered</th><th>Failed</th><th>Time</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($rows as $n): ?>
    <tr>
      <td><a href="notice.php?id=<?= (int)$n['id'] ?>"><?= e($n['title']) ?></a></td>
      <td><?= $n['target_class'] ? e(trim($n['target_class'] . ' ' . $n['target_batch'])) : 'All Students' ?></td>
      <td><?= (int)$n['total'] ?></td><td><?= (int)$n['delivered'] ?></td><td><?= (int)$n['failed'] ?></td>
      <td><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></td>
      <td><span class="tag <?= e($n['status']) ?>"><?= $n['status'] === 'done' ? 'Completed' : 'Sending...' ?></span></td>
      <td class="actions">
        <a class="btn-action-view" href="notice.php?id=<?= (int)$n['id'] ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
          </svg>
          <span>View</span>
        </a>
        <a class="btn-action-edit" href="notice_edit.php?id=<?= (int)$n['id'] ?>">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
          </svg>
          <span>Edit</span>
        </a>
        <form method="post" action="notice_delete.php" onsubmit="return confirm('Delete this notice and its delivery records? Messages already sent on WhatsApp cannot be recalled.')">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <input type="hidden" name="back" value="notices.php">
          <button class="btn-action-delete">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <polyline points="3 6 5 6 21 6"></polyline>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
            </svg>
            <span>Delete</span>
          </button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php pager($page, $pages); ?>
  <?php else: ?><p class="muted">No notices found.</p><?php endif; ?>
</section>
<?php include 'partials/foot.php'; ?>
