<?php
require __DIR__ . '/lib.php';
require_login();
$args = []; $where = notice_filters($args);

$total_notices = (int)q("SELECT COUNT(*) FROM notices n $where", $args)->fetchColumn();

$tot = [];
foreach (q("SELECT l.status, COUNT(*) c FROM message_logs l JOIN notices n ON n.id = l.notice_id $where GROUP BY l.status", $args) as $r) {
    $tot[$r['status']] = (int)$r['c'];
}

$stats = [
    'sent'      => array_sum($tot),
    'delivered' => ($tot['delivered'] ?? 0) + ($tot['read'] ?? 0),
    'read'      => $tot['read'] ?? 0,
    'failed'    => ($tot['failed'] ?? 0) + ($tot['invalid'] ?? 0),
];

$delivery_rate = $stats['sent'] > 0 ? round(($stats['delivered'] / $stats['sent']) * 100, 1) : 0;
$read_rate     = $stats['sent'] > 0 ? round(($stats['read'] / $stats['sent']) * 100, 1) : 0;
$active_students = (int)q("SELECT COUNT(*) FROM v_broadcast_students WHERE " . ACTIVE)->fetchColumn();

$notices = q("SELECT n.*, SUM(l.status IN ('delivered','read')) AS delivered, SUM(l.status IN ('failed','invalid')) AS failed
              FROM notices n LEFT JOIN message_logs l ON l.notice_id = n.id $where
              GROUP BY n.id ORDER BY n.id DESC LIMIT 5", $args)->fetchAll();
$title = 'Dashboard'; $active = 'dash';
include 'partials/head.php';
?>
<header class="head">
  <h1>Notice Broadcast Dashboard</h1>
  <a class="btn" href="compose.php">Send New Notice</a>
</header>

<?php filter_bar('index.php'); ?>

<section class="stats-container">
  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Total Notices</span>
      <span class="stat-box-number"><?= number_format($total_notices) ?></span>
      <span class="stat-box-sub">As per filters</span>
    </div>
    <div class="stat-box-icon green">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
        <polyline points="14 2 14 8 20 8"></polyline>
        <line x1="16" y1="13" x2="8" y2="13"></line>
        <line x1="16" y1="17" x2="8" y2="17"></line>
        <polyline points="10 9 9 9 8 9"></polyline>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Messages Sent</span>
      <span class="stat-box-number"><?= number_format($stats['sent']) ?></span>
      <span class="stat-box-sub">As per filters</span>
    </div>
    <div class="stat-box-icon orange">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="22" y1="2" x2="11" y2="13"></line>
        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Total Delivered</span>
      <span class="stat-box-number"><?= number_format($stats['delivered']) ?></span>
      <span class="stat-box-sub">Status: delivered / read</span>
    </div>
    <div class="stat-box-icon purple">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <polyline points="12 6 12 12 16 14"></polyline>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Read by Students</span>
      <span class="stat-box-number"><?= number_format($stats['read']) ?></span>
      <span class="stat-box-sub">Confirmed read</span>
    </div>
    <div class="stat-box-icon cyan">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Delivery Rate</span>
      <span class="stat-box-number"><?= $delivery_rate ?>%</span>
      <span class="stat-box-sub">Success rate</span>
    </div>
    <div class="stat-box-icon emerald">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
        <polyline points="22 4 12 14.01 9 11.01"></polyline>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Read Rate</span>
      <span class="stat-box-number"><?= $read_rate ?>%</span>
      <span class="stat-box-sub">Student engagement</span>
    </div>
    <div class="stat-box-icon amber">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Failed / Invalid</span>
      <span class="stat-box-number"><?= number_format($stats['failed']) ?></span>
      <span class="stat-box-sub">Failed or wrong number</span>
    </div>
    <div class="stat-box-icon red">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="12" cy="12" r="10"></circle>
        <line x1="12" y1="8" x2="12" y2="12"></line>
        <line x1="12" y1="16" x2="12.01" y2="16"></line>
      </svg>
    </div>
  </div>

  <div class="stat-box">
    <div class="stat-box-left">
      <span class="stat-box-title">Active Students</span>
      <span class="stat-box-number"><?= number_format($active_students) ?></span>
      <span class="stat-box-sub">In college database</span>
    </div>
    <div class="stat-box-icon indigo">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
      </svg>
    </div>
  </div>
</section>

<section class="panel">
  <div class="head" style="margin-bottom:12px">
    <h2 style="margin:0">Recent Notices</h2>
    <a href="notices.php" style="color: #6f42c1; font-weight: 600; text-decoration: none;">View all notices &raquo;</a>
  </div>
  <?php if ($notices): ?>
  <div class="scroll"><table>
    <tr><th>Notice</th><th>Sent To</th><th>Total</th><th>Delivered</th><th>Failed</th><th>Time</th><th>Status</th></tr>
    <?php foreach ($notices as $n): ?>
    <tr>
      <td><a href="notice.php?id=<?= (int)$n['id'] ?>"><?= e($n['title']) ?></a></td>
      <td><?= $n['target_class'] ? e(trim($n['target_class'] . ' ' . $n['target_batch'])) : 'All Students' ?></td>
      <td><?= number_format((int)$n['total']) ?></td>
      <td><?= number_format((int)$n['delivered']) ?></td>
      <td><?= number_format((int)$n['failed']) ?></td>
      <td><?= date('d M, h:i A', strtotime($n['created_at'])) ?></td>
      <td><span class="tag <?= e($n['status']) ?>"><?= $n['status'] === 'done' ? 'Completed' : 'Sending...' ?></span></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php else: ?><p class="muted">No notices found for selected filter.</p><?php endif; ?>
</section>
<?php include 'partials/foot.php'; ?>
