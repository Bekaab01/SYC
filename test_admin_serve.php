<?php
// Test script to simulate admin serve-file.php path construction
include_once 'spotyourcargo.com/private/db.php';

$id = $_GET['id'] ?? 43;
$type = $_GET['type'] ?? 'association';

// Simulate the __DIR__ of admin.spotyourcargo.com/public_html/serve-file.php
$test_dir = 'C:\xampp\htdocs\domains\admin.spotyourcargo.com\public_html';

echo "Testing admin serve-file.php path construction for ID: $id, Type: $type\n\n";
echo "Simulated __DIR__: $test_dir\n\n";

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

// Simulate admin serve-file.php logic
$relative_path = '';
if (!empty($file['stored_path']) && $file['stored_path'] !== '/') {
    $relative_path = $file['stored_path'];
} elseif (!empty($file['file_path']) && $file['file_path'] !== '/') {
    $relative_path = $file['file_path'];
} else {
    echo "No valid file path found in database\n";
    exit;
}

echo "Selected relative_path: '$relative_path'\n";

// Remove any leading/trailing slashes
$relative_path = ltrim($relative_path, '/\\');
echo "After ltrim: '$relative_path'\n";

// If stored_path already starts with 'private/uploads/', don't add it again
if (strpos($relative_path, 'private/uploads/') === 0) {
    $file_path = $test_dir . '/../../spotyourcargo.com/' . $relative_path;
} else {
    $file_path = $test_dir . '/../../spotyourcargo.com/private/uploads/' . $relative_path;
}

// Resolve the path to handle .. properly
$original_file_path = $file_path;
$file_path = realpath($file_path);

echo "Path calculation:\n";
echo "Simulated __DIR__ = $test_dir\n";
echo "Relative path = '$relative_path'\n";
echo "Since relative_path starts with 'private/uploads/', using: test_dir . '/../../spotyourcargo.com/' . relative_path\n";
echo "Result: " . $test_dir . '/../../spotyourcargo.com/' . $relative_path . "\n";
echo "After realpath: " . ($file_path ?: 'NULL (realpath failed)') . "\n";
if (!$file_path) {
    echo "realpath failed for: $original_file_path\n";
    $file_path = $test_dir . '/../../spotyourcargo.com/' . $relative_path;
    $file_path = str_replace('\\', '/', $file_path);
    $file_path = preg_replace('#/+#', '/', $file_path);
    echo "Fallback path: $file_path\n";
}

echo "Current __DIR__: " . __DIR__ . "\n";
echo "Relative path starts with 'private/uploads/': " . (strpos($relative_path, 'private/uploads/') === 0 ? 'YES' : 'NO') . "\n";

echo "Constructed file_path: '$file_path'\n";

// Normalize the path (convert backslashes to forward slashes, remove duplicates)
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
