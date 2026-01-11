-- Add tables and columns for Trust & Management Hub in Drafts tab

-- Update shipments table to add missing columns for trust system
ALTER TABLE shipments
ADD COLUMN IF NOT EXISTS cargo_name VARCHAR(255) AFTER shipper_id,
ADD COLUMN IF NOT EXISTS origin VARCHAR(255) AFTER cargo_name,
ADD COLUMN IF NOT EXISTS destination VARCHAR(255) AFTER origin,
ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0 AFTER status,
ADD COLUMN IF NOT EXISTS load_ref VARCHAR(20) UNIQUE AFTER is_verified,
ADD COLUMN IF NOT EXISTS is_boosted TINYINT(1) DEFAULT 0 AFTER load_ref,
ADD COLUMN IF NOT EXISTS boosted_at DATETIME NULL AFTER is_boosted,
ADD COLUMN IF NOT EXISTS trust_score INT DEFAULT 0 AFTER boosted_at;

-- Modify status enum to include Draft and Pending Verification
ALTER TABLE shipments MODIFY COLUMN status ENUM('Draft','Pending Verification','In-Transit','Delivered','posted','bidding','awarded','assigned','in_transit','delivered','cancelled') DEFAULT 'Draft';

-- Create load_documents table for document verification
CREATE TABLE IF NOT EXISTS load_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    doc_type ENUM('Invoice','Warehouse Release','SAD','Insurance') NOT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_load_documents_shipment_id (shipment_id),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shipment_views table for carrier engagement analytics
CREATE TABLE IF NOT EXISTS shipment_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    carrier_id INT NULL, -- Can be NULL for anonymous views
    viewed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shipment_views_shipment_id (shipment_id),
    INDEX idx_shipment_views_carrier_id (carrier_id),
    INDEX idx_shipment_views_viewed_at (viewed_at),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update existing shipments to have load_ref if missing
UPDATE shipments SET load_ref = CONCAT('SYC-LD-', LPAD(id, 6, '0')) WHERE load_ref IS NULL;

-- Create verification_logs table for fraud prevention logging
CREATE TABLE IF NOT EXISTS verification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    user_id INT NOT NULL,
    doc_type ENUM('Invoice','Warehouse Release','SAD','Insurance') NOT NULL,
    flagged TINYINT(1) DEFAULT 0,
    issues TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_verification_logs_shipment_id (shipment_id),
    INDEX idx_verification_logs_user_id (user_id),
    INDEX idx_verification_logs_created_at (created_at),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
