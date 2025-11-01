<?php
require_once 'db.php';

try {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    $pdo->exec('DROP TABLE IF EXISTS carriers, carrier_trucks, carrier_vetting, carrier_documents');
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    echo 'Carrier tables dropped successfully';
} catch (PDOException $e) {
    echo 'Error dropping tables: ' . $e->getMessage();
}
?>
