-- Add missing columns to load_documents table for document verification

ALTER TABLE load_documents
ADD COLUMN IF NOT EXISTS document_id VARCHAR(255) UNIQUE AFTER doc_type,
ADD COLUMN IF NOT EXISTS original_filename VARCHAR(255) AFTER document_id,
ADD COLUMN IF NOT EXISTS stored_filename VARCHAR(255) AFTER original_filename,
ADD COLUMN IF NOT EXISTS file_size INT AFTER stored_filename,
ADD COLUMN IF NOT EXISTS mime_type VARCHAR(100) AFTER file_size,
ADD COLUMN IF NOT EXISTS uploader_id INT AFTER mime_type,
ADD INDEX IF NOT EXISTS idx_load_documents_document_id (document_id),
ADD INDEX IF NOT EXISTS idx_load_documents_uploader_id (uploader_id),
ADD CONSTRAINT fk_load_documents_uploader_id FOREIGN KEY (uploader_id) REFERENCES users(id) ON DELETE SET NULL;
