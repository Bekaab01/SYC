<?php
include 'db.php';

try {
    // Update load_refs that have status prefixes
    $stmt = $pdo->prepare("UPDATE shipments SET load_ref = CONCAT('SYC-LD-', UPPER(SUBSTRING(MD5(RAND()), 1, 6))) WHERE load_ref LIKE 'SYC-LD-DRAFT-%' OR load_ref LIKE 'SYC-LD-PENDING-%'");
    $stmt->execute();

    echo "Load references updated successfully!\n";

    // Show updated records
    $stmt = $pdo->prepare("SELECT id, load_ref, status FROM shipments WHERE shipper_id = 8 ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "\nUpdated records:\n";
    foreach ($records as $record) {
        echo "ID: {$record['id']}, Load Ref: {$record['load_ref']}, Status: {$record['status']}\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
