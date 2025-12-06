<?php
include 'db.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'association_trucks'");
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($result) > 0) {
        echo "Table 'association_trucks' exists.\n";
    } else {
        echo "Table 'association_trucks' does not exist.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
