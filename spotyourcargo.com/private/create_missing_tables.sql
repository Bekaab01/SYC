-- Create missing tables for shippers and transitors
-- These tables are referenced in access.php but missing from the schema

-- Create shippers table
CREATE TABLE IF NOT EXISTS shippers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    syc_id VARCHAR(20) UNIQUE,
    company_name VARCHAR(255),
    email VARCHAR(255),
    company_contact_name VARCHAR(255),
    company_contact_email VARCHAR(255),
    tin VARCHAR(50),
    business_category VARCHAR(100),
    contact_person VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_shippers_user_id (user_id),
    INDEX idx_shippers_syc_id (syc_id),
    INDEX idx_shippers_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create transitors table
CREATE TABLE IF NOT EXISTS transitors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    syc_id VARCHAR(20) UNIQUE,
    company_name VARCHAR(255),
    email VARCHAR(255),
    company_contact_name VARCHAR(255),
    company_contact_email VARCHAR(255),
    transitor_license VARCHAR(100),
    port_operation VARCHAR(255),
    customs_agency VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_transitors_user_id (user_id),
    INDEX idx_transitors_syc_id (syc_id),
    INDEX idx_transitors_email (email),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
