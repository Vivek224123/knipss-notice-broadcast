<?php
require __DIR__ . '/lib_broadcast.php';
require_login();

$tpl = null; $vars = []; $step = 1; $useName = false;

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['tpl'])) {   // step 1 -> 2
    $tpl = tpl_get($_GET['tpl']);
    if (!$tpl) { flash('Template not found or inactive.', 'err'); header('Location: broadcast.php'); exit; }
    $step = 2;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $tpl = tpl_get($_POST['tpl'] ?? 0);
    if (!$tpl) { flash('Template not found or inactive.', 'err'); header('Location: broadcast.php'); exit; }
    $vars = tpl_read_vars($tpl['var_count']);
    $useName = !empty($_POST['use_name']) && $tpl['var_count'] >= 1;
    if ($useName) { $vars[0] = '[Student Name]'; }

    if (in_array('', $vars, true)) {
        flash('Please fill in every variable.', 'err'); $step = 2;
    } elseif (($_POST['step'] ?? '') === 'send') {           // step 3 -> send
        $cls = $_POST['class'] ?? 'all'; $sub = $_POST['sub1'] ?? 'All';
        $rec = build_recipients($cls, $sub, $_POST['number'] ?? '');
        if (!$rec['list']) {
            flash('No phone numbers found for this selection.', 'err'); $step = 3;
        } else {
            $manual = trim($_POST['number'] ?? '') !== '';
            if ($manual)            { $label = 'Custom number list'; $batch = null; }
            elseif ($cls === 'all') { $label = null; $batch = null; }
            else {
                $label = q("SELECT class_name FROM v_broadcast_students WHERE class = ? LIMIT 1", [$cls])->fetchColumn() ?: $cls;
                $batch = $sub !== 'All' ? $sub : null;
            }
            q("INSERT INTO notices (title, body, target_class, target_batch, total) VALUES (?,?,?,?,?)",
              [$tpl['heading'], tpl_render($tpl['body'], $vars), $label, $batch, count($rec['list'])]);
            $nid = (int)db()->lastInsertId();
            flash('Sending to ' . count($rec['list']) . ' numbers.' . ($rec['dupes'] ? ' ' . $rec['dupes'] . ' duplicate number(s) skipped.' : ''), 'ok');
            finish_response('notice.php?id=' . $nid);        // redirect first
            send_template_notice($nid, $tpl, $vars, $rec['list'], $useName);   // then send in the background
            exit;
        }
    } else {                                                 // step 2 -> 3
        $step = 3;
    }
}

if ($step === 1) {   // Sync approved templates from Meta (once every 10 minutes, or via the button)
    if (isset($_GET['sync']) || time() - ($_SESSION['tpl_synced'] ?? 0) > 600) {
        $res = sync_meta_templates();
        if ($res['ok']) {
            $_SESSION['tpl_synced'] = time();
            if (isset($_GET['sync'])) flash("Synced from Meta: {$res['added']} new, {$res['updated']} updated, {$res['disabled']} disabled.", 'ok');
        } else {
            $_SESSION['tpl_synced'] = time() - 540;   // retry after 1 minute
            flash('Meta sync failed: ' . $res['error'], 'err');
        }
    }
}

$title = 'Template Broadcast'; $active = 'broadcast';
include 'partials/head.php';
$preview = $tpl ? tpl_render($tpl['body'], $vars) : '';
?>
<header class="head"><h1>Template Broadcast</h1><div><a class="btn ghost" href="broadcast.php?sync=1">Sync from Meta</a> <a class="btn ghost" href="templates.php">Manage Templates</a></div></header>

<?php if ($step === 1): ?>
<section class="panel">
  <h2>Choose a template</h2>
  <?php $rows = q("SELECT * FROM wa_templates WHERE active = 1 ORDER BY id")->fetchAll(); ?>
  <?php if ($rows): ?>
  <div class="scroll"><table>
    <tr><th>#</th><th>Name</th><th>Message</th><th>Meta template</th><th>Lang</th><th></th></tr>
    <?php foreach ($rows as $i => $r): ?>
    <tr>
      <td><?= $i + 1 ?></td><td><?= e($r['heading']) ?></td><td><?= nl2br(e($r['body'])) ?></td>
      <td><?= e($r['template_name']) ?></td><td><?= e($r['lang']) ?></td>
      <td><a class="btn sm" href="broadcast.php?tpl=<?= (int)$r['id'] ?>">Proceed</a></td>
    </tr>
    <?php endforeach; ?>
  </table></div>
  <?php else: ?>
  <p class="muted">No approved templates found. Create one in Meta WhatsApp Manager, then click "Sync from Meta".</p>
  <?php endif; ?>
</section>

<?php elseif ($step === 2): ?>
<div class="split">
  <form method="post" action="broadcast.php" class="panel form">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="tpl" value="<?= (int)$tpl['id'] ?>">
    <input type="hidden" name="step" value="review">
    <h2><?= e($tpl['heading']) ?></h2>
    <?php if ($tpl['var_count'] == 0): ?><p class="muted">This template has no variables.</p><?php endif; ?>
    <?php for ($i = 1; $i <= $tpl['var_count']; $i++): ?>
      <label>Variable <?= $i ?><input name="var_<?= $i ?>" id="var_<?= $i ?>" maxlength="300" required oninput="pv()" value="<?= e($vars[$i - 1] ?? '') ?>"></label>
      <?php if ($i === 1): ?>
      <label style="display:flex;gap:8px;align-items:center;font-weight:normal">
        <input type="checkbox" name="use_name" id="use_name" value="1" onchange="toggleName()" <?= $useName ? 'checked' : '' ?> style="width:auto">
        Use student name (each student's own name)
      </label>
      <?php endif; ?>
    <?php endfor; ?>
    <button class="btn">Next: choose recipients</button>
    <a class="btn ghost" href="broadcast.php">Back</a>
  </form>
  <div class="phone"><div class="bubble"><p id="pv" style="white-space:pre-line"><?= e($preview) ?></p><small>WhatsApp Preview</small></div></div>
</div>
<script>
const tplText = <?= json_encode($tpl['body'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
function pv() {
  document.getElementById('pv').textContent = tplText.replace(/\{\{(\d+)\}\}/g, (m, n) => {
    const el = document.getElementById('var_' + n);
    return (el && el.value.trim() !== '') ? el.value : m;
  });
}
function toggleName() {
  const cb = document.getElementById('use_name'), v1 = document.getElementById('var_1');
  if (!cb || !v1) return;
  if (cb.checked) { v1.dataset.old = v1.value === '[Student Name]' ? '' : v1.value; v1.value = '[Student Name]'; v1.disabled = true; v1.required = false; }
  else { v1.disabled = false; v1.required = true; v1.value = v1.dataset.old || ''; }
  pv();
}
if (document.getElementById('use_name') && document.getElementById('use_name').checked) toggleName();
pv();
</script>

<?php else: /* step 3 */ ?>
<?php $classes = q("SELECT class, class_name, COUNT(*) c FROM v_broadcast_students
                    WHERE class IS NOT NULL AND class <> '' AND " . ACTIVE . "
                    GROUP BY class, class_name ORDER BY class_name")->fetchAll(); ?>
<div class="split">
  <form method="post" action="broadcast.php" class="panel form" onsubmit="return confirm('Send this WhatsApp message now? This cannot be undone.')">
    <input type="hidden" name="csrf" value="<?= csrf() ?>">
    <input type="hidden" name="tpl" value="<?= (int)$tpl['id'] ?>">
    <input type="hidden" name="step" value="send">
    <?php foreach ($vars as $k => $v): ?><input type="hidden" name="var_<?= $k + 1 ?>" value="<?= e($v) ?>"><?php endforeach; ?>
    <?php if ($useName): ?><input type="hidden" name="use_name" value="1"><?php endif; ?>
    <h2>Choose recipients</h2>

    <label>Upload Excel (numbers in first column)<input type="file" id="excelFile" accept=".xlsx,.xls"></label>
    <label>Or enter numbers (comma separated)<input name="number" id="numberInput" placeholder="9876543210, 9123456789"></label>
    <p class="muted">If numbers are entered above, the class selection below is ignored.</p>

    <label>Class
      <select name="class" id="cls">
        <option value="all">All Students</option>
        <?php foreach ($classes as $c): ?><option value="<?= e($c['class']) ?>"><?= e($c['class_name']) ?> (<?= (int)$c['c'] ?>)</option><?php endforeach; ?>
      </select>
    </label>
    <label>Subject<select name="sub1" id="sub1"><option value="All">All</option></select></label>
    <p class="count" id="count">&nbsp;</p>

    <button class="btn">Send WhatsApp</button>
    <a class="btn ghost" href="broadcast.php?tpl=<?= (int)$tpl['id'] ?>">Back</a>
  </form>
  <div class="phone"><div class="bubble"><p style="white-space:pre-line"><?= e($preview) ?></p><small>WhatsApp Preview</small></div></div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
<script>
const $ = id => document.getElementById(id);

$('excelFile').addEventListener('change', e => {
  const file = e.target.files[0]; if (!file) return;
  const reader = new FileReader();
  reader.onload = ev => {
    const wb = XLSX.read(new Uint8Array(ev.target.result), { type: 'array' });
    const rows = XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1 });
    $('numberInput').value = rows.map(r => r[0]).filter(Boolean).join(',');
    refresh();
  };
  reader.readAsArrayBuffer(file);
});

async function loadSubjects() {
  const r = await fetch('broadcast_api.php?a=subjects&cls=' + encodeURIComponent($('cls').value));
  const d = await r.json();
  const sel = $('sub1'); sel.innerHTML = '';
  ['All', ...d.subjects].forEach(s => { const o = document.createElement('option'); o.value = s; o.textContent = s; sel.appendChild(o); });
}
function splitNumbers(t) {   // same rules as split_numbers() in lib_broadcast.php
  const out = [];
  t.split(/[,;\r\n]+/).forEach(chunk => {
    chunk = chunk.trim(); if (!chunk) return;
    const joined = chunk.replace(/[\s\-().]/g, '');
    const d = joined.replace(/\D/g, '').replace(/^0+/, '');
    const ok = d.length === 10 || (d.length === 12 && d.startsWith('91'));
    if (ok || !/\s/.test(chunk)) out.push(joined);
    else chunk.split(/\s+/).forEach(p => { if (p) out.push(p); });
  });
  return out;
}
async function refresh() {
  const num = $('numberInput').value.trim();
  if (num) {
    $('count').textContent = new Set(splitNumbers(num)).size + ' numbers entered.';
    return;
  }
  const r = await fetch('broadcast_api.php?a=count&cls=' + encodeURIComponent($('cls').value) + '&sub=' + encodeURIComponent($('sub1').value));
  const d = await r.json();
  $('count').textContent = d.total + ' students found, ' + d.valid + ' valid phone numbers.';
}
$('cls').onchange = async () => { await loadSubjects(); refresh(); };
$('sub1').onchange = refresh;
$('numberInput').oninput = refresh;
refresh();
</script>
<?php endif; ?>
<?php include 'partials/foot.php'; ?>