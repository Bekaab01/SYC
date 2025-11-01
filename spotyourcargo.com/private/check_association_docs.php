<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM association_documents');
    $result = $stmt->fetch();
    echo 'Total association documents: ' . $result['count'] . PHP_EOL;

    if ($result['count'] > 0) {
        $stmt = $pdo->query('SELECT ad.*, a.name as association_name FROM association_documents ad JOIN associations a ON ad.association_id = a.id LIMIT 5');
        $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($docs as $doc) {
            echo 'ID: ' . $doc['id'] . ', Association: ' . $doc['association_name'] . ', Type: ' . $doc['document_type'] . ', File: ' . $doc['original_filename'] . PHP_EOL;
        }
    }
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
