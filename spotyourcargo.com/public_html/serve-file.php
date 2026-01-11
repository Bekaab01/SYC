<?php
// Secure file serving script for uploaded documents
// Include database connection
include_once __DIR__ . '/../private/db.php';

// Check if id is provided (for both carrier and association documents)
if (!isset($_GET['id'])) {
    http_response_code(400);
    die('File ID required');
}

$file_id = $_GET['id'];
$type = $_GET['type'] ?? 'carrier'; // Default to carrier if no type specified

// Fetch file information from database based on type
if ($type === 'association') {
    $stmt = $pdo->prepare("
        SELECT ad.file_path, ad.stored_path, ad.original_filename, ad.document_type, a.id as association_id
        FROM association_documents ad
        JOIN associations a ON ad.association_id = a.id
        WHERE ad.id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT cd.file_path, cd.stored_path, cd.original_filename, cd.document_type, c.id as carrier_id
        FROM shipper_documents cd
        JOIN shippers c ON cd.shipper_id = c.id
        WHERE cd.id = ?
    ");
}
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    die('File not found');
}

// Debug: Check what paths we have
error_log("File paths - file_path: " . $file['file_path'] . ", stored_path: " . $file['stored_path']);

// Construct full file path - prefer stored_path if available, fallback to file_path
$relative_path = '';
if (!empty($file['stored_path']) && $file['stored_path'] !== '/') {
    $relative_path = $file['stored_path'];
} elseif (!empty($file['file_path']) && $file['file_path'] !== '/') {
    $relative_path = $file['file_path'];
} else {
    http_response_code(404);
    die('No valid file path found in database');
}

// Remove any leading/trailing slashes and construct absolute path
$relative_path = ltrim($relative_path, '/\\');
$file_path = __DIR__ . '/../private/uploads/' . $relative_path;

// Normalize the path (convert backslashes to forward slashes, remove duplicates)
$file_path = str_replace('\\', '/', $file_path);
$file_path = preg_replace('#/+#', '/', $file_path);

error_log("Constructed file path: " . $file_path);

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    error_log("File does not exist at: " . $file_path);
    die('File not found on disk');
}

if (is_dir($file_path)) {
    http_response_code(404);
    error_log("Path is a directory: " . $file_path);
    die('Path is a directory, not a file');
}

if (!is_file($file_path)) {
    http_response_code(404);
    error_log("Path is not a regular file: " . $file_path);
    die('Path is not a regular file');
}

// Check if file is readable
if (!is_readable($file_path)) {
    http_response_code(403);
    error_log("File is not readable: " . $file_path);
    die('File is not readable');
}

// Get file extension and determine MIME type
$extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
$mime_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'txt' => 'text/plain'
];

$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Set headers for file download/display
if (isset($_GET['download'])) {
    // Force download
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
} else {
    // Display inline (for images)
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
}

header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file content
readfile($file_path);
exit();
?>
