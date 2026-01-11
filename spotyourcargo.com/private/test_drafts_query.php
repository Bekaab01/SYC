<?php
include '../public_html/db.php';

try {
    // Simulate shipper_id from session
    $shipper_id = 8;

    echo "=== TESTING DRAFTS QUERY FOR SHIPPER_ID: $shipper_id ===\n\n";

    $stmt = $pdo->prepare("
        SELECT s.id, s.load_ref, s.cargo_name, s.origin_geo as origin, s.dest_geo as destination,
               s.status, s.is_verified, s.is_boosted,
               (SELECT COUNT(*) FROM load_documents ld WHERE ld.shipment_id = s.id) AS docs_count,
               (SELECT COUNT(*) FROM shipment_views sv WHERE sv.shipment_id = s.id) AS views_count,
               (SELECT COUNT(*) FROM quotations q WHERE q.shipment_id = s.id) AS bids_count,
               COALESCE((
                   SELECT SUM(
                       CASE
                           WHEN doc_type = 'invoice' AND verification_status = 'verified' THEN 40
                           WHEN doc_type = 'packing_list' AND verification_status = 'verified' THEN 30
                           WHEN doc_type = 'warehouse_release' AND verification_status = 'verified' THEN 30
                           ELSE 0
                       END
                   ) FROM load_documents WHERE shipment_id = s.id
               ),0) AS trust_score,
               COALESCE((
                   SELECT COUNT(
                       CASE
                           WHEN doc_type IN ('invoice','packing_list','warehouse_release')
                           AND verification_status = 'verified'
                           THEN 1
                       END
                   ) FROM load_documents WHERE shipment_id = s.id
               ),0) AS verified_docs,
               (SELECT COUNT(*) FROM load_documents WHERE shipment_id = s.id AND doc_type = 'insurance') > 0 AS has_insurance
        FROM shipments s
        WHERE s.shipper_id = ? AND s.status IN ('Draft','Pending Verification')
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$shipper_id]);
    $loads = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Query executed successfully. Found " . count($loads) . " loads\n\n";

    if (!empty($loads)) {
        echo "Load details:\n";
        foreach ($loads as $load) {
            echo "- ID: {$load['id']}, Load Ref: {$load['load_ref']}, Status: {$load['status']}, Origin: '{$load['origin']}', Destination: '{$load['destination']}', Trust Score: {$load['trust_score']}%\n";
        }
    } else {
        echo "No loads found!\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
