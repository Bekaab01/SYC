<?php
include 'db.php';

try {
    echo "Starting load_documents table migration...\n";

    // Add document_id column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS document_id VARCHAR(255) UNIQUE AFTER doc_type");
    echo "Added document_id column\n";

    // Add original_filename column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS original_filename VARCHAR(255) AFTER document_id");
    echo "Added original_filename column\n";

    // Add stored_filename column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS stored_filename VARCHAR(255) AFTER original_filename");
    echo "Added stored_filename column\n";

    // Add file_size column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS file_size INT AFTER stored_filename");
    echo "Added file_size column\n";

    // Add mime_type column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) AFTER file_size");
    echo "Added mime_type column\n";

    // Add uploader_id column
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS uploader_id INT AFTER mime_type");
    echo "Added uploader_id column\n";

    // Add indexes
    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_document_id (document_id)");
    echo "Added document_id index\n";

    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_uploader_id (uploader_id)");
    echo "Added uploader_id index\n";

    // Add foreign key constraint
    $pdo->exec("ALTER TABLE load_documents ADD CONSTRAINT fk_load_documents_uploader_id FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL");
    echo "Added foreign key constraint\n";

    // Add OCR-related columns
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_text TEXT NULL COMMENT 'Extracted text from OCR processing'");
    echo "Added ocr_text column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_engine VARCHAR(50) NULL COMMENT 'OCR engine used (tesseract, google_vision, aws_textract)'");
    echo "Added ocr_engine column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_confidence DECIMAL(5,2) NULL COMMENT 'OCR confidence score (0-100)'");
    echo "Added ocr_confidence column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT 'OCR processing status'");
    echo "Added ocr_status column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_processed_at TIMESTAMP NULL COMMENT 'When OCR processing was completed'");
    echo "Added ocr_processed_at column\n";

    // Add verification-related columns
    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS verification_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending' COMMENT 'Document verification status'");
    echo "Added verification_status column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS trust_score DECIMAL(5,2) NULL COMMENT 'Calculated trust score (0-100)'");
    echo "Added trust_score column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS processed_at TIMESTAMP NULL COMMENT 'When verification processing was completed'");
    echo "Added processed_at column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS error_message TEXT NULL COMMENT 'Error message if processing failed'");
    echo "Added error_message column\n";

    // Add indexes for performance
    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_ocr_status (ocr_status)");
    echo "Added ocr_status index\n";

    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_verification_status (verification_status)");
    echo "Added verification_status index\n";

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
