<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "Available tables:\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }

    echo "\nChecking for associations table: ";
    if (in_array('associations', $tables)) {
        echo "EXISTS\n";

        // Check table structure
        $stmt = $pdo->query('DESCRIBE associations');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Table structure:\n";
        foreach ($columns as $column) {
            echo "- {$column['Field']}: {$column['Type']} ({$column['Null']})\n";
        }
    } else {
        echo "DOES NOT EXIST\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
