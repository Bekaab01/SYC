<?php
require_once 'db.php';

try {
    echo "Executing associations schema...\n";

    $sql = file_get_contents('db_schema_associations.sql');

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            echo "Executing: " . substr($statement, 0, 50) . "...\n";
            $pdo->exec($statement);
        }
    }

    echo "Schema alterations executed successfully.\n";

    // Verify tables were created
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $expected_tables = ['associations', 'association_documents', 'drivers', 'association_trucks', 'service_requests'];

    echo "\nVerification:\n";
    foreach ($expected_tables as $table) {
        if (in_array($table, $tables)) {
            echo "- $table: CREATED\n";
        } else {
            echo "- $table: MISSING\n";
        }
    }

} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    echo 'SQL State: ' . $e->getCode() . "\n";
}
?>
