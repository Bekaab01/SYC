<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('SELECT id, name, registration_status FROM associations WHERE user_id = 43');
    $assoc = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($assoc) {
        echo 'Association ID: ' . $assoc['id'] . PHP_EOL;
        echo 'Name: ' . $assoc['name'] . PHP_EOL;
        echo 'Status: ' . $assoc['registration_status'] . PHP_EOL;
    } else {
        echo 'No association found for user_id 43';
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
