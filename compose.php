<?php
require __DIR__ . '/lib.php';
require_login();

// Purana direct-send page band: ye hello_world bhejta tha. Ab Template Broadcast use hota hai.
header('Location: broadcast.php'); exit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $t = trim($_POST['title'] ?? ''); $b = trim($_POST['body'] ?? '');
    [$cls, $batch] = parse_target($_POST['target'] ?? 'ALL');
    if ($t === '' || $b === '') { flash('Both title and message are required.', 'err'); header('Location: compose.php'); exit; }
    $students = fetch_students($cls, $batch);
    if (!$students) { flash('No students found in this class.', 'err'); header('Location: compose.php'); exit; }
    $cname = $cls ? (q("SELECT class_name FROM v_broadcast_students WHERE class = ? LIMIT 1", [$cls])->fetchColumn() ?: $cls) : null;
    q("INSERT INTO notices (title, body, target_class, target_batch, total) VALUES (?,?,?,?,?)",
      [$t, $b, $cname, $batch, count($students)]);
    $nid = (int)db()->lastInsertId();
    flash('Sending notice to ' . count($students) . ' students.', 'ok');
    finish_response('notice.php?id=' . $nid);   // Redirect first
    send_notice($nid, $t, $b, $students);       // Then send in background
    exit;
}

$groups = q("SELECT class, class_name, COUNT(*) c FROM v_broadcast_students
             WHERE class IS NOT NULL AND class <> '' AND " . ACTIVE . "
             GROUP BY class, class_name ORDER BY class_name")->fetchAll();
$title = 'New Notice'; $active = 'compose';
include 'partials/head.php';
?>
<header class="head"><h1>New Notice</h1></header>
<div class="split">
  <form method="post" class="panel form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <label>Send to
      <select name="target" id="target">
        <option value="ALL">All Students</option>
        <?php foreach ($groups as $g): ?>
          <option value="<?= e($g['class']) ?>"><?= e($g['class_name']) ?> (<?= (int)$g['c'] ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <p class="count" id="count">&nbsp;</p>
    <label>Notice Title<input name="title" id="title" maxlength="100" required placeholder="e.g. Exam Schedule Change"></label>
    <label>Message<textarea name="body" id="body" rows="6" maxlength="700" required placeholder="Enter complete notice details here"></textarea></label>
    <button class="btn" onclick="return confirm('Are you sure you want to send this notice? This action cannot be undone.')">Send Now</button>
  </form>
  <div class="phone">
    <div class="bubble">
      <p>Dear <b>Rahul</b>, new notice from college: <b id="pt">title</b>. <span id="pb">message</span></p>
      <small>WhatsApp Preview</small>
    </div>
  </div>
</div>
<script>
const $ = id => document.getElementById(id);
const sync = () => { $('pt').textContent = $('title').value || 'title'; $('pb').textContent = $('body').value || 'message'; };
$('title').oninput = $('body').oninput = sync;
async function count() {
  const r = await fetch('count.php?target=' + encodeURIComponent($('target').value));
  const d = await r.json();
  $('count').textContent = d.total + ' students found, ' + d.valid + ' valid phone numbers.';
}
$('target').onchange = count; count();
</script>
<?php include 'partials/foot.php'; ?>