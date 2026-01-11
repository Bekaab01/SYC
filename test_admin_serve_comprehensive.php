<?php
// Comprehensive test script for admin serve-file.php
include_once 'spotyourcargo.com/private/db.php';

// Get some test IDs
echo "Getting test document IDs...\n\n";

// Get association documents
$stmt = $pdo->query("SELECT id, stored_path, original_filename FROM association_documents LIMIT 3");
$assoc_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Association documents:\n";
foreach ($assoc_docs as $doc) {
    echo "ID: {$doc['id']}, Path: {$doc['stored_path']}, Filename: {$doc['original_filename']}\n";
}
echo "\n";

// Get carrier documents
$stmt = $pdo->query("SELECT id, stored_path, original_filename FROM shipper_documents LIMIT 3");
$carrier_docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Carrier documents:\n";
foreach ($carrier_docs as $doc) {
    echo "ID: {$doc['id']}, Path: {$doc['stored_path']}, Filename: {$doc['original_filename']}\n";
}
echo "\n";

// Test path construction for each
echo "Testing path construction...\n\n";

foreach ($assoc_docs as $doc) {
    echo "Testing association document ID: {$doc['id']}\n";
    test_path_construction($doc['id'], 'association');
    echo "\n";
}

foreach ($carrier_docs as $doc) {
    echo "Testing carrier document ID: {$doc['id']}\n";
    test_path_construction($doc['id'], 'carrier');
    echo "\n";
}

// Test invalid IDs
echo "Testing invalid IDs...\n";
test_path_construction(99999, 'association');
test_path_construction(99999, 'carrier');
echo "\n";

function test_path_construction($id, $type) {
    global $pdo;

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
        echo "No file found in database\n";
        return;
    }

    echo "Database record: stored_path='{$file['stored_path']}', file_path='{$file['file_path']}'\n";

    $relative_path = '';
    if (!empty($file['stored_path']) && $file['stored_path'] !== '/') {
        $relative_path = $file['stored_path'];
    } elseif (!empty($file['file_path']) && $file['file_path'] !== '/') {
        $relative_path = $file['file_path'];
    } else {
        echo "No valid file path found\n";
        return;
    }

    $relative_path = ltrim($relative_path, '/\\');

    $test_dir = 'C:\xampp\htdocs\domains\admin.spotyourcargo.com\public_html';

    if (strpos($relative_path, 'private/uploads/') === 0) {
        $file_path = $test_dir . '/../../spotyourcargo.com/' . $relative_path;
    } else {
        $file_path = $test_dir . '/../../spotyourcargo.com/private/uploads/' . $relative_path;
    }

    $file_path = realpath($file_path);

    if (!$file_path) {
        $file_path = $test_dir . '/../../spotyourcargo.com/' . $relative_path;
        $file_path = str_replace('\\', '/', $file_path);
        $file_path = preg_replace('#/+#', '/', $file_path);
    }

    $file_path = str_replace('\\', '/', $file_path);
    $file_path = preg_replace('#/+#', '/', $file_path);

    echo "Constructed path: $file_path\n";
    echo "File exists: " . (file_exists($file_path) ? 'YES' : 'NO') . "\n";
    if (file_exists($file_path)) {
        echo "Is file: " . (is_file($file_path) ? 'YES' : 'NO') . "\n";
        echo "Is readable: " . (is_readable($file_path) ? 'YES' : 'NO') . "\n";
    }
}
?>
