<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM associations');
    $result = $stmt->fetch();
    echo 'Total associations: ' . $result['count'] . PHP_EOL;

    $stmt = $pdo->query('SELECT registration_status, COUNT(*) as count FROM associations GROUP BY registration_status');
    $statuses = $stmt->fetchAll();
    foreach ($statuses as $status) {
        echo $status['registration_status'] . ': ' . $status['count'] . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
