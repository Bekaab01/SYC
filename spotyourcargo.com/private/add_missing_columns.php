<?php
require_once '../private/db.php';

if ($pdo) {
    try {
        // Check which columns already exist
        $stmt = $pdo->query("DESCRIBE users");
        $existingColumns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $existingColumns[] = $row['Field'];
        }

        // Define columns to add with their definitions
        $columnsToAdd = [
            'syc_id' => "ALTER TABLE users ADD COLUMN syc_id VARCHAR(20) UNIQUE COMMENT 'Spot Your Cargo unique identifier' AFTER id",
            'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(100) AFTER syc_id",
            'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(100) AFTER first_name",
            'full_name' => "ALTER TABLE users ADD COLUMN full_name VARCHAR(255) AFTER last_name",
            'username' => "ALTER TABLE users ADD COLUMN username VARCHAR(255) UNIQUE AFTER full_name",
            'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT FALSE AFTER tenant_id",
            'verification_token' => "ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) AFTER email_verified",
            'verified_at' => "ALTER TABLE users ADD COLUMN verified_at TIMESTAMP NULL AFTER verification_token",
            'company_name' => "ALTER TABLE users ADD COLUMN company_name VARCHAR(255) AFTER verified_at",
            'company_contact_name' => "ALTER TABLE users ADD COLUMN company_contact_name VARCHAR(255) AFTER company_name",
            'company_contact_email' => "ALTER TABLE users ADD COLUMN company_contact_email VARCHAR(255) AFTER company_contact_name"
        ];

        // Add missing columns
        foreach ($columnsToAdd as $columnName => $query) {
            if (!in_array($columnName, $existingColumns)) {
                $pdo->exec($query);
                echo 'Added column: ' . $columnName . PHP_EOL;
            } else {
                echo 'Column already exists: ' . $columnName . PHP_EOL;
            }
        }

        // Add indexes if they don't exist
        $indexesToAdd = [
            'idx_users_syc_id' => "ALTER TABLE users ADD INDEX idx_users_syc_id (syc_id)",
            'idx_users_email_verified' => "ALTER TABLE users ADD INDEX idx_users_email_verified (email_verified)"
        ];

        foreach ($indexesToAdd as $indexName => $query) {
            try {
                $pdo->exec($query);
                echo 'Added index: ' . $indexName . PHP_EOL;
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                    echo 'Error adding index ' . $indexName . ': ' . $e->getMessage() . PHP_EOL;
                } else {
                    echo 'Index already exists: ' . $indexName . PHP_EOL;
                }
            }
        }

        echo 'Script completed successfully!' . PHP_EOL;
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo 'Database connection failed';
}
?>
