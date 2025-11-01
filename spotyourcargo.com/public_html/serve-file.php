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
        SELECT ad.file_path, ad.original_filename, ad.document_type, a.id as association_id
        FROM association_documents ad
        JOIN associations a ON ad.association_id = a.id
        WHERE ad.id = ?
    ");
} else {
    $stmt = $pdo->prepare("
        SELECT cd.file_path, cd.original_filename, cd.document_type, c.id as carrier_id
        FROM carrier_documents cd
        JOIN carriers c ON cd.carrier_id = c.id
        WHERE cd.id = ?
    ");
}
$stmt->execute([$file_id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    http_response_code(404);
    die('File not found');
}

// Construct full file path
$file_path = __DIR__ . '/../' . $file['file_path'];

// Check if file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found on disk');
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
