<?php
require __DIR__ . '/lib.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$n = q("SELECT * FROM notices WHERE id = ?", [$id])->fetch();
if (!$n) { header('Location: notices.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $t = trim($_POST['title'] ?? ''); $b = trim($_POST['body'] ?? '');
    if ($t === '' || $b === '') { flash('Both title and message are required.', 'err'); header('Location: notice_edit.php?id=' . $id); exit; }
    q("UPDATE notices SET title = ?, body = ? WHERE id = ?", [$t, $b, $id]);
    flash('Notice updated.', 'ok');
    header('Location: notice.php?id=' . $id); exit;
}
$title = 'Edit Notice'; $active = 'notices';
include 'partials/head.php';
?>
<header class="head"><h1>Edit Notice</h1><a class="btn sm ghost" href="notice.php?id=<?= $id ?>">Cancel</a></header>
<form method="post" class="panel form" style="max-width:720px">
  <input type="hidden" name="csrf" value="<?= csrf() ?>">
  <p class="muted" style="margin-top:0">This only changes the saved record here. Messages already sent on WhatsApp will not change.</p>
  <label>Notice Title<input name="title" maxlength="100" required value="<?= e($n['title']) ?>"></label>
  <label>Message<textarea name="body" rows="6" maxlength="700" required><?= e($n['body']) ?></textarea></label>
  <button class="btn">Save Changes</button>
</form>
<?php include 'partials/foot.php'; ?>
