<?php
require_once __DIR__ . '/../db.php';

// --- Capture parameters ---
$filename = isset($_GET['file']) ? basename($_GET['file']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$filename || !$id) {
    http_response_code(400);
    exit('Missing file name or document ID');
}

// --- Delete file from /uploads ---
$filePath = __DIR__ . '/../uploads/' . $filename;
if (file_exists($filePath)) {
    unlink($filePath);
}

// --- Delete record from database ---
$stmt = $mysqli->prepare("DELETE FROM documents WHERE id = ?");
$stmt->bind_param('i', $id);
$stmt->execute();
$stmt->close();

// --- Reset AUTO_INCREMENT to maintain sequence ---
$mysqli->query("ALTER TABLE documents AUTO_INCREMENT = 1");

echo "✅ File and record deleted, ID reset.";
?>
