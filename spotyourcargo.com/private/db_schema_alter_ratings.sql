-- Alter script for adding carrier rating system

-- Add rating columns to carriers table
ALTER TABLE carriers
ADD COLUMN average_rating DECIMAL(3,2) DEFAULT 0.00,
ADD COLUMN total_ratings INT DEFAULT 0;

-- Create ratings table for carrier reviews
CREATE TABLE IF NOT EXISTS ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    carrier_id INT NOT NULL,
    shipper_id INT NOT NULL, -- FK to shippers.id or users.id depending on structure
    load_id INT, -- Optional FK to cargo.id for context
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    is_approved BOOLEAN DEFAULT TRUE, -- For moderation
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (carrier_id) REFERENCES carriers(id) ON DELETE CASCADE,
    FOREIGN KEY (shipper_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (load_id) REFERENCES cargo(id) ON DELETE SET NULL,
    UNIQUE KEY unique_rating (carrier_id, shipper_id, load_id) -- Prevent duplicate ratings per load
);

-- Optional: Add delivery_status to cargo table if not exists (for rating eligibility)
-- ALTER TABLE cargo ADD COLUMN delivery_status ENUM('pending', 'in_transit', 'delivered') DEFAULT 'pending';
