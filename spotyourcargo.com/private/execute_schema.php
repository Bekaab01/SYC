<?php
require_once 'db.php';

try {
    $sql = file_get_contents('db_schema_alter_shipments.sql');
    $pdo->exec($sql);
    echo 'Schema alterations executed successfully.';
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
