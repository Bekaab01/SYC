<?php
require 'db.php';

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'verification_logs'");
    if ($stmt->fetch()) {
        echo "verification_logs table exists\n";
    } else {
        echo "verification_logs table does not exist\n";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
