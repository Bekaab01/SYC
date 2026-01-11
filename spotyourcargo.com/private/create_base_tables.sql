-- Create base tables first (users table and roles)
-- This must be run before the main migration

-- Create roles table if it doesn't exist
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert basic roles
INSERT IGNORE INTO roles (name, display_name, description) VALUES
('shipper', 'Shipper', 'Commercial shipper role for logistics operations'),
('association', 'Association', 'Transport association role for fleet management'),
('transitor', 'Transitor', 'Transitor role for customs and port operations'),
('admin', 'Administrator', 'System administrator');

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    syc_id VARCHAR(20) UNIQUE COMMENT 'Spot Your Cargo unique identifier',
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    full_name VARCHAR(255),
    username VARCHAR(255) UNIQUE,
    phone VARCHAR(20) UNIQUE,
    email VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NULL,
    user_type ENUM('shipper', 'association', 'transitor', 'admin') DEFAULT 'shipper',
    status ENUM('active', 'inactive', 'suspended', 'pending') DEFAULT 'active',
    tenant_id INT NULL COMMENT 'Association ID for multi-tenancy',
    email_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    verified_at TIMESTAMP NULL,
    company_name VARCHAR(255),
    company_contact_name VARCHAR(255),
    company_contact_email VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_users_syc_id (syc_id),
    INDEX idx_users_phone (phone),
    INDEX idx_users_email (email),
    INDEX idx_users_role_id (role_id),
    INDEX idx_users_tenant_id (tenant_id),
    INDEX idx_users_status (status),
    INDEX idx_users_email_verified (email_verified),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Update existing users to have role_id based on user_type
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = users.user_type) WHERE role_id IS NULL AND user_type IN ('shipper', 'association', 'transitor', 'admin');
