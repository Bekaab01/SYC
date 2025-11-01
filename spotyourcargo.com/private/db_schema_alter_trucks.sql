-- ALTER TABLE statements to add missing columns to trucks table for shipper dashboard available trucks feature

-- Add current_location column
ALTER TABLE trucks ADD COLUMN current_location VARCHAR(255) DEFAULT NULL;

-- Add operating_route column
ALTER TABLE trucks ADD COLUMN operating_route VARCHAR(255) DEFAULT NULL;

-- Add available_from column (datetime for when the truck is available)
ALTER TABLE trucks ADD COLUMN available_from DATETIME DEFAULT NULL;

-- Add special_features column (text for special truck features)
ALTER TABLE trucks ADD COLUMN special_features TEXT DEFAULT NULL;

-- Add truck_type column (e.g., flatbed, refrigerated, etc.)
ALTER TABLE trucks ADD COLUMN truck_type VARCHAR(100) DEFAULT NULL;
