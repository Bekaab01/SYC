<?php
include 'db.php';

try {
    $stmt = $pdo->query('SELECT id FROM drivers LIMIT 1');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($result) {
        echo 'Drivers table exists and has data.';
    } else {
        echo 'Drivers table exists but is empty.';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
