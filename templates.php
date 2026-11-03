<?php
require __DIR__ . '/lib_broadcast.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['do'] ?? '') === 'toggle') {
        q("UPDATE wa_templates SET active = 1 - active WHERE id = ?", [(int)($_POST['id'] ?? 0)]);
        flash('Template updated.', 'ok');
    }
    header('Location: templates.php'); exit;
}

$rows = q("SELECT * FROM wa_templates ORDER BY id")->fetchAll();
$title = 'Templates'; $active = 'templates';
include 'partials/head.php';
?>
<header class="head"><h1>WhatsApp Templates</h1><a class="btn" href="broadcast.php">Template Broadcast</a></header>

<section class="panel">
  <h2>Saved templates</h2>
  <?php if ($rows): ?>
  <div class="scroll"><table>
    <tr><th>#</th><th>Name</th><th>Message</th><th>Meta template</th><th>Lang</th><th>Status</th><th></th></tr>
    <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td><td><?= e($r['heading']) ?></td><td><?= nl2br(e($r['body'])) ?></td>
      <td><?= e($r['template_name']) ?></td><td><?= e($r['lang']) ?></td>
      <td><span class="tag <?= $r['active'] ? 'done' : 'failed' ?>"><?= $r['active'] ? 'Active' : 'Disabled' ?></span></td>
      <td>
        <form method="post" style="margin:0">
          <input type="hidden" name="csrf" value="<?= csrf() ?>">
          <input type="hidden" name="do" value="toggle">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn sm ghost"><?= $r['active'] ? 'Disable' : 'Enable' ?></button>
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php else: ?><p class="muted">No templates yet.</p><?php endif; ?>
</section>

<?php include 'partials/foot.php'; ?>