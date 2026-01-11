<?php
include_once 'spotyourcargo.com/private/db.php';

try {
    // Fix duplicate paths in association_documents
    $stmt = $pdo->prepare("UPDATE association_documents SET stored_path = REPLACE(stored_path, 'private/uploads/private/uploads/', 'private/uploads/') WHERE stored_path LIKE 'private/uploads/private/uploads/%'");
    $stmt->execute();
    echo "Fixed duplicate paths in association_documents\n";

    // Fix duplicate paths in shipper_documents
    $stmt = $pdo->prepare("UPDATE shipper_documents SET stored_path = REPLACE(stored_path, 'private/uploads/private/uploads/', 'private/uploads/') WHERE stored_path LIKE 'private/uploads/private/uploads/%'");
    $stmt->execute();
    echo "Fixed duplicate paths in shipper_documents\n";

    echo "Duplicate path fixes completed successfully.\n";
} catch (Exception $e) {
    echo "Error fixing duplicate paths: " . $e->getMessage() . "\n";
}
?>
