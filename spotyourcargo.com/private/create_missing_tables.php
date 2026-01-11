<?php
include 'db.php';

// Open log file for writing
$log_file = 'create_missing_tables_log.txt';
$log_handle = fopen($log_file, 'w');

function log_message($message) {
    global $log_handle;
    echo $message . "\n";
    fwrite($log_handle, $message . "\n");
}

try {
    log_message("Starting missing tables creation at " . date('Y-m-d H:i:s'));

    // Read the SQL file
    $sql_file = file_get_contents(__DIR__ . '/create_missing_tables.sql');

    if (!$sql_file) {
        log_message("Error: Could not read create_missing_tables.sql file.");
        die("Error: Could not read create_missing_tables.sql file.\n");
    }

    log_message("SQL file loaded successfully. Size: " . strlen($sql_file) . " bytes");

    // Split the SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql_file)));

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

    log_message("\nTable creation completed!");
    log_message("Successful statements: $success_count");
    log_message("Failed statements: $error_count");

    if ($error_count == 0) {
        log_message("✅ All missing tables created successfully!");
    } else {
        log_message("⚠️  Some statements failed. Please check the errors above.");
    }

} catch (Exception $e) {
    log_message("Fatal error: " . $e->getMessage());
} finally {
    fclose($log_handle);
    echo "\nCheck create_missing_tables_log.txt for detailed results.\n";
}
?>
