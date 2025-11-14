<?php
// api/search.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? max(1,intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page-1) * $limit;

if ($q === '') {
    // return latest docs
    $stmt = $mysqli->prepare("SELECT id, title, description, file_name, upload_date, LEFT(extracted_text,255) as snippet FROM documents ORDER BY upload_date DESC LIMIT ? OFFSET ?");
    $stmt->bind_param('ii', $limit, $offset);
} else {
    $like = "%$q%";
    $stmt = $mysqli->prepare("SELECT id, title, description, file_name, upload_date, LEFT(extracted_text,255) as snippet FROM documents WHERE extracted_text LIKE ? OR title LIKE ? OR description LIKE ? LIMIT ? OFFSET ?");
    $stmt->bind_param('sssii', $like, $like, $like, $limit, $offset);
}
$stmt->execute();
$res = $stmt->get_result();
$items = [];
while ($row = $res->fetch_assoc()) {
    $row['preview_url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . '/uploads/' . rawurlencode($row['file_name']);
    $items[] = $row;
}
echo json_encode(['success'=>true,'items'=>$items]);
