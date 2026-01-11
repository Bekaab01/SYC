<?php
include_once 'spotyourcargo.com/private/db.php';

try {
    // Update association_documents
    $stmt = $pdo->prepare("UPDATE association_documents SET stored_path = CONCAT('private/uploads/', stored_path) WHERE stored_path IS NOT NULL AND stored_path NOT LIKE 'private/uploads/%'");
    $stmt->execute();
    echo "Updated association_documents\n";

    // Update shipper_documents
    $stmt = $pdo->prepare("UPDATE shipper_documents SET stored_path = CONCAT('private/uploads/', stored_path) WHERE stored_path IS NOT NULL AND stored_path NOT LIKE 'private/uploads/%'");
    $stmt->execute();
    echo "Updated shipper_documents\n";

    echo "Path updates completed successfully.\n";
} catch (Exception $e) {
    echo "Error updating paths: " . $e->getMessage() . "\n";
}
?>
