<?php
// db.php
$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = ''; // set your password
$DB_NAME = 'ocr_db';

$mysqli = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode(['success'=>false,'error'=>'DB connection failed']);
    exit;
}
$mysqli->set_charset("utf8mb4");
?>
