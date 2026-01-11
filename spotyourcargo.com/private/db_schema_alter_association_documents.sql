-- Add document-level approval/rejection columns to association_documents table

ALTER TABLE association_documents
ADD COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' AFTER checksum,
ADD COLUMN rejection_reason TEXT NULL AFTER status,
ADD COLUMN reviewed_at TIMESTAMP NULL AFTER rejection_reason,
ADD COLUMN reviewed_by INT NULL AFTER reviewed_at,
ADD INDEX idx_status (status),
ADD INDEX idx_reviewed_by (reviewed_by),
ADD CONSTRAINT fk_document_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL;

-- Set default status for existing documents
UPDATE association_documents SET status = 'pending' WHERE status = 'pending';
