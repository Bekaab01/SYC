<?php
include 'db.php';

try {
    $stmt = $pdo->query("DESCRIBE association_trucks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns in association_trucks:\n";
    foreach ($columns as $column) {
        echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
    }

    // Check for foreign key constraints
    $stmt = $pdo->query("SELECT CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
                         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
                         WHERE TABLE_NAME = 'association_trucks' AND TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL");
    $fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($fks) > 0) {
        echo "\nForeign keys:\n";
        foreach ($fks as $fk) {
            echo "- " . $fk['CONSTRAINT_NAME'] . ": " . $fk['COLUMN_NAME'] . " -> " . $fk['REFERENCED_TABLE_NAME'] . "." . $fk['REFERENCED_COLUMN_NAME'] . "\n";
        }
    } else {
        echo "\nNo foreign keys found.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
