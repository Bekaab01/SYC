<?php
require_once '../private/db.php';

if ($pdo) {
    try {
        $stmt = $pdo->query('DESCRIBE users');
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo 'Users table columns:' . PHP_EOL;
        foreach ($columns as $col) {
            echo '- ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
        }
    } catch (Exception $e) {
        echo 'Error: ' . $e->getMessage();
    }
} else {
    echo 'Database connection failed';
}
?>
