-- Add JSON columns for storing document processing results

ALTER TABLE load_documents
ADD COLUMN IF NOT EXISTS ocr_result JSON NULL COMMENT 'OCR processing results as JSON',
ADD COLUMN IF NOT EXISTS field_extraction_result JSON NULL COMMENT 'Field extraction results as JSON',
ADD COLUMN IF NOT EXISTS validation_result JSON NULL COMMENT 'Document validation results as JSON',
ADD COLUMN IF NOT EXISTS llm_verification_result JSON NULL COMMENT 'LLM verification results as JSON';

-- Add indexes for performance
ALTER TABLE load_documents ADD INDEX IF NOT EXISTS idx_load_documents_trust_score (trust_score);
