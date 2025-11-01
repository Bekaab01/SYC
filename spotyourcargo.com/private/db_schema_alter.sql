-- ALTER TABLE statements to add missing columns to carriers table for updated carrier registration

-- Add carrier_type column
ALTER TABLE carriers ADD COLUMN carrier_type ENUM('individual', 'company') NOT NULL DEFAULT 'company' AFTER user_id;

-- Add common fields
ALTER TABLE carriers ADD COLUMN full_name VARCHAR(255) AFTER carrier_type;
ALTER TABLE carriers ADD COLUMN password_hash VARCHAR(255) AFTER full_name;

-- Add individual owner-operator fields
ALTER TABLE carriers ADD COLUMN kebele_id VARCHAR(100) AFTER password_hash;
ALTER TABLE carriers ADD COLUMN driving_license_number VARCHAR(100) AFTER kebele_id;
ALTER TABLE carriers ADD COLUMN defensive_driving_cert VARCHAR(255) AFTER driving_license_number;
ALTER TABLE carriers ADD COLUMN vehicle_plate_number VARCHAR(50) AFTER defensive_driving_cert;
ALTER TABLE carriers ADD COLUMN vehicle_type VARCHAR(100) AFTER vehicle_plate_number;
ALTER TABLE carriers ADD COLUMN vehicle_registration_cert VARCHAR(255) AFTER vehicle_type;
ALTER TABLE carriers ADD COLUMN vehicle_insurance_cert VARCHAR(255) AFTER vehicle_registration_cert;

-- Banking fields (common)
ALTER TABLE carriers ADD COLUMN bank_name VARCHAR(255) AFTER vehicle_insurance_cert;
ALTER TABLE carriers ADD COLUMN account_number VARCHAR(100) AFTER bank_name;
ALTER TABLE carriers ADD COLUMN account_holder_name VARCHAR(255) AFTER account_number;

-- Company/Fleet Operator fields
ALTER TABLE carriers ADD COLUMN legal_business_name VARCHAR(255) AFTER account_holder_name;
ALTER TABLE carriers ADD COLUMN trade_name VARCHAR(255) AFTER legal_business_name;
ALTER TABLE carriers ADD COLUMN business_registration_number VARCHAR(100) AFTER trade_name;
ALTER TABLE carriers ADD COLUMN tin_number VARCHAR(100) AFTER business_registration_number;
ALTER TABLE carriers ADD COLUMN type_of_business VARCHAR(100) AFTER tin_number;
ALTER TABLE carriers ADD COLUMN business_license_upload VARCHAR(255) AFTER type_of_business;
ALTER TABLE carriers ADD COLUMN tin_certificate_upload VARCHAR(255) AFTER business_license_upload;
ALTER TABLE carriers ADD COLUMN company_region VARCHAR(100) AFTER tin_certificate_upload;
ALTER TABLE carriers ADD COLUMN company_city VARCHAR(100) AFTER company_region;
ALTER TABLE carriers ADD COLUMN company_subcity VARCHAR(100) AFTER company_city;
ALTER TABLE carriers ADD COLUMN company_kebele VARCHAR(100) AFTER company_subcity;
ALTER TABLE carriers ADD COLUMN company_email VARCHAR(255) AFTER company_kebele;
ALTER TABLE carriers ADD COLUMN company_phone VARCHAR(50) AFTER company_email;
ALTER TABLE carriers ADD COLUMN total_trucks INT DEFAULT 0 AFTER company_phone;
ALTER TABLE carriers ADD COLUMN owner_manager_name VARCHAR(255) AFTER total_trucks;
ALTER TABLE carriers ADD COLUMN id_passport_upload VARCHAR(255) AFTER owner_manager_name;
ALTER TABLE carriers ADD COLUMN bank_statement_upload VARCHAR(255) AFTER id_passport_upload;
ALTER TABLE carriers ADD COLUMN safety_compliance_cert VARCHAR(255) AFTER bank_statement_upload;
ALTER TABLE carriers ADD COLUMN cooperative_membership_proof VARCHAR(255) AFTER safety_compliance_cert;
ALTER TABLE carriers ADD COLUMN reference_letter_upload VARCHAR(255) AFTER cooperative_membership_proof;

-- Add alt_phone column (for individual carriers)
ALTER TABLE carriers ADD COLUMN alt_phone VARCHAR(50) AFTER phone;

-- Testimonials table for client reviews
CREATE TABLE IF NOT EXISTS testimonials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    position VARCHAR(255),
    review TEXT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
