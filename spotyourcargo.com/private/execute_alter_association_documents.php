<?php
require 'db.php';

try {
    $sql = file_get_contents('db_schema_alter_association_documents.sql');

    if (!$sql) {
        die("Could not read SQL file.\n");
    }

    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
            echo "✓ Success\n";
        }
    }

    echo "\nAll database alterations completed successfully!\n";

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
