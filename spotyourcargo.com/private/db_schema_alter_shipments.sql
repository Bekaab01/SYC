-- ALTER TABLE statements to add shipments table and related structures for transitor functionality

-- Create shipments table
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id VARCHAR(50) UNIQUE NOT NULL,
    transitor_id INT NOT NULL,
    shipper_id INT NOT NULL,
    assigned_carrier_id INT NULL,
    status ENUM('pending', 'assigned', 'in_transit', 'customs_hold', 'delivered', 'cancelled') DEFAULT 'pending',
    -- Origin details
    origin_city VARCHAR(100) NOT NULL,
    origin_country VARCHAR(100) NOT NULL,
    origin_address TEXT,
    -- Destination details
    destination_city VARCHAR(100) NOT NULL,
    destination_country VARCHAR(100) NOT NULL,
    destination_address TEXT,
    -- Cargo details
    cargo_description TEXT,
    weight DECIMAL(10,2),
    volume DECIMAL(10,2),
    dimensions VARCHAR(100),
    cargo_value DECIMAL(15,2),
    -- Schedule
    estimated_departure DATETIME,
    estimated_delivery DATETIME,
    actual_departure DATETIME,
    actual_delivery DATETIME,
    -- Pricing
    agreed_price DECIMAL(15,2),
    currency VARCHAR(3) DEFAULT 'USD',
    -- Additional info
    special_instructions TEXT,
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    -- Tracking
    tracking_number VARCHAR(100) UNIQUE,
    -- Metadata
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (transitor_id) REFERENCES transitors(id) ON DELETE CASCADE,
    FOREIGN KEY (shipper_id) REFERENCES shippers(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_carrier_id) REFERENCES carriers(id) ON DELETE SET NULL
);

-- Create transitor_quotes table
CREATE TABLE IF NOT EXISTS transitor_quotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    transitor_id INT NOT NULL,
    price DECIMAL(15,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    valid_until DATETIME,
    notes TEXT,
    status ENUM('pending', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (transitor_id) REFERENCES transitors(id) ON DELETE CASCADE,
    UNIQUE KEY unique_quote (shipment_id, transitor_id)
);

-- Create shipment_documents table
CREATE TABLE IF NOT EXISTS shipment_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100),
    file_size INT,
    document_type ENUM('invoice', 'packing_list', 'bill_of_lading', 'customs_docs', 'insurance', 'other') DEFAULT 'other',
    visibility ENUM('private', 'carrier', 'public') DEFAULT 'private',
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Create shipment_events table for audit logging
CREATE TABLE IF NOT EXISTS shipment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    user_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    payload JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_shipment_event (shipment_id, event_type),
    INDEX idx_event_time (created_at)
);

-- Add transitor_id to invoices table for transitor payments (if invoices table exists)
SET @sql = (SELECT IF(
    EXISTS(
        SELECT * FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'invoices'
    ),
    'ALTER TABLE invoices ADD COLUMN transitor_id INT NULL AFTER carrier_id, ADD FOREIGN KEY (transitor_id) REFERENCES transitors(id) ON DELETE SET NULL;',
    'SELECT "Invoices table does not exist, skipping ALTER TABLE" as message;'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Create indexes for better performance
CREATE INDEX idx_shipments_transitor ON shipments(transitor_id);
CREATE INDEX idx_shipments_shipper ON shipments(shipper_id);
CREATE INDEX idx_shipments_carrier ON shipments(assigned_carrier_id);
CREATE INDEX idx_shipments_status ON shipments(status);
CREATE INDEX idx_shipments_created ON shipments(created_at);

CREATE INDEX idx_quotes_shipment ON transitor_quotes(shipment_id);
CREATE INDEX idx_quotes_transitor ON transitor_quotes(transitor_id);
CREATE INDEX idx_quotes_status ON transitor_quotes(status);

CREATE INDEX idx_docs_shipment ON shipment_documents(shipment_id);
CREATE INDEX idx_docs_type ON shipment_documents(document_type);
