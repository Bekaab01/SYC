<?php
include 'db.php';

try {
    // Simulate shipper_id from session (replace with actual session value)
    $shipper_id = 8; // Change this to the actual shipper_id you're testing with

    echo "=== DEBUGGING DRAFTS FOR SHIPPER_ID: $shipper_id ===\n\n";

    // Check total shipments for this shipper
    $check_stmt = $pdo->prepare("SELECT COUNT(*) as total FROM shipments WHERE shipper_id = ?");
    $check_stmt->execute([$shipper_id]);
    $total_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
    echo "Total shipments for this shipper: " . $total_result['total'] . "\n\n";

    // Check shipments by status
    $status_stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM shipments WHERE shipper_id = ? GROUP BY status ORDER BY count DESC");
    $status_stmt->execute([$shipper_id]);
    $status_results = $status_stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Status breakdown:\n";
    foreach ($status_results as $status) {
        echo "- {$status['status']}: {$status['count']} records\n";
    }
    echo "\n";

    // Check if there are any Draft or Pending Verification shipments
    $draft_stmt = $pdo->prepare("SELECT COUNT(*) as draft_count FROM shipments WHERE shipper_id = ? AND status IN ('Draft','Pending Verification')");
    $draft_stmt->execute([$shipper_id]);
    $draft_result = $draft_stmt->fetch(PDO::FETCH_ASSOC);
    echo "Draft/Pending Verification shipments: " . $draft_result['draft_count'] . "\n\n";

    if ($draft_result['draft_count'] > 0) {
        // Show the actual draft shipments
        echo "=== DRAFT SHIPMENTS DETAILS ===\n";
        $details_stmt = $pdo->prepare("
            SELECT id, load_ref, cargo_name, origin, destination, origin_geo, dest_geo, status, is_verified, created_at
            FROM shipments
            WHERE shipper_id = ? AND status IN ('Draft','Pending Verification')
            ORDER BY created_at DESC
        ");
        $details_stmt->execute([$shipper_id]);
        $drafts = $details_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($drafts as $draft) {
            echo "ID: {$draft['id']}, Load Ref: {$draft['load_ref']}, Status: {$draft['status']}\n";
            echo "  Origin: '{$draft['origin']}', Destination: '{$draft['destination']}'\n";
            echo "  Origin_geo: '{$draft['origin_geo']}', Dest_geo: '{$draft['dest_geo']}'\n";
            echo "  Created: {$draft['created_at']}\n\n";
        }
    } else {
        echo "No draft or pending verification shipments found for this shipper.\n";
    }

    // Check table structure
    echo "\n=== TABLE STRUCTURE ===\n";
    $stmt = $pdo->query('DESCRIBE shipments');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
