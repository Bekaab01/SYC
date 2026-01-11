-- Document Verification System Schema
-- This schema defines the data contracts for the future document verification system
-- No database queries or persistence logic yet - just schema definitions

-- Documents table for storing document metadata
CREATE TABLE IF NOT EXISTS document_verification_documents (
    id VARCHAR(36) PRIMARY KEY COMMENT 'Unique identifier for the document',
    owner_id INT NOT NULL COMMENT 'User ID who owns this document',
    document_type ENUM('identity_card', 'passport', 'drivers_license', 'certificate', 'contract', 'invoice') NOT NULL,
    processing_status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    trust_score DECIMAL(5,2) NULL COMMENT 'Trust score from 0.00 to 100.00, nullable until calculated',
    file_path VARCHAR(500) NULL COMMENT 'Path to stored document file',
    file_name VARCHAR(255) NULL COMMENT 'Original file name',
    file_size INT NULL COMMENT 'File size in bytes',
    mime_type VARCHAR(100) NULL COMMENT 'MIME type of the file',
    ocr_text TEXT NULL COMMENT 'Extracted text from OCR processing (future)',
    extracted_fields JSON NULL COMMENT 'Structured data extracted from document (future)',
    validation_results JSON NULL COMMENT 'Results from rule-based validation (future)',
    llm_verification JSON NULL COMMENT 'Results from LLM semantic verification (future)',
    processing_metadata JSON NULL COMMENT 'Metadata about processing steps',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_owner_id (owner_id),
    INDEX idx_document_type (document_type),
    INDEX idx_processing_status (processing_status),
    INDEX idx_trust_score (trust_score),
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verification logs table for audit trail
CREATE TABLE IF NOT EXISTS document_verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id VARCHAR(36) NOT NULL,
    user_id INT NOT NULL COMMENT 'User who performed the action',
    action ENUM('upload', 'ocr_processing', 'field_extraction', 'validation', 'llm_verification', 'trust_scoring', 'manual_review') NOT NULL,
    status ENUM('started', 'completed', 'failed') DEFAULT 'started',
    details JSON NULL COMMENT 'Additional details about the action',
    processing_time_ms INT NULL COMMENT 'Time taken for processing in milliseconds',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_document_id (document_id),
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (document_id) REFERENCES document_verification_documents(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- OCR Engines table for pluggable OCR providers (future)
CREATE TABLE IF NOT EXISTS document_verification_ocr_engines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Engine identifier (tesseract, google_vision, etc.)',
    display_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Whether this engine is currently active',
    priority INT DEFAULT 0 COMMENT 'Priority order for fallback (higher = preferred)',
    config JSON NULL COMMENT 'Engine-specific configuration',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- LLM Providers table for pluggable AI verification (future)
CREATE TABLE IF NOT EXISTS document_verification_llm_providers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE COMMENT 'Provider identifier (openai, anthropic, etc.)',
    display_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Whether this provider is currently active',
    priority INT DEFAULT 0 COMMENT 'Priority order for fallback (higher = preferred)',
    config JSON NULL COMMENT 'Provider-specific configuration (API keys, endpoints, etc.)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_is_active (is_active),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default OCR engines (inactive by default)
INSERT IGNORE INTO document_verification_ocr_engines (name, display_name, priority) VALUES
('tesseract', 'Tesseract OCR', 1),
('google_vision', 'Google Cloud Vision', 2),
('aws_textract', 'AWS Textract', 3);

-- Insert default LLM providers (inactive by default)
INSERT IGNORE INTO document_verification_llm_providers (name, display_name, priority) VALUES
('openai', 'OpenAI', 1),
('anthropic', 'Anthropic Claude', 2),
('google', 'Google Gemini', 3);
