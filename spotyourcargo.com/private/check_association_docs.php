<?php
require 'db.php';

try {
    $stmt = $pdo->query("SELECT id, stored_path, file_path, original_filename, document_type FROM association_documents ORDER BY id DESC");
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Association Documents:\n";
    echo "====================\n";

    if (empty($documents)) {
        echo "No association documents found.\n";
    } else {
        foreach ($documents as $doc) {
            echo "ID: {$doc['id']}\n";
            echo "Stored Path: {$doc['stored_path']}\n";
            echo "File Path: {$doc['file_path']}\n";
            echo "Original Filename: {$doc['original_filename']}\n";
            echo "Document Type: {$doc['document_type']}\n";

            echo "---\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
