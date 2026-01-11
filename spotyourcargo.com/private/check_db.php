<?php
include 'db.php';

try {
    echo "Checking current database state...\n\n";

    // Check if database exists and get all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Current tables in database:\n";
    if (empty($tables)) {
        echo "No tables found.\n";
    } else {
        foreach ($tables as $table) {
            echo "- $table\n";
        }
    }

    echo "\nDatabase connection successful.\n";

} catch (PDOException $e) {
    echo "Database connection error: " . $e->getMessage() . "\n";
    echo "Please ensure:\n";
    echo "1. XAMPP MySQL service is running\n";
    echo "2. Database 'syc_db' exists\n";
    echo "3. MySQL credentials are correct\n";
}
?>
