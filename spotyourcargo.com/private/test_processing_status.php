<?php
include 'db.php';

try {
    echo "Testing processing status columns in load_documents table...\n\n";

    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE load_documents");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $required_columns = ['ocr_status', 'verification_status', 'ocr_confidence', 'trust_score'];
    $found_columns = [];

    echo "Table structure:\n";
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']}\n";
        if (in_array($column['Field'], $required_columns)) {
            $found_columns[] = $column['Field'];
        }
    }

    echo "\nRequired columns check:\n";
    foreach ($required_columns as $col) {
        if (in_array($col, $found_columns)) {
            echo "✅ $col - FOUND\n";
        } else {
            echo "❌ $col - MISSING\n";
        }
    }

    // Test fetch_documents query (simulate what happens in shipper-dashboard.php)
    echo "\nTesting fetch_documents query...\n";

    // First, let's see if there are any test shipments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM shipments WHERE status = 'Draft'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "Draft shipments count: {$result['count']}\n";

    if ($result['count'] > 0) {
        // Get first draft shipment
        $stmt = $pdo->query("SELECT id FROM shipments WHERE status = 'Draft' LIMIT 1");
        $shipment = $stmt->fetch(PDO::FETCH_ASSOC);
        $shipment_id = $shipment['id'];

        echo "Testing with shipment ID: $shipment_id\n";

        // Test the fetch_documents query
        $stmt = $pdo->prepare("
            SELECT
                doc_type,
                uploaded_at,
                COALESCE(ocr_status, 'pending') as ocr_status,
                COALESCE(ocr_confidence, 0) as ocr_confidence,
                COALESCE(verification_status, 'pending') as verification_status,
                COALESCE(trust_score, 0) as trust_score,
                COALESCE(ocr_result, '{}') as ocr_result,
                COALESCE(field_extraction_result, '{}') as field_extraction_result,
                COALESCE(validation_result, '{}') as validation_result,
                COALESCE(llm_verification_result, '{}') as llm_verification_result
            FROM load_documents
            WHERE shipment_id = ?
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([$shipment_id]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "Documents found: " . count($documents) . "\n";

        if (count($documents) > 0) {
            echo "Sample document data:\n";
            $doc = $documents[0];
            echo "- doc_type: {$doc['doc_type']}\n";
            echo "- ocr_status: {$doc['ocr_status']}\n";
            echo "- verification_status: {$doc['verification_status']}\n";
            echo "- ocr_confidence: {$doc['ocr_confidence']}%\n";
            echo "- trust_score: {$doc['trust_score']}%\n";
        }
    } else {
        echo "No draft shipments found for testing.\n";
        echo "Consider creating a test draft shipment with documents to fully test the functionality.\n";
    }

    echo "\n✅ Processing status column test completed!\n";

} catch (Exception $e) {
    echo "❌ Test failed: " . $e->getMessage() . "\n";
}
?>
