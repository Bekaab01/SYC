<?php
include 'db.php';
include 'load_ref_generator.php';

try {
    // Generate proper load refs using the generator
    $draft_load_ref = generateUniqueLoadRef($pdo);
    $pending_load_ref = generateUniqueLoadRef($pdo);

    // Insert a test draft shipment for shipper_id 8
    $stmt = $pdo->prepare("
        INSERT INTO shipments (
            load_ref, shipper_id, origin_geo, dest_geo, cargo_type,
            weight_tons, value_etb, status, is_verified, created_at
        ) VALUES (
            ?, 8, 'Addis Ababa', 'Djibouti', 'Test Cargo',
            10.5, 50000.00, 'Draft', 0, NOW()
        )
    ");

    $stmt->execute([$draft_load_ref]);
    echo "Test draft shipment created successfully with load_ref: $draft_load_ref\n";

    // Also create a pending verification shipment
    $stmt = $pdo->prepare("
        INSERT INTO shipments (
            load_ref, shipper_id, origin_geo, dest_geo, cargo_type,
            weight_tons, value_etb, status, is_verified, created_at
        ) VALUES (
            ?, 8, 'Addis Ababa', 'Dire Dawa', 'Pending Cargo',
            5.0, 25000.00, 'Pending Verification', 0, NOW()
        )
    ");

    $stmt->execute([$pending_load_ref]);
    echo "Test pending verification shipment created successfully with load_ref: $pending_load_ref\n";

    echo "Now check the drafts tab - it should show these test shipments.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
