<?php
include_once 'api/auth/db.php';

try {
    // Update shippers table to set shipper_code = syc_id where it's NULL or empty
    $update_stmt = $pdo->prepare("UPDATE shippers SET shipper_code = syc_id WHERE shipper_code IS NULL OR shipper_code = ''");
    $update_stmt->execute();

    $affected_rows = $update_stmt->rowCount();

    echo "Migration completed successfully. Updated $affected_rows rows in shippers table.\n";

    // Verify the update
    $verify_stmt = $pdo->query("SELECT COUNT(*) as total_shippers, SUM(CASE WHEN shipper_code IS NOT NULL AND shipper_code != '' THEN 1 ELSE 0 END) as with_code FROM shippers");
    $stats = $verify_stmt->fetch(PDO::FETCH_ASSOC);

    echo "Total shippers: {$stats['total_shippers']}\n";
    echo "Shippers with code: {$stats['with_code']}\n";

} catch (PDOException $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
}
?>
