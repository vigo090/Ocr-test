<?php
// api/document.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid id']); exit; }
$stmt = $mysqli->prepare("SELECT id,title,description,file_name,file_type,extracted_text,upload_date,tags FROM documents WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $row['file_url'] = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'] . '/uploads/' . rawurlencode($row['file_name']);
    echo json_encode(['success'=>true,'document'=>$row]);
} else {
    echo json_encode(['success'=>false,'error'=>'Not found']);
}
