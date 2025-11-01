<?php
// Execute association_audit table creation
include_once 'db.php';

try {
    // Read the SQL file
    $sql = file_get_contents('create_association_audit_table.sql');

    // Execute the SQL
    $pdo->exec($sql);

    echo "SUCCESS: association_audit table created successfully!\n";

    // Verify the table was created
    $stmt = $pdo->query("SHOW TABLES LIKE 'association_audit'");
    if ($stmt->rowCount() > 0) {
        echo "VERIFIED: association_audit table exists in database.\n";
    } else {
        echo "ERROR: association_audit table was not created.\n";
    }

} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
?>
