<?php

if (PHP_SAPI !== 'cli' && !defined('NO_SESSION')) { session_start(); }
$CFG = require __DIR__ . '/config.php';

// status 0 = ab enrolled nahi (left / purane records). SMS wale flow jaisi hi definition.
const ACTIVE = "status <> 0";
const PHONE  = "COALESCE(NULLIF(whatsapp_no,''), NULLIF(mobile,''))";

function db() {
    static $p; global $CFG;
    if (!$p) {
        $d = $CFG['db'];
        $p = new PDO("mysql:host={$d['host']};dbname={$d['name']};charset=utf8mb4", $d['user'], $d['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    }
    return $p;
}
function q($sql, $args = []) { $s = db()->prepare($sql); $s->execute($args); return $s; }
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function flash($m, $c = 'ok') { $_SESSION['flash'][] = [$c, $m]; }
function require_login() { if (empty($_SESSION['admin'])) { header('Location: login.php'); exit; } }
function csrf() { return $_SESSION['csrf'] ??= bin2hex(random_bytes(16)); }
function check_csrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) { http_response_code(400); exit('Session expired. Please reload the page.'); }
}
function normalize($n) {
    $d = preg_replace('/\D/', '', (string)$n);
    if (strlen($d) == 10) return '91' . $d;
    if (strlen($d) == 12 && substr($d, 0, 2) == '91') return $d;
    return null;
}
function clean($t) { return trim(preg_replace('/\s+/u', ' ', (string)$t)); } // template param me newline allowed nahi
function parse_target($t) {
    if ($t === 'ALL' || $t === '') return [null, null];
    $p = explode('||', $t);
    return [$p[0] !== '' ? $p[0] : null, ($p[1] ?? '') !== '' ? $p[1] : null];
}
function fetch_students($cls = null, $batch = null) {
    $sql = "SELECT sno, stu_name, " . PHONE . " AS phone FROM v_broadcast_students WHERE " . ACTIVE;
    $args = [];
    if ($cls)   { $sql .= " AND class = ?";  $args[] = $cls; }
    if ($batch) { $sql .= " AND batch = ?";  $args[] = $batch; }
    return q($sql, $args)->fetchAll();
}

// Browser ko turant redirect karke baaki kaam background me chalane ke liye
function finish_response($location) {
    ignore_user_abort(true); set_time_limit(0);
    session_write_close();
    header('Location: ' . $location); header('Connection: close'); header('Content-Length: 0');
    if (function_exists('fastcgi_finish_request')) { fastcgi_finish_request(); }
    else { while (ob_get_level()) ob_end_flush(); flush(); }
}

function send_notice($nid, $title, $body, $students) {
    global $CFG; $w = $CFG['wa'];
    $url = "https://graph.facebook.com/v25.0/{$w['phone_id']}/messages"; 
    $ins = db()->prepare("INSERT INTO message_logs (notice_id,sno,phone,wamid,status,error) VALUES (?,?,?,?,?,?)");
    foreach ($students as $s) {
        $phone = normalize($s['phone']);
        if (!$phone) { $ins->execute([$nid, $s['sno'], $s['phone'], null, 'invalid', 'Invalid or empty phone number']); continue; }
        $payload = [
            'messaging_product' => 'whatsapp', 'to' => $phone, 'type' => 'template',
            'template' => [
                'name' => $w['template'], 'language' => ['code' => $w['lang']],
                // 'components' => [[ 'type' => 'body', 'parameters' => [
                //     ['type' => 'text', 'text' => clean($s['stu_name']) ?: 'Student'],
                //     ['type' => 'text', 'text' => clean($title)],
                //     ['type' => 'text', 'text' => clean($body)],
                // ]]],
            ],
        ];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_CAINFO => 'C:\\php8\\cacert.pem', 
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $w['token'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);
        $data = json_decode((string)$res, true);
        $wamid = null; $status = 'sent'; $err = null;
        if ($code >= 200 && $code < 300 && isset($data['messages'][0]['id'])) { $wamid = $data['messages'][0]['id']; }
        else { $status = 'failed'; $err = mb_substr($data['error']['message'] ?? ($cerr ?: 'Unknown error'), 0, 290); }
        $ins->execute([$nid, $s['sno'], $phone, $wamid, $status, $err]);
        usleep(120000); // rate limit se bachne ke liye
    }
    q("UPDATE notices SET status='done' WHERE id=?", [$nid]);
}

// ---------- filters / pagination helpers ----------
function url_with($changes) { return '?' . http_build_query(array_merge($_GET, $changes)); }

function notice_filters(&$args) {
    $w = [];
    $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $w[] = "n.created_at >= ?"; $args[] = "$from 00:00:00"; }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   { $w[] = "n.created_at <= ?"; $args[] = "$to 23:59:59"; }
    $c = $_GET['cls'] ?? '';
    if ($c === '__ALL__') { $w[] = "n.target_class IS NULL"; }
    elseif ($c !== '')    { $w[] = "n.target_class = ?"; $args[] = $c; }
    $s = trim($_GET['q'] ?? '');
    if ($s !== '') { $w[] = "(n.title LIKE ? OR n.body LIKE ?)"; $args[] = "%$s%"; $args[] = "%$s%"; }
    $st = $_GET['st'] ?? '';
    if (in_array($st, ['sending', 'done'], true)) { $w[] = "n.status = ?"; $args[] = $st; }
    return $w ? ' WHERE ' . implode(' AND ', $w) : '';
}

function filter_bar($action) {
    $classes = q("SELECT DISTINCT target_class FROM notices WHERE target_class IS NOT NULL AND target_class <> '' ORDER BY target_class")->fetchAll(PDO::FETCH_COLUMN);
    $cur  = $_GET['cls'] ?? '';
    $st   = $_GET['st'] ?? '';
    $from = $_GET['from'] ?? '';
    $to   = $_GET['to'] ?? '';
    $q    = $_GET['q'] ?? '';
    ?>
    <div class="filter-card">
      <form method="get" action="<?= e($action) ?>" class="filter-form">
        <div class="filter-group">
          <label class="filter-label" for="filter-cls">Class / Audience</label>
          <select name="cls" id="filter-cls" class="filter-input">
            <option value="">All classes</option>
            <option value="__ALL__" <?= $cur === '__ALL__' ? 'selected' : '' ?>>All Students</option>
            <?php foreach ($classes as $c): ?>
              <option value="<?= e($c) ?>" <?= $cur === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-group">
          <label class="filter-label" for="filter-from">From date</label>
          <input type="date" name="from" id="filter-from" value="<?= e($from) ?>" class="filter-input">
        </div>

        <div class="filter-group">
          <label class="filter-label" for="filter-to">To date</label>
          <input type="date" name="to" id="filter-to" value="<?= e($to) ?>" class="filter-input">
        </div>

        <div class="filter-group">
          <label class="filter-label" for="filter-st">Status</label>
          <select name="st" id="filter-st" class="filter-input">
            <option value="">All status</option>
            <option value="done" <?= $st === 'done' ? 'selected' : '' ?>>Completed</option>
            <option value="sending" <?= $st === 'sending' ? 'selected' : '' ?>>Sending</option>
          </select>
        </div>

        <?php if ($action === 'notices.php' || !empty($q)): ?>
        <div class="filter-group">
          <label class="filter-label" for="filter-q">Search</label>
          <input type="text" name="q" id="filter-q" value="<?= e($q) ?>" placeholder="Title or message" class="filter-input">
        </div>
        <?php endif; ?>

        <div class="filter-actions">
          <button type="submit" class="filter-btn-apply">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
              <polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>
            </svg>
            <span>Apply</span>
          </button>
          <a href="<?= e($action) ?>" class="filter-btn-reset">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
              <path d="M3 3v5h5"/>
            </svg>
            <span>Reset</span>
          </a>
        </div>
      </form>
    </div>
    <?php
}

function pager($page, $pages) {
    if ($pages <= 1) return;
    echo '<nav class="pager">';
    if ($page > 1) echo '<a href="' . e(url_with(['page' => $page - 1])) . '">&laquo; Prev</a>';
    echo '<span>Page ' . (int)$page . ' of ' . (int)$pages . '</span>';
    if ($page < $pages) echo '<a href="' . e(url_with(['page' => $page + 1])) . '">Next &raquo;</a>';
    echo '</nav>';
}