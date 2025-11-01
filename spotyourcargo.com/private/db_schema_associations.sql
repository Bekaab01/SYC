-- Database schema for SYC Association System
-- This creates tables for associations, trucks, drivers, and related functionality

-- Add 'association' to user_type ENUM in users table
ALTER TABLE users MODIFY COLUMN user_type ENUM('carrier', 'shipper', 'transitor', 'admin', 'association') NOT NULL DEFAULT 'shipper';

-- Create associations table
CREATE TABLE IF NOT EXISTS associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    syc_id VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) NOT NULL,
    region VARCHAR(100) NOT NULL,
    contact_person VARCHAR(255) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    email VARCHAR(255) NOT NULL,
    number_of_trucks INT DEFAULT 0,
    registration_status ENUM('pending', 'manual_review', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_syc_id (syc_id),
    INDEX idx_status (registration_status)
);

-- Create association_documents table for file uploads
CREATE TABLE IF NOT EXISTS association_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL,
    document_type ENUM('business_license', 'tin_certificate', 'association_membership') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    INDEX idx_association_id (association_id),
    INDEX idx_document_type (document_type)
);

-- Create drivers table
CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    license_number VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    experience_years INT DEFAULT 0,
    status ENUM('available', 'assigned', 'on-trip') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    INDEX idx_association_id (association_id),
    INDEX idx_status (status),
    INDEX idx_license (license_number)
);

-- Create trucks table (separate from carrier_trucks for associations)
CREATE TABLE IF NOT EXISTS association_trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL,
    plate_number VARCHAR(50) NOT NULL UNIQUE,
    truck_type ENUM('flatbed', 'refrigerated', 'container', 'tanker', 'dump', 'box') NOT NULL,
    capacity DECIMAL(8,2) NOT NULL COMMENT 'Capacity in tons',
    driver_id INT NULL,
    status ENUM('active', 'inactive', 'maintenance', 'on-trip') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    INDEX idx_association_id (association_id),
    INDEX idx_driver_id (driver_id),
    INDEX idx_status (status),
    INDEX idx_plate_number (plate_number)
);

-- Create service_requests table for association assignments
-- This extends the existing shipments system to include associations
CREATE TABLE IF NOT EXISTS service_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NULL COMMENT 'Reference to shipments table if applicable',
    cargo_owner_id INT NOT NULL COMMENT 'Shipper or Transitor ID',
    transitor_id INT NULL,
    association_id INT NULL,
    truck_id INT NULL,
    driver_id INT NULL,
    -- Cargo details
    cargo_description TEXT,
    cargo_weight DECIMAL(10,2),
    origin VARCHAR(255),
    destination VARCHAR(255),
    -- Status and dates
    delivery_status ENUM('pending', 'accepted', 'in transit', 'delivered', 'cancelled') DEFAULT 'pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    accepted_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    -- Pricing
    agreed_price DECIMAL(15,2) NULL,
    currency VARCHAR(3) DEFAULT 'ETB',
    -- Notes
    special_instructions TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (cargo_owner_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (transitor_id) REFERENCES transitors(id) ON DELETE SET NULL,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL,
    FOREIGN KEY (truck_id) REFERENCES association_trucks(id) ON DELETE SET NULL,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    INDEX idx_association_id (association_id),
    INDEX idx_delivery_status (delivery_status),
    INDEX idx_created_at (created_at)
);

-- Create indexes for better performance
CREATE INDEX idx_associations_user_id ON associations(user_id);
CREATE INDEX idx_associations_syc_id ON associations(syc_id);
CREATE INDEX idx_associations_status ON associations(registration_status);

CREATE INDEX idx_drivers_association_id ON drivers(association_id);
CREATE INDEX idx_drivers_status ON drivers(status);

CREATE INDEX idx_trucks_association_id ON association_trucks(association_id);
CREATE INDEX idx_trucks_driver_id ON association_trucks(driver_id);
CREATE INDEX idx_trucks_status ON association_trucks(status);

CREATE INDEX idx_service_requests_association ON service_requests(association_id);
CREATE INDEX idx_service_requests_status ON service_requests(delivery_status);
