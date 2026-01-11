<?php
include 'db.php';

try {
    echo "Adding processing result columns to load_documents table...\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS ocr_result JSON NULL COMMENT 'OCR processing results as JSON'");
    echo "Added ocr_result column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS field_extraction_result JSON NULL COMMENT 'Field extraction results as JSON'");
    echo "Added field_extraction_result column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS validation_result JSON NULL COMMENT 'Document validation results as JSON'");
    echo "Added validation_result column\n";

    $pdo->exec("ALTER TABLE load_documents ADD COLUMN IF NOT EXISTS llm_verification_result JSON NULL COMMENT 'LLM verification results as JSON'");
    echo "Added llm_verification_result column\n";

    $pdo->exec("ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_trust_score (trust_score)");
    echo "Added trust_score index\n";

    echo "Migration completed successfully!\n";

} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
