<?php
// Helpers for the SMS-style flow (template + variables + class/subject/Excel). Builds on lib.php.
require_once __DIR__ . '/lib.php';

// Extra numbers to add to every class/all send. Currently empty, so no extra numbers are added.
// If needed, write them like this: ['9XXXXXXXXX', '8XXXXXXXXX']
const ADMIN_NUMBERS = [];
// These classes are skipped when class "all" is selected (same as the SMS list)
const SKIP_CLASSES  = '66,67,68,69,70,71,72,73,74,75';
// Phone number priority for WhatsApp: whatsapp_no first, then p_mobile (as in SMS), then mobile
const SMS_PHONE     = "COALESCE(NULLIF(whatsapp_no,''), NULLIF(p_mobile,''), NULLIF(mobile,''))";

/* ---------------- templates ---------------- */

function tpl_get($id) {
    $t = q("SELECT * FROM wa_templates WHERE id = ? AND active = 1", [(int)$id])->fetch();
    if (!$t) return null;
    preg_match_all('/\{\{(\d+)\}\}/', $t['body'], $m);
    $t['var_count'] = $m[1] ? max(array_map('intval', $m[1])) : 0;
    return $t;
}

function tpl_read_vars($count) {
    $v = [];
    for ($i = 1; $i <= $count; $i++) { $v[] = clean($_POST['var_' . $i] ?? ''); }
    return $v;
}

// Replace {{n}} with its value; if the value is empty, keep {{n}} as it is
function tpl_render($body, $vars) {
    return preg_replace_callback('/\{\{(\d+)\}\}/', function ($m) use ($vars) {
        $k = (int)$m[1] - 1;
        return (isset($vars[$k]) && $vars[$k] !== '') ? $vars[$k] : $m[0];
    }, $body);
}

function sync_meta_templates() {
    global $CFG; $w = $CFG['wa'];
    if (empty($w['waba_id'])) return ['ok' => false, 'error' => 'waba_id is missing in config.php (add it inside the wa array)'];

    $url = "https://graph.facebook.com/v25.0/{$w['waba_id']}/message_templates?status=APPROVED&limit=100&fields=name,language,components";
    $ca  = $w['cainfo'] ?? 'C:\\php8\\cacert.pem';
    $all = [];
    while ($url) {
        $ch  = curl_init($url);
        $opt = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
                CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $w['token']]];
        if ($ca && is_file($ca)) { $opt[CURLOPT_CAINFO] = $ca; }
        curl_setopt_array($ch, $opt);
        $res = curl_exec($ch); $cerr = curl_error($ch); curl_close($ch);
        $d = json_decode((string)$res, true);
        if (!isset($d['data'])) return ['ok' => false, 'error' => mb_substr($d['error']['message'] ?? ($cerr ?: 'Unknown error'), 0, 200)];
        $all = array_merge($all, $d['data']);
        $url = $d['paging']['next'] ?? null;
    }

    $added = $updated = $disabled = 0; $keep = [];
    foreach ($all as $t) {
        $body = ''; $ok = true;
        foreach ($t['components'] ?? [] as $c) {
            $type = strtoupper($c['type'] ?? '');
            if ($type === 'BODY') { $body = $c['text'] ?? ''; }
            if ($type === 'HEADER' && (($c['format'] ?? 'TEXT') !== 'TEXT' || strpos($c['text'] ?? '', '{{') !== false)) { $ok = false; }
        }
        if ($body === '' || preg_match('/\{\{\s*[^0-9\s}][^}]*\}\}/', $body)) { $ok = false; }   // named variables
        if (!$ok) continue;

        $row = q("SELECT id, body FROM wa_templates WHERE template_name = ? AND lang = ?", [$t['name'], $t['language']])->fetch();
        if ($row) {
            if ($row['body'] !== $body) { q("UPDATE wa_templates SET body = ? WHERE id = ?", [$body, $row['id']]); $updated++; }
            $keep[] = (int)$row['id'];
        } else {
            q("INSERT INTO wa_templates (heading, template_name, lang, body) VALUES (?,?,?,?)",
              [ucwords(str_replace('_', ' ', $t['name'])), $t['name'], $t['language'], $body]);
            $keep[] = (int)db()->lastInsertId(); $added++;
        }
    }
    if ($keep) {
        $in = implode(',', $keep);   // integer ids only, safe
        $disabled = q("UPDATE wa_templates SET active = 0 WHERE active = 1 AND id NOT IN ($in)")->rowCount();
    }
    return ['ok' => true, 'added' => $added, 'updated' => $updated, 'disabled' => $disabled];
}

/* ---------------- recipients (same queries as the SMS flow) ---------------- */

function recipients_for_class($cls, $sub = 'All') {
    if ($cls === 'all') {
        return q("SELECT sno, " . SMS_PHONE . " AS phone FROM student_info
                  WHERE status != 0 AND class NOT IN (" . SKIP_CLASSES . ")")->fetchAll();
    }
    // student_info2 does not exist in this database, so only the first part of the SMS query is used (status = 2)
    $f = ''; $a = [$cls];
    if ($sub !== 'All') { $f = ' AND (sub1 = ? OR sub2 = ? OR sub3 = ?)'; $a = array_merge($a, [$sub, $sub, $sub]); }
    return q("SELECT sno, " . SMS_PHONE . " AS phone FROM student_info
              WHERE class = ? AND status = 2 $f", $a)->fetchAll();
}

/* For custom/Excel numbers: look up the student in student_info by the last 10 digits, so the notice page can show name/class */
function match_students($list) {
    $keys = [];
    foreach ($list as $r) { if ($r['valid'] && $r['sno'] === null) { $keys[substr($r['phone'], -10)] = true; } }
    if (!$keys) return [];
    $map = [];
    foreach (array_chunk(array_keys($keys), 300) as $chunk) {
        $in = implode(',', array_fill(0, count($chunk), '?'));
        $parts = [];
        foreach (['p_mobile', 'mobile', 'whatsapp_no', 'parent_mobile'] as $c) {
            $parts[] = "SELECT sno, RIGHT(REPLACE($c, ' ', ''), 10) AS k FROM student_info WHERE RIGHT(REPLACE($c, ' ', ''), 10) IN ($in)";
        }
        $rows = q(implode(' UNION ALL ', $parts) . ' ORDER BY sno', array_merge($chunk, $chunk, $chunk, $chunk))->fetchAll();
        foreach ($rows as $r) { if (!isset($map[$r['k']])) { $map[$r['k']] = (int)$r['sno']; } }   // if several students share one number, use the first one
    }
    return $map;
}

/*
 * If manual (Excel/pasted) numbers are given, use only those; otherwise use the class/subject query.
 * Duplicates are removed based on the last 10 digits. Return: ['list' => [...], 'dupes' => n]
 */
function build_recipients($cls, $sub, $manual) {
    $raw = [];
    $manual = trim((string)$manual);
    if ($manual !== '') {
        foreach (preg_split('/[,\s]+/', $manual) as $p) {
            if ($p !== '') $raw[] = ['sno' => null, 'phone' => $p];
        }
    } else {
        foreach (ADMIN_NUMBERS as $a) { $raw[] = ['sno' => null, 'phone' => $a]; }
        foreach (recipients_for_class($cls, $sub) as $r) {
            if ((string)$r['phone'] !== '') $raw[] = ['sno' => $r['sno'], 'phone' => $r['phone']];
        }
    }
    $seen = []; $list = []; $dupes = 0;
    foreach ($raw as $r) {
        $n = normalize(ltrim(preg_replace('/\D/', '', (string)$r['phone']), '0'));
        if ($n === null) { $list[] = ['sno' => $r['sno'], 'phone' => mb_substr((string)$r['phone'], 0, 20), 'valid' => false]; continue; }
        $k = substr($n, -10);
        if (isset($seen[$k])) { $dupes++; continue; }
        $seen[$k] = true;
        $list[] = ['sno' => $r['sno'], 'phone' => $n, 'valid' => true];
    }
    if ($manual !== '') {
        $map = match_students($list);
        foreach ($list as &$r) {
            if ($r['valid'] && $r['sno'] === null) {
                $k = substr($r['phone'], -10);
                if (isset($map[$k])) { $r['sno'] = $map[$k]; }
            }
        }
        unset($r);
    }
    return ['list' => $list, 'dupes' => $dupes];
}

/* ---------------- sending ---------------- */

// Send the template message and record every number in message_logs. The dashboard and webhook use these logs.
function send_template_notice($nid, $tpl, $vars, $recipients, $useName = false) {
    global $CFG; $w = $CFG['wa'];
    @set_time_limit(0); ignore_user_abort(true);
    $url = "https://graph.facebook.com/v25.0/{$w['phone_id']}/messages";
    $ca  = $w['cainfo'] ?? 'C:\\php8\\cacert.pem';   // existing setting; skipped automatically if the file does not exist (e.g. on Linux)
    $ins = db()->prepare("INSERT INTO message_logs (notice_id,sno,phone,wamid,status,error) VALUES (?,?,?,?,?,?)");

    $names = [];
    if ($useName) {
        $snos = array_values(array_unique(array_filter(array_column($recipients, 'sno'))));
        foreach (array_chunk($snos, 500) as $chunk) {
            $in = implode(',', array_map('intval', $chunk));
            foreach (q("SELECT sno, stu_name FROM v_broadcast_students WHERE sno IN ($in)")->fetchAll() as $n) {
                $names[(int)$n['sno']] = mb_convert_case(clean($n['stu_name']), MB_CASE_TITLE, 'UTF-8');
            }
        }
    }

    $abort = false;
    foreach ($recipients as $r) {
        if ($abort) { $ins->execute([$nid, $r['sno'], $r['phone'], null, 'failed', 'Skipped: token invalid, sending stopped']); continue; }
        if (!$r['valid']) { $ins->execute([$nid, $r['sno'], $r['phone'], null, 'invalid', 'Invalid or empty phone number']); continue; }

        $v = $vars;
        if ($useName && $v) { $v[0] = ($names[(int)$r['sno']] ?? '') ?: 'Student'; }
        $components = [];
        if ($v) {
            $params = [];
            foreach ($v as $x) { $params[] = ['type' => 'text', 'text' => $x]; }
            $components = [['type' => 'body', 'parameters' => $params]];
        }

        $payload = ['messaging_product' => 'whatsapp', 'to' => $r['phone'], 'type' => 'template',
                    'template' => ['name' => $tpl['template_name'], 'language' => ['code' => $tpl['lang']]]];
        if ($components) { $payload['template']['components'] = $components; }

        $ch = curl_init($url);
        $opt = [
            CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $w['token'], 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];
        if ($ca && is_file($ca)) { $opt[CURLOPT_CAINFO] = $ca; }
        curl_setopt_array($ch, $opt);
        $res = curl_exec($ch); $code = curl_getinfo($ch, CURLINFO_HTTP_CODE); $cerr = curl_error($ch); curl_close($ch);

        $data = json_decode((string)$res, true);
        if ($code >= 200 && $code < 300 && isset($data['messages'][0]['id'])) {
            $ins->execute([$nid, $r['sno'], $r['phone'], $data['messages'][0]['id'], 'sent', null]);
        } else {
            $err = mb_substr($data['error']['message'] ?? ($cerr ?: 'Unknown error'), 0, 290);
            $ins->execute([$nid, $r['sno'], $r['phone'], null, 'failed', $err]);
            if ($code == 401) { $abort = true; }   // token invalid/expired, no point in sending further
        }
        usleep(120000);
    }
    q("UPDATE notices SET status='done' WHERE id=?", [$nid]);
}