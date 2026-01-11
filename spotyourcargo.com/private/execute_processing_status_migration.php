<?php
include 'db.php';

try {
    echo "Adding processing status columns to load_documents table...\n";

    $pdo->exec("
        ALTER TABLE load_documents
        ADD COLUMN IF NOT EXISTS ocr_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT 'OCR processing status',
        ADD COLUMN IF NOT EXISTS verification_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT 'AI verification processing status',
        ADD COLUMN IF NOT EXISTS ocr_confidence DECIMAL(5,2) DEFAULT 0 COMMENT 'OCR confidence percentage (0-100)',
        ADD COLUMN IF NOT EXISTS trust_score DECIMAL(5,2) DEFAULT 0 COMMENT 'AI verification trust score percentage (0-100)'
    ");

    echo "Processing status columns added successfully\n";

    // Add indexes for performance
    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_ocr_status (ocr_status)");
    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_verification_status (verification_status)");

    echo "Indexes added successfully\n";
    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
