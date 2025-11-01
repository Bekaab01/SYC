<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('DESCRIBE users');
    $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Users table structure:\n";
    foreach($cols as $col) {
        echo $col['Field'] . ' - ' . $col['Type'] . "\n";
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
