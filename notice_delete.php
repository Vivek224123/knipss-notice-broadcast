<?php
require __DIR__ . '/lib.php';
require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $id = (int)($_POST['id'] ?? 0);
    q("DELETE FROM message_logs WHERE notice_id = ?", [$id]);
    q("DELETE FROM notices WHERE id = ?", [$id]);
    flash('Notice deleted.', 'ok');
}
$back = $_POST['back'] ?? 'notices.php';
header('Location: ' . (in_array($back, ['notices.php', 'index.php'], true) ? $back : 'notices.php'));
