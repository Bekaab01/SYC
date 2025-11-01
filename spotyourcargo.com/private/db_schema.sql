-- SQL schema for carriers and users tables for carrier registration and vetting system

-- Users table (if not exists)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syc_id VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    full_name VARCHAR(200),
    company_name VARCHAR(255),
    company_contact_name VARCHAR(255),
    company_contact_email VARCHAR(255),
    phone VARCHAR(50),
    user_type ENUM('carrier', 'shipper', 'admin') NOT NULL DEFAULT 'shipper',
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Carriers table
CREATE TABLE IF NOT EXISTS carriers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    syc_id VARCHAR(50) UNIQUE NOT NULL,
    carrier_type ENUM('individual', 'company') NOT NULL DEFAULT 'company',
    -- Common fields
    full_name VARCHAR(255),
    email VARCHAR(255) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255),
    -- Individual Owner-Operator fields
    kebele_id VARCHAR(100),
    driving_license_number VARCHAR(100),
    defensive_driving_cert VARCHAR(255), -- file path
    vehicle_plate_number VARCHAR(50),
    vehicle_type VARCHAR(100),
    vehicle_registration_cert VARCHAR(255), -- file path
    vehicle_insurance_cert VARCHAR(255), -- file path
    -- Banking (common)
    bank_name VARCHAR(255),
    account_number VARCHAR(100),
    account_holder_name VARCHAR(255),
    alt_phone VARCHAR(50), -- Alternative phone number
    -- Company/Fleet Operator fields
    legal_business_name VARCHAR(255),
    trade_name VARCHAR(255),
    business_registration_number VARCHAR(100),
    tin_number VARCHAR(100),
    type_of_business VARCHAR(100),
    business_license_upload VARCHAR(255), -- file path
    tin_certificate_upload VARCHAR(255), -- file path
    company_region VARCHAR(100),
    company_city VARCHAR(100),
    company_subcity VARCHAR(100),
    company_kebele VARCHAR(100),
    company_email VARCHAR(255),
    company_phone VARCHAR(50),
    total_trucks INT DEFAULT 0,
    owner_manager_name VARCHAR(255),
    id_passport_upload VARCHAR(255), -- file path
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(50),
    bank_statement_upload VARCHAR(255), -- file path
    safety_compliance_cert VARCHAR(255), -- file path
    cooperative_membership_proof VARCHAR(255), -- file path
    reference_letter_upload VARCHAR(255), -- file path
    -- Legacy fields (for backward compatibility)
    company_name VARCHAR(255),
    company_contact_name VARCHAR(255),
    company_contact_email VARCHAR(255),
    contact_person VARCHAR(255),
    dot_number VARCHAR(50),
    mc_number VARCHAR(50),
    fleet_size INT,
    equipment_types TEXT,
    operating_areas TEXT,
    years_experience INT,
    insurance_provider VARCHAR(255),
    insurance_policy_number VARCHAR(255),
    insurance_expiry DATE,
    business_license VARCHAR(255),
    tax_id VARCHAR(100),
    status ENUM('pending', 'manual_review', 'verified', 'rejected') DEFAULT 'pending',
    remarks TEXT, -- Admin remarks during review
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Carrier Documents table (for file uploads)
CREATE TABLE IF NOT EXISTS carrier_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    document_type VARCHAR(100) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
);

-- Carrier Trucks table (for company fleet trucks)
CREATE TABLE IF NOT EXISTS carrier_trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    plate_number VARCHAR(50) NOT NULL,
    vehicle_type VARCHAR(100),
    ownership_type ENUM('owned', 'leased') DEFAULT 'owned',
    registration_cert_path VARCHAR(255),
    insurance_cert_path VARCHAR(255),
    driver_name VARCHAR(255),
    driver_license_number VARCHAR(100),
    driver_license_path VARCHAR(255),
    defensive_driving_cert_path VARCHAR(255),
    verified BOOLEAN DEFAULT FALSE, -- Verified by admin
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
);

-- Trucks table (legacy, if not exists, for carrier trucks)
CREATE TABLE IF NOT EXISTS trucks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    license_plate VARCHAR(50) NOT NULL,
    truck_model VARCHAR(255) NOT NULL,
    capacity DECIMAL(10,2),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
);

-- Notifications table (if not exists)
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syc_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Bids table (if not exists)
CREATE TABLE IF NOT EXISTS bids (
    id INT AUTO_INCREMENT PRIMARY KEY,
    load_id INT NOT NULL,
    carrier_id INT NOT NULL,
    bid_amount DECIMAL(10,2) NOT NULL,
    bid_notes TEXT,
    status ENUM('pending', 'accepted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
);

-- Messages table (if not exists)
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_syc_id VARCHAR(50) NOT NULL,
    receiver_syc_id VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Invoices table (for carrier payments)
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    load_id INT NOT NULL,
    carrier_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE
);

-- Carrier Vetting table (tracks vetting and approval workflow)
CREATE TABLE IF NOT EXISTS carrier_vetting (
    vetting_id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    checked_by INT NOT NULL, -- FK to admins.admin_id
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    decision ENUM('approved', 'rejected', 'needs_info') NOT NULL,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE,
    FOREIGN KEY (checked_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Admins table (for admin users)
CREATE TABLE IF NOT EXISTS admins (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE, -- Links to users table
    name VARCHAR(255) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    role ENUM('superadmin', 'vetting_officer') DEFAULT 'vetting_officer',
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
