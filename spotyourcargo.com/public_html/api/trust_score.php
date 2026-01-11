<?php
/**
 * Trust Score API Endpoint
 *
 * Returns the calculated trust score for a shipment based on uploaded documents.
 * Uses the DocumentVerificationService to process and score documents.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

require_once '../../private/db.php';
require_once '../../private/session_config.php';
require_once '../../private/document_verification/config.php';
require_once '../../private/document_verification/TrustScorer.php';
require_once '../../private/document_verification/DocumentVerificationService.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$shipment_id = $_GET['shipment_id'] ?? null;

if (!$shipment_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Shipment ID required']);
    exit;
}

// Verify shipment belongs to user
try {
    $stmt = $pdo->prepare("SELECT id FROM shipments WHERE id = ? AND shipper_id = ?");
    $stmt->execute([$shipment_id, $user_id]);
    if (!$stmt->fetch()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
} catch (PDOException $e) {
    error_log("Shipment verification error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}

// Get all documents for this shipment
try {
    $stmt = $pdo->prepare("
        SELECT
            ld.document_id,
            ld.doc_type as document_type,
            ld.uploaded_at,
            ld.original_filename,
            ld.stored_filename,
            ld.file_size,
            ld.mime_type,
            ld.uploader_id,
            -- Include processing results if available
            COALESCE(ld.ocr_result, '{}') as ocr_result,
            COALESCE(ld.field_extraction_result, '{}') as field_extraction_result,
            COALESCE(ld.validation_result, '{}') as validation_result,
            COALESCE(ld.llm_verification_result, '{}') as llm_verification_result,
            COALESCE(ld.trust_score, 0) as individual_trust_score
        FROM load_documents ld
        WHERE ld.shipment_id = ?
        ORDER BY ld.uploaded_at ASC
    ");
    $stmt->execute([$shipment_id]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Document retrieval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error retrieving documents']);
    exit;
}

// If no documents, return 0 trust score
if (empty($documents)) {
    echo json_encode([
        'success' => true,
        'trust_score' => 0,
        'status' => 'no_documents',
        'notes' => [
            ['type' => 'info', 'message' => 'No documents have been uploaded yet. Upload required documents to improve your trust score.']
        ],
        'message' => 'No documents uploaded for this shipment'
    ]);
    exit;
}

// Process documents and calculate trust score
try {
    $trustScorer = new TrustScorer();
    $verificationService = new DocumentVerificationService();

    // Convert database results to processing result format
    $documentResults = [];
    foreach ($documents as $doc) {
        $processingResult = [
            'document_id' => $doc['document_id'],
            'document_type' => $doc['document_type'],
            'shipment_id' => $shipment_id,
            'processing_steps' => []
        ];

        // Add OCR results if available
        if (!empty($doc['ocr_result'])) {
            $ocrData = json_decode($doc['ocr_result'], true);
            if ($ocrData && isset($ocrData['success']) && $ocrData['success']) {
                $processingResult['processing_steps']['ocr'] = [
                    'success' => true,
                    'confidence' => $ocrData['confidence'] ?? 0,
                    'text' => $ocrData['text'] ?? ''
                ];
            }
        }

        // Add field extraction results if available
        if (!empty($doc['field_extraction_result'])) {
            $fieldData = json_decode($doc['field_extraction_result'], true);
            if ($fieldData && isset($fieldData['success'])) {
                $processingResult['processing_steps']['field_extraction'] = $fieldData;
            }
        }

        // Add validation results if available
        if (!empty($doc['validation_result'])) {
            $validationData = json_decode($doc['validation_result'], true);
            if ($validationData) {
                $processingResult['processing_steps']['validation'] = $validationData;
            }
        }

        // Add LLM verification results if available
        if (!empty($doc['llm_verification_result'])) {
            $llmData = json_decode($doc['llm_verification_result'], true);
            if ($llmData) {
                $processingResult['processing_steps']['llm_verification'] = $llmData;
            }
        }

        $documentResults[] = $processingResult;
    }

    // Calculate trust score using evidence-weighted approach for multiple documents
    $scoringResult = $trustScorer->calculateTrustScore($documentResults);

    // Return the trust score with detailed information
    echo json_encode([
        'success' => true,
        'trust_score' => $scoringResult['trust_score'] ?? 0,
        'status' => $scoringResult['status'] ?? 'unknown',
        'document_count' => count($documents),
        'notes' => $scoringResult['notes'] ?? [],
        'message' => 'Trust score calculated successfully'
    ]);

} catch (Exception $e) {
    error_log("Trust score calculation error: " . $e->getMessage());
    // Return a default trust score on error rather than failing completely
    echo json_encode([
        'success' => true,
        'trust_score' => 0,
        'message' => 'Error calculating trust score, defaulting to 0'
    ]);
}
?>
