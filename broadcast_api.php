<?php
require __DIR__ . '/lib_broadcast.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

$a   = $_GET['a'] ?? '';
$cls = $_GET['cls'] ?? 'all';

if ($a === 'subjects') {
    $subjects = [];
    if ($cls !== 'all') {
        // SMS flow ke filter jaisa: sub1/sub2/sub3 me jo bhi values is class ke students ke paas hain
        $sql = "SELECT DISTINCT s FROM (
                  SELECT sub1 AS s FROM student_info  WHERE class = ?
                  UNION SELECT sub2 FROM student_info  WHERE class = ?
                  UNION SELECT sub3 FROM student_info  WHERE class = ?
                  UNION SELECT sub1 FROM student_info2 WHERE class = ?
                  UNION SELECT sub2 FROM student_info2 WHERE class = ?
                  UNION SELECT sub3 FROM student_info2 WHERE class = ?
                ) t WHERE s IS NOT NULL AND s <> '' ORDER BY s";
        $subjects = q($sql, array_fill(0, 6, $cls))->fetchAll(PDO::FETCH_COLUMN);
    }
    echo json_encode(['subjects' => $subjects], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($a === 'count') {
    $rows  = recipients_for_class($cls, $_GET['sub'] ?? 'All');
    $valid = count(array_filter($rows, fn($r) => normalize(ltrim(preg_replace('/\D/', '', (string)$r['phone']), '0'))));
    echo json_encode(['total' => count($rows), 'valid' => $valid]);
    exit;
}

http_response_code(400);
echo json_encode(['error' => 'bad request']);
