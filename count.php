<?php
require __DIR__ . '/lib.php';
require_login();
[$cls, $batch] = parse_target($_GET['target'] ?? 'ALL');
$rows = fetch_students($cls, $batch);
header('Content-Type: application/json');
echo json_encode(['total' => count($rows), 'valid' => count(array_filter($rows, fn($r) => normalize($r['phone'])))]);
