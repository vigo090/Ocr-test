<?php
// ===============================================
// OCR Upload Script - Fully Cleaned and Debug-Ready (No Tags)
// ===============================================

ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('max_execution_time', 300); // 300 seconds = 5 minutes

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../db.php';

// --- Ensure uploads directory exists ---
$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// --- Debug logger ---
function log_debug($msg) {
    file_put_contents(__DIR__ . '/../debug_log.txt', '[' . date('Y-m-d H:i:s') . "] $msg\n", FILE_APPEND);
}

log_debug("Upload script started");

// --- Validate request method ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Use POST method']);
    exit;
}

// --- Collect form fields ---
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';

if (!isset($_FILES['file'])) {
    log_debug("No file found in upload");
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    log_debug("Upload error code: " . $file['error']);
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
    exit;
}

// --- Validate file type ---
$allowed = ['image/png', 'image/jpeg', 'image/jpg', 'image/tiff', 'application/pdf'];
if (!in_array($file['type'], $allowed)) {
    log_debug("Invalid file type: " . $file['type']);
    echo json_encode(['success' => false, 'error' => 'Unsupported file type']);
    exit;
}

// --- Save uploaded file ---
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$safeName = uniqid('doc_') . '.' . $ext;
$dest = $uploadDir . '/' . $safeName;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    log_debug("Failed to move uploaded file to $dest");
    echo json_encode(['success' => false, 'error' => 'Failed to move uploaded file']);
    exit;
}

log_debug("File moved successfully: $safeName");

// --- OCR PROCESSING FUNCTION ---
function run_ocr($filepath) {
    log_debug("Starting OCR on $filepath");

    $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $output = '';

    // Adjust if your Tesseract path differs
    $tessPath = "\"C:\\Program Files\\Tesseract-OCR\\tesseract.exe\"";

    if ($ext === 'pdf') {
        $prefix = tempnam(sys_get_temp_dir(), 'pdf_');
        @unlink($prefix);
        $cmd = "pdftoppm " . escapeshellarg($filepath) . " " . escapeshellarg($prefix) . " -png 2>&1";
        exec($cmd, $out, $ret);
        log_debug("pdftoppm returned code $ret");

        $images = glob($prefix . '-*.png');
        foreach ($images as $img) {
            $cmd2 = "$tessPath " . escapeshellarg($img) . " stdout 2>&1";
            log_debug("Running OCR command: $cmd2");
            $output .= shell_exec($cmd2);
            @unlink($img);
        }
    } else {
        $cmd = "$tessPath " . escapeshellarg($filepath) . " stdout 2>&1";
        log_debug("Running OCR command: $cmd");
        $output = shell_exec($cmd);
    }

    log_debug("OCR output length: " . strlen($output));
    return $output;
}

// --- Run OCR and Clean Text ---
$extracted_text = '';
try {
    $extracted_text = run_ocr($dest);

    // === CLEANUP UNWANTED TEXT ===
    // Remove “Estimating resolution …”
    $extracted_text = preg_replace('/Estimating resolution.*/i', '', $extracted_text);

    // Remove localhost URLs
    $extracted_text = preg_replace('/https?:\/\/localhost[^\s]+/i', '', $extracted_text);
    $extracted_text = preg_replace('/localhost[^\s]+/i', '', $extracted_text);

    // Remove ©, +, and @ symbols (including leading @)
    $extracted_text = str_replace(['©', '+'], '', $extracted_text);
    $extracted_text = preg_replace('/^\s*@\s*/m', '', $extracted_text); // remove @ at start of lines
    $extracted_text = preg_replace('/\s@\s?/', ' ', $extracted_text);   // remove lone @ inside text

    // Remove long blank sections
    $extracted_text = preg_replace("/(\r?\n){3,}/", "\n\n", $extracted_text);

    // Trim spaces and newlines
    $extracted_text = trim($extracted_text);

    log_debug("Cleaned OCR text length: " . strlen($extracted_text));

} catch (Exception $e) {
    log_debug("OCR exception: " . $e->getMessage());
    $extracted_text = '';
}

// --- Store result in database ---
$stmt = $mysqli->prepare("INSERT INTO documents (title, description, file_name, file_type, extracted_text) VALUES (?, ?, ?, ?, ?)");
if (!$stmt) {
    log_debug("DB prepare failed: " . $mysqli->error);
    echo json_encode(['success' => false, 'error' => 'Database prepare failed']);
    exit;
}

$file_type = $file['type'];
$stmt->bind_param('sssss', $title, $description, $safeName, $file_type, $extracted_text);

if (!$stmt->execute()) {
    log_debug("DB execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'error' => 'Database insert failed']);
    exit;
}

$docId = $stmt->insert_id;
$stmt->close();

$baseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://") . $_SERVER['HTTP_HOST'];
$previewUrl = $baseUrl . '/uploads/' . rawurlencode($safeName);

log_debug("Upload success - ID $docId");

// --- Final JSON Response ---
echo json_encode([
    'success' => true,
    'id' => $docId,
    'message' => 'Upload & OCR complete',
    'preview_url' => $previewUrl
]);
?>
