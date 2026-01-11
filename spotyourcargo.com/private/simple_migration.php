<?php
include 'db.php';

try {
    // Try to add columns one by one
    $columns = [
        "document_id VARCHAR(255) UNIQUE",
        "original_filename VARCHAR(255)",
        "stored_filename VARCHAR(255)",
        "file_size INT",
        "mime_type VARCHAR(100)",
        "uploader_id INT"
    ];

    foreach ($columns as $column) {
        try {
            $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS $column");
            echo "Added $column\n";
        } catch (Exception $e) {
            echo "Failed to add $column: " . $e->getMessage() . "\n";
        }
    }

    echo "Migration completed\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
