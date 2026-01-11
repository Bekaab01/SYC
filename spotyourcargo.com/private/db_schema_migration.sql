-- SpotYourCargo Multi-Tenant Database Schema Migration
-- Production-grade schema for logistics SaaS in Ethiopia
-- Single-database, logical isolation with tenant_id (association_id)

-- =====================================================
-- USER & ACCESS MANAGEMENT
-- =====================================================

-- Modify existing users table to add multi-tenancy and audit fields
ALTER TABLE users
ADD COLUMN IF NOT EXISTS tenant_id INT NULL COMMENT 'Association ID for multi-tenancy',
ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL,
ADD INDEX IF NOT EXISTS idx_users_tenant_id (tenant_id),
ADD INDEX IF NOT EXISTS idx_users_phone (phone),
ADD INDEX IF NOT EXISTS idx_users_email (email),
ADD INDEX IF NOT EXISTS idx_users_status (status);

-- Create business_profiles table for legal identity
CREATE TABLE IF NOT EXISTS business_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    tenant_id INT NULL COMMENT 'Association ID for multi-tenancy',
    company_name VARCHAR(255) NOT NULL,
    tin_number VARCHAR(50) UNIQUE,
    is_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_business_profiles_user_id (user_id),
    INDEX idx_business_profiles_tenant_id (tenant_id),
    INDEX idx_business_profiles_tin (tin_number),
    INDEX idx_business_profiles_verified (is_verified),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create associations table (root tenant for Association dashboard)
CREATE TABLE IF NOT EXISTS associations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    union_name VARCHAR(255) NOT NULL,
    license_no VARCHAR(100) UNIQUE,
    commission_rate DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Commission rate as percentage',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_associations_license (license_no),
    INDEX idx_associations_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- UNION HIERARCHY (MEMBERS & TRUCKS)
-- =====================================================

-- Create association_members table (truck owners under a union)
CREATE TABLE IF NOT EXISTS association_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    owner_name VARCHAR(255) NOT NULL,
    bank_account VARCHAR(100),
    phone VARCHAR(20),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_association_members_association_id (association_id),
    INDEX idx_association_members_phone (phone),
    INDEX idx_association_members_status (status),
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create trucks table (physical fleet)
CREATE TABLE IF NOT EXISTS trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    plate_no_truck VARCHAR(20) NOT NULL UNIQUE,
    plate_no_trailer VARCHAR(20),
    type ENUM('truck', 'trailer', 'semi_trailer') DEFAULT 'truck',
    capacity_tons DECIMAL(8,2),
    status ENUM('active', 'maintenance', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_trucks_member_id (member_id),
    INDEX idx_trucks_association_id (association_id),
    INDEX idx_trucks_plate_truck (plate_no_truck),
    INDEX idx_trucks_status (status),
    FOREIGN KEY (member_id) REFERENCES association_members(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create drivers table (drivers assigned to trucks)
CREATE TABLE IF NOT EXISTS drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    name VARCHAR(255) NOT NULL,
    license_no VARCHAR(50) UNIQUE,
    phone VARCHAR(20),
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_drivers_association_id (association_id),
    INDEX idx_drivers_license (license_no),
    INDEX idx_drivers_phone (phone),
    INDEX idx_drivers_status (status),
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SHIPMENT LIFECYCLE
-- =====================================================

-- Create shipments table (job/shipment details)
CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipper_id INT NOT NULL,
    association_id INT NULL COMMENT 'Tenant ID - assigned association',
    origin_geo VARCHAR(255) COMMENT 'Origin location coordinates/address',
    dest_geo VARCHAR(255) COMMENT 'Destination location coordinates/address',
    cargo_type VARCHAR(100),
    weight_tons DECIMAL(8,2),
    value_etb DECIMAL(12,2),
    status ENUM('posted', 'bidding', 'awarded', 'assigned', 'in_transit', 'delivered', 'cancelled') DEFAULT 'posted',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_shipments_shipper_id (shipper_id),
    INDEX idx_shipments_association_id (association_id),
    INDEX idx_shipments_status (status),
    INDEX idx_shipments_created_at (created_at),
    FOREIGN KEY (shipper_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create quotations table (bidding system)
CREATE TABLE IF NOT EXISTS quotations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    price_offered DECIMAL(12,2) NOT NULL,
    currency ENUM('ETB', 'USD') DEFAULT 'ETB',
    status ENUM('pending', 'accepted', 'rejected', 'expired') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_quotations_shipment_id (shipment_id),
    INDEX idx_quotations_association_id (association_id),
    INDEX idx_quotations_status (status),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create assignments table (assigns a truck/driver to a shipment)
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    truck_id INT NOT NULL,
    driver_id INT NOT NULL,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    status ENUM('assigned', 'in_transit', 'delivered', 'cancelled') DEFAULT 'assigned',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_assignments_shipment_id (shipment_id),
    INDEX idx_assignments_truck_id (truck_id),
    INDEX idx_assignments_driver_id (driver_id),
    INDEX idx_assignments_association_id (association_id),
    INDEX idx_assignments_status (status),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (truck_id) REFERENCES trucks(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- FINANCIAL & TAX LOGIC
-- =====================================================

-- Create tax_ledger table (withholding tax tracking)
CREATE TABLE IF NOT EXISTS tax_ledger (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    shipment_id INT NULL,
    tax_type ENUM('withholding', 'vat', 'excise') DEFAULT 'withholding',
    amount DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(5,2),
    paid_at TIMESTAMP NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_tax_ledger_association_id (association_id),
    INDEX idx_tax_ledger_shipment_id (shipment_id),
    INDEX idx_tax_ledger_status (status),
    INDEX idx_tax_ledger_paid_at (paid_at),
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create platform_revenue table (platform fee tracking)
CREATE TABLE IF NOT EXISTS platform_revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    shipment_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    fee_type ENUM('platform_fee', 'commission') DEFAULT 'platform_fee',
    collected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending', 'collected', 'refunded') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_platform_revenue_association_id (association_id),
    INDEX idx_platform_revenue_shipment_id (shipment_id),
    INDEX idx_platform_revenue_status (status),
    INDEX idx_platform_revenue_collected_at (collected_at),
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create association_revenue table (association commission tracking)
CREATE TABLE IF NOT EXISTS association_revenue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    shipment_id INT NULL,
    member_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    revenue_type ENUM('commission', 'payout', 'bonus') DEFAULT 'commission',
    paid_at TIMESTAMP NULL,
    status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_association_revenue_association_id (association_id),
    INDEX idx_association_revenue_shipment_id (shipment_id),
    INDEX idx_association_revenue_member_id (member_id),
    INDEX idx_association_revenue_status (status),
    INDEX idx_association_revenue_paid_at (paid_at),
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE SET NULL,
    FOREIGN KEY (member_id) REFERENCES association_members(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- ADDITIONAL TABLES FOR COMPLETE FUNCTIONALITY
-- =====================================================

-- Create truck_locations table for GPS tracking (Redis cache complement)
CREATE TABLE IF NOT EXISTS truck_locations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    truck_id INT NOT NULL,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    latitude DECIMAL(10,8),
    longitude DECIMAL(11,8),
    speed_kmh DECIMAL(5,2),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_truck_locations_truck_id (truck_id),
    INDEX idx_truck_locations_association_id (association_id),
    INDEX idx_truck_locations_recorded_at (recorded_at),
    FOREIGN KEY (truck_id) REFERENCES trucks(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create shipment_events table for audit trail
CREATE TABLE IF NOT EXISTS shipment_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    shipment_id INT NOT NULL,
    association_id INT NOT NULL COMMENT 'Tenant ID',
    event_type ENUM('posted', 'bid_received', 'awarded', 'assigned', 'departed', 'arrived', 'delivered', 'stalled', 'cancelled') NOT NULL,
    event_data JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_shipment_events_shipment_id (shipment_id),
    INDEX idx_shipment_events_association_id (association_id),
    INDEX idx_shipment_events_type (event_type),
    INDEX idx_shipment_events_created_at (created_at),
    FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create notifications table for user communications
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    association_id INT NULL COMMENT 'Tenant ID',
    title VARCHAR(255) NOT NULL,
    message TEXT,
    type ENUM('info', 'warning', 'success', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_notifications_user_id (user_id),
    INDEX idx_notifications_association_id (association_id),
    INDEX idx_notifications_is_read (is_read),
    INDEX idx_notifications_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- SAMPLE SEED DATA (Optional - uncomment to use)
-- =====================================================

/*
-- Sample association
INSERT INTO associations (union_name, license_no, commission_rate) VALUES
('Addis Ababa Transport Association', 'ATA-001', 5.00);

-- Sample association member
INSERT INTO association_members (association_id, owner_name, bank_account, phone) VALUES
(1, 'Ahmed Mohammed', '1000123456789', '+251911123456');

-- Sample truck
INSERT INTO trucks (member_id, association_id, plate_no_truck, type, capacity_tons) VALUES
(1, 1, 'AA-1234', 'truck', 25.00);

-- Sample driver
INSERT INTO drivers (association_id, name, license_no, phone) VALUES
(1, 'Kassahun Tadesse', 'DRV-001', '+251922654321');
*/
