<?php
define('NO_SESSION', true);
require __DIR__ . '/lib.php';
$w = $CFG['wa'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {   // Meta verification (PHP "hub.x" ko "hub_x" bana deta hai)
    if (($_GET['hub_verify_token'] ?? '') === $w['verify_token']) { echo $_GET['hub_challenge'] ?? ''; exit; }
    http_response_code(403); exit('forbidden');
}

$raw = file_get_contents('php://input');
if (!empty($w['app_secret'])) {
    $sig = $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '';
    if (!hash_equals('sha256=' . hash_hmac('sha256', $raw, $w['app_secret']), $sig)) { http_response_code(403); exit('bad signature'); }
}
$rank = ['sent' => 1, 'delivered' => 2, 'read' => 3];
foreach ((json_decode($raw, true)['entry'] ?? []) as $entry) {
    foreach (($entry['changes'] ?? []) as $ch) {
        foreach (($ch['value']['statuses'] ?? []) as $st) {
            $id = $st['id'] ?? ''; $s = $st['status'] ?? '';
            if ($s === 'failed') {
                $err = mb_substr($st['errors'][0]['title'] ?? 'failed', 0, 290);
                q("UPDATE message_logs SET status='failed', error=? WHERE wamid=?", [$err, $id]);
            } elseif (isset($rank[$s])) {   // read ke baad delivered aaye to status piche na jaye
                $lower = array_keys(array_filter($rank, fn($v) => $v < $rank[$s]));
                if ($lower) {
                    $in = implode(',', array_fill(0, count($lower), '?'));
                    q("UPDATE message_logs SET status=? WHERE wamid=? AND status IN ($in)", array_merge([$s, $id], $lower));
                }
            }
        }
    }
}
echo 'ok';
