<?php
include 'db.php';

try {
    $stmt = $pdo->query('DESCRIBE shipments');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Shipments table columns:\n";
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }

    // Check if load_ref column exists
    $loadRefExists = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'load_ref') {
            $loadRefExists = true;
            break;
        }
    }

    if (!$loadRefExists) {
        echo "\nload_ref column is MISSING! Adding it...\n";

        $pdo->exec("ALTER TABLE shipments ADD COLUMN load_ref VARCHAR(20) UNIQUE AFTER id");
        echo "load_ref column added successfully!\n";
    } else {
        echo "\nload_ref column exists.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
