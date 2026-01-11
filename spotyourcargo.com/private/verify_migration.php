<?php
include 'db.php';

try {
    echo "Verifying database migration...\n\n";

    // Check if key tables exist
    $tables = [
        'associations',
        'business_profiles',
        'association_members',
        'trucks',
        'drivers',
        'shipments',
        'quotations',
        'assignments',
        'tax_ledger',
        'platform_revenue',
        'association_revenue',
        'truck_locations',
        'shipment_events',
        'notifications'
    ];

    $existing_tables = 0;
    $missing_tables = 0;

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($exists) {
            echo "✓ Table '$table' exists\n";
            $existing_tables++;
        } else {
            echo "✗ Table '$table' missing\n";
            $missing_tables++;
        }
    }

    echo "\nSummary:\n";
    echo "Existing tables: $existing_tables\n";
    echo "Missing tables: $missing_tables\n";

    if ($missing_tables == 0) {
        echo "\n✅ Migration successful! All tables created.\n";
    } else {
        echo "\n⚠️  Migration incomplete. Some tables are missing.\n";
    }

    // Check if users table has new columns
    echo "\nChecking users table modifications...\n";
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $required_columns = ['tenant_id', 'created_at', 'updated_at', 'deleted_at'];
    foreach ($required_columns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] == $col) {
                $found = true;
                break;
            }
        }
        echo ($found ? "✓" : "✗") . " Column '$col' " . ($found ? "exists" : "missing") . "\n";
    }

} catch (PDOException $e) {
    echo "Error verifying migration: " . $e->getMessage() . "\n";
}
?>
