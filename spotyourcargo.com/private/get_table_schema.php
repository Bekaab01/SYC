<?php
include 'db.php';

try {
    $stmt = $pdo->query('SHOW CREATE TABLE association_trucks');
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $result['Create Table'];
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
