<?php
include 'db.php';

try {
    $stmt = $pdo->query('DESCRIBE load_documents');
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo 'load_documents table columns:' . PHP_EOL;
    foreach ($columns as $col) {
        echo '- ' . $col['Field'] . ' (' . $col['Type'] . ')' . PHP_EOL;
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . PHP_EOL;
}
?>
