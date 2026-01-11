<?php
// Debug script to check file paths for a specific ID and type
include_once 'spotyourcargo.com/private/db.php';

$id = $_GET['id'] ?? 43;
$type = $_GET['type'] ?? 'association';

echo "Debugging file paths for ID: $id, Type: $type\n\n";

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

$stmt->execute([$id]);
$file = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$file) {
    echo "No file found in database for ID: $id, Type: $type\n";
    exit;
}

echo "Database record:\n";
echo "file_path: '" . ($file['file_path'] ?: 'NULL') . "'\n";
echo "stored_path: '" . ($file['stored_path'] ?: 'NULL') . "'\n";
echo "original_filename: '" . $file['original_filename'] . "'\n";
echo "document_type: '" . $file['document_type'] . "'\n\n";

// Simulate path construction logic
$relative_path = '';
if (!empty($file['stored_path']) && $file['stored_path'] !== '/') {
    $relative_path = $file['stored_path'];
    echo "Selected stored_path: '$relative_path'\n";
} elseif (!empty($file['file_path']) && $file['file_path'] !== '/') {
    $relative_path = $file['file_path'];
    echo "Selected file_path: '$relative_path'\n";
} else {
    echo "No valid file path found in database\n";
    exit;
}

echo "Before ltrim: '$relative_path'\n";
// Remove any leading/trailing slashes
$relative_path = ltrim($relative_path, '/\\');
echo "After ltrim: '$relative_path'\n";

// Construct full path (adjusted for debug script location in domains/)
if (strpos($relative_path, 'private/uploads/') === 0) {
    $file_path = __DIR__ . '/spotyourcargo.com/' . $relative_path;
} else {
    $file_path = __DIR__ . '/spotyourcargo.com/private/uploads/' . $relative_path;
}
echo "Constructed file_path: '$file_path'\n";

// Normalize
$file_path = str_replace('\\', '/', $file_path);
$file_path = preg_replace('#/+#', '/', $file_path);
echo "Normalized file_path: '$file_path'\n\n";

echo "File exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n";
if (file_exists($file_path)) {
    echo "Is file: " . (is_file($file_path) ? 'YES' : 'NO') . "\n";
    echo "Is readable: " . (is_readable($file_path) ? 'YES' : 'NO') . "\n";
    echo "File size: " . filesize($file_path) . " bytes\n";
} else {
    echo "Checking directory: " . dirname($file_path) . "\n";
    echo "Directory exists: " . (is_dir(dirname($file_path)) ? 'YES' : 'NO') . "\n";
}
?>
