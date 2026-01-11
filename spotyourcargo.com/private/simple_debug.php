<?php
include 'db.php';

echo "=== CHECKING SHIPPER_ID 8 RECORDS ===\n";

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shipments WHERE shipper_id = ?");
    $stmt->execute([8]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total shipments for shipper_id 8: " . $result['total'] . "\n";

    $stmt = $pdo->prepare("SELECT id, load_ref, status, is_verified, created_at FROM shipments WHERE shipper_id = ? ORDER BY created_at DESC");
    $stmt->execute([8]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($records)) {
        echo "No records found for shipper_id 8\n";
    } else {
        foreach ($records as $record) {
            echo "ID: {$record['id']}, Load Ref: {$record['load_ref']}, Status: {$record['status']}, Verified: {$record['is_verified']}, Created: {$record['created_at']}\n";
        }
    }

    echo "\n=== CHECKING DRAFT/VERIFICATION QUERY ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) as draft_count FROM shipments WHERE shipper_id = ? AND status IN ('Draft','Pending Verification')");
    $stmt->execute([8]);
    $draft_result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Draft/Pending Verification count for shipper_id 8: " . $draft_result['draft_count'] . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
