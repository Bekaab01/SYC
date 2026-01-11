-- Add load_ref column to shipments table for public load reference IDs
-- Format: SYC-LD-XXXXXX (where XXXXXX is random uppercase alphanumeric)

ALTER TABLE shipments
ADD COLUMN load_ref VARCHAR(20) UNIQUE AFTER id,
ADD INDEX idx_shipments_load_ref (load_ref);
