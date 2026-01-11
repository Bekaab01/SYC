<?php
include 'db.php';

// Open log file for writing
$log_file = 'trust_migration_log.txt';
$log_handle = fopen($log_file, 'w');

function log_message($message) {
    global $log_handle;
    echo $message . "\n";
    fwrite($log_handle, $message . "\n");
}

try {
    log_message("Starting trust management migration at " . date('Y-m-d H:i:s'));

    // Read the migration SQL file
    $migration_sql = file_get_contents(__DIR__ . '/add_trust_management_tables.sql');

    if (!$migration_sql) {
        log_message("Error: Could not read migration file.");
        die("Error: Could not read migration file.\n");
    }

    log_message("Migration file loaded successfully. Size: " . strlen($migration_sql) . " bytes");

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $migration_sql)));

    log_message("Found " . count($statements) . " SQL statements to execute");

    $success_count = 0;
    $error_count = 0;

    foreach ($statements as $index => $statement) {
        if (empty($statement)) continue;

        try {
            $pdo->exec($statement);
            $success_count++;
            log_message("✓ Statement " . ($index + 1) . " executed successfully");
        } catch (PDOException $e) {
            $error_count++;
            log_message("✗ Error executing statement " . ($index + 1) . ": " . $e->getMessage());
            log_message("Statement: " . substr($statement, 0, 200) . "...");
        }
    }

    log_message("\nMigration completed!");
    log_message("Successful statements: $success_count");
    log_message("Failed statements: $error_count");

    if ($error_count == 0) {
        log_message("✅ All migrations applied successfully!");
    } else {
        log_message("⚠️  Some statements failed. Please check the errors above.");
    }

} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage());
} finally {
    fclose($log_handle);
    echo "\nCheck trust_migration_log.txt for detailed results.\n";
}
?>
