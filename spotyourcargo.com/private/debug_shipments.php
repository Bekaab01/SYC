<?php
include 'db.php';

try {
    // Get table structure
    echo "=== SHIPMENTS TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE shipments');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n=== SAMPLE DATA (Last 5 records) ===\n";
    $stmt = $pdo->query('SELECT id, shipper_id, load_ref, cargo_name, status, is_verified, created_at FROM shipments ORDER BY id DESC LIMIT 5');
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($samples as $sample) {
        echo "ID: {$sample['id']}, Shipper: {$sample['shipper_id']}, Load Ref: {$sample['load_ref']}, Status: {$sample['status']}, Verified: {$sample['is_verified']}, Created: {$sample['created_at']}\n";
    }

    echo "\n=== STATUS BREAKDOWN ===\n";
    $stmt = $pdo->query('SELECT status, COUNT(*) as count FROM shipments GROUP BY status ORDER BY count DESC');
    $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($statuses as $status) {
        echo "{$status['status']}: {$status['count']} records\n";
    }

    echo "\n=== RECORDS FOR SHIPPER_ID 8 ===\n";
    $stmt = $pdo->prepare('SELECT id, load_ref, cargo_name, status, is_verified, created_at FROM shipments WHERE shipper_id = ? ORDER BY created_at DESC');
    $stmt->execute([8]);
    $shipper_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($shipper_records)) {
        echo "No records found for shipper_id 8\n";
    } else {
        foreach ($shipper_records as $record) {
            echo "ID: {$record['id']}, Load Ref: {$record['load_ref']}, Status: {$record['status']}, Verified: {$record['is_verified']}, Created: {$record['created_at']}\n";
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
