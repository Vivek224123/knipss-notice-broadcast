<?php
require __DIR__ . '/lib.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$n = q("SELECT * FROM notices WHERE id = ?", [$id])->fetch();
if (!$n) { header('Location: notices.php'); exit; }

$label = ['sent' => 'Sent', 'delivered' => 'Delivered', 'read' => 'Read', 'failed' => 'Failed', 'invalid' => 'Invalid Number'];
$counts = [];
foreach (q("SELECT status, COUNT(*) c FROM message_logs WHERE notice_id = ? GROUP BY status", [$id]) as $r) $counts[$r['status']] = (int)$r['c'];

$w = ["l.notice_id = ?"]; $args = [$id];
$st = $_GET['st'] ?? '';  if (isset($label[$st])) { $w[] = "l.status = ?"; $args[] = $st; }
$s  = trim($_GET['s'] ?? ''); if ($s !== '') { $w[] = "(s.stu_name LIKE ? OR l.phone LIKE ?)"; $args[] = "%$s%"; $args[] = "%$s%"; }
$cl = $_GET['cl'] ?? '';  if ($cl !== '') { $w[] = "s.class_name = ?"; $args[] = $cl; }
$from = "FROM message_logs l LEFT JOIN v_broadcast_students s ON s.sno = l.sno WHERE " . implode(' AND ', $w);

$per = 50; $page = max(1, (int)($_GET['page'] ?? 1));
$total = (int)q("SELECT COUNT(*) $from", $args)->fetchColumn();
$pages = max(1, (int)ceil($total / $per)); $page = min($page, $pages);
$logs = q("SELECT l.*, s.stu_name, s.class_name AS class $from ORDER BY l.id LIMIT $per OFFSET " . (($page - 1) * $per), $args)->fetchAll();
$classes = $n['target_class'] === null
    ? q("SELECT DISTINCT s.class_name FROM message_logs l JOIN v_broadcast_students s ON s.sno = l.sno WHERE l.notice_id = ? ORDER BY 1", [$id])->fetchAll(PDO::FETCH_COLUMN)
    : [];

$title = $n['title']; $active = 'notices';
$extra_head = $n['status'] !== 'done' ? '<meta http-equiv="refresh" content="5">' : '';
include 'partials/head.php';
?>
<header class="head">
  <h1><?= e($n['title']) ?></h1>
  <div class="actions">
    <a class="btn-action-view" href="notices.php">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="19" y1="12" x2="5" y2="12"></line>
        <polyline points="12 19 5 12 12 5"></polyline>
      </svg>
      <span>Back</span>
    </a>
    <a class="btn-action-edit" href="notice_edit.php?id=<?= $id ?>">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
      </svg>
      <span>Edit</span>
    </a>
    <form method="post" action="notice_delete.php" onsubmit="return confirm('Delete this notice and its delivery records? Messages already sent on WhatsApp cannot be recalled.')">
      <input type="hidden" name="csrf" value="<?= csrf() ?>">
      <input type="hidden" name="id" value="<?= $id ?>">
      <input type="hidden" name="back" value="notices.php">
      <button class="btn-action-delete">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <polyline points="3 6 5 6 21 6"></polyline>
          <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
        </svg>
        <span>Delete</span>
      </button>
    </form>
  </div>
</header>
<section class="panel">
  <p><?= nl2br(e($n['body'])) ?></p>
  <p class="muted"><?= $n['target_class'] ? e(trim($n['target_class'] . ' ' . $n['target_batch'])) : 'All Students' ?> · <?= (int)$n['total'] ?> students · <?= $n['status'] === 'done' ? 'Completed' : 'Sending in progress, page will auto-refresh' ?></p>
  <div class="chips">
    <?php foreach ($label as $k => $v): if (empty($counts[$k])) continue; ?>
      <a class="tag <?= e($k) ?>" href="<?= e(url_with(['st' => $k, 'page' => 1])) ?>"><?= e($v) ?>: <?= $counts[$k] ?></a>
    <?php endforeach; ?>
  </div>
</section>

<form method="get" class="filters">
  <input type="hidden" name="id" value="<?= $id ?>">
  <label>Search name / phone<input name="s" value="<?= e($s) ?>" placeholder="Name or number"></label>
  <label>Status
    <select name="st"><option value="">All</option>
      <?php foreach ($label as $k => $v): ?><option value="<?= e($k) ?>" <?= $st === $k ? 'selected' : '' ?>><?= e($v) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php if ($classes): ?>
  <label>Class
    <select name="cl"><option value="">All</option>
      <?php foreach ($classes as $c): ?><option value="<?= e($c) ?>" <?= $cl === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button class="btn sm">Filter</button>
  <a class="btn sm ghost" href="notice.php?id=<?= $id ?>">Reset</a>
</form>

<section class="panel">
  <h2>Student Delivery Status <span class="muted">(<?= $total ?>)</span></h2>
  <div class="scroll"><table>
    <tr><th>Name</th><th>Class</th><th>Phone</th><th>Status</th><th>Details</th></tr>
    <?php foreach ($logs as $l): ?>
    <tr><td><?= e($l['stu_name']) ?></td><td><?= e($l['class']) ?></td><td><?= e($l['phone']) ?></td>
        <td><span class="tag <?= e($l['status']) ?>"><?= e($label[$l['status']] ?? $l['status']) ?></span></td>
        <td class="muted"><?= e($l['error']) ?></td></tr>
    <?php endforeach; ?>
    <?php if (!$logs): ?><tr><td colspan="5" class="muted">No records found.</td></tr><?php endif; ?>
  </table></div>
  <?php pager($page, $pages); ?>
</section>
<?php include 'partials/foot.php'; ?>
