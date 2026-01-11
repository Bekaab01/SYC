-- Create roles table for role-first authentication
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert the three commercial roles
INSERT INTO roles (name, display_name, description) VALUES
('shipper', 'Shipper', 'Commercial shipper role for logistics operations'),
('association', 'Association', 'Transport association role for fleet management'),
('transitor', 'Transitor', 'Transitor role for customs and port operations')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name), description = VALUES(description);

-- Add role_id column to users table if it doesn't exist
ALTER TABLE users ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER user_type;

-- Create index for performance
CREATE INDEX IF NOT EXISTS idx_users_role_id ON users(role_id);

-- Add foreign key constraint
ALTER TABLE users ADD CONSTRAINT fk_users_role_id FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL;

-- Update existing users to have role_id based on user_type
UPDATE users SET role_id = (SELECT id FROM roles WHERE name = users.user_type) WHERE role_id IS NULL AND user_type IN ('shipper', 'association', 'transitor');
