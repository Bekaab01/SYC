-- Create association_audit table for logging admin actions on associations
CREATE TABLE IF NOT EXISTS association_audit (
    id INT AUTO_INCREMENT PRIMARY KEY,
    association_id INT NOT NULL,
    admin_user_id INT NULL COMMENT 'Admin who performed the action',
    admin_username VARCHAR(255) NULL COMMENT 'Admin username for logging',
    action ENUM('approved', 'rejected', 'viewed', 'commented') NOT NULL,
    old_status ENUM('pending', 'manual_review', 'approved', 'rejected') NULL,
    new_status ENUM('pending', 'manual_review', 'approved', 'rejected') NULL,
    notes TEXT NULL COMMENT 'Additional notes or comments',
    ip_address VARCHAR(45) NULL COMMENT 'IP address of admin',
    user_agent TEXT NULL COMMENT 'Browser/user agent info',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (association_id) REFERENCES associations(id) ON DELETE CASCADE,
    INDEX idx_association_id (association_id),
    INDEX idx_admin_user_id (admin_user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- Add indexes for better performance
CREATE INDEX idx_association_audit_association ON association_audit(association_id);
CREATE INDEX idx_association_audit_admin ON association_audit(admin_user_id);
CREATE INDEX idx_association_audit_action ON association_audit(action);
CREATE INDEX idx_association_audit_created ON association_audit(created_at);
