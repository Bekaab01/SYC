<?php
/**
 * Test script for trust scoring functionality
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/TrustScorer.php';

echo "Testing Trust Scoring Module\n";
echo "===========================\n\n";

// Test 1: Disabled scoring
echo "Test 1: Disabled scoring\n";
$scorer = new TrustScorer();
$results = [
    'document_id' => 'test-doc-1',
    'document_type' => 'identity_card',
    'processing_steps' => []
];

$score = $scorer->calculateTrustScore($results);
echo "Disabled result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: OCR only
echo "Test 2: OCR only scoring\n";
$results['processing_steps']['ocr'] = [
    'success' => true,
    'confidence' => 85,
    'extracted_text' => 'Sample OCR text'
];

$score = $scorer->calculateTrustScore($results);
echo "OCR only result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Full pipeline
echo "Test 3: Full pipeline scoring\n";
$results['processing_steps']['field_extraction'] = [
    'success' => true,
    'required_fields' => ['name', 'id_number', 'date_of_birth'],
    'present_fields' => ['name', 'id_number'],
    'warnings' => ['Date format inconsistent']
];

$results['processing_steps']['validation'] = [
    'success' => true,
    'passed' => true,
    'errors' => []
];

$results['processing_steps']['llm_verification'] = [
    'success' => true,
    'confidence' => 92,
    'verified' => true
];

$score = $scorer->calculateTrustScore($results);
echo "Full pipeline result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Different document type
echo "Test 4: Certificate document type\n";
$results['document_type'] = 'certificate';
$score = $scorer->calculateTrustScore($results);
echo "Certificate result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Low quality document
echo "Test 5: Low quality document\n";
$results['processing_steps']['ocr']['confidence'] = 45;
$results['processing_steps']['field_extraction']['present_fields'] = ['name'];
$results['processing_steps']['validation']['passed'] = false;
$results['processing_steps']['validation']['errors'] = ['Invalid format'];
$results['processing_steps']['llm_verification']['confidence'] = 60;

$score = $scorer->calculateTrustScore($results);
echo "Low quality result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 6: Hard failure - OCR failed
echo "Test 6: Hard failure - OCR failed\n";
$hardFailureResults = [
    'document_id' => 'test-hard-failure-ocr',
    'document_type' => 'identity_card',
    'processing_steps' => [
        'ocr' => ['status' => 'failed'],
        'field_extraction' => [
            'success' => true,
            'required_fields' => ['name', 'id_number'],
            'present_fields' => ['name', 'id_number']
        ],
        'validation' => ['passed' => true],
        'llm_verification' => ['status' => 'passed']
    ]
];

$score = $scorer->calculateTrustScore($hardFailureResults);
echo "Hard failure OCR result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 7: Hard failure - Missing required field
echo "Test 7: Hard failure - Missing required field\n";
$hardFailureResults['document_id'] = 'test-hard-failure-field';
$hardFailureResults['processing_steps']['ocr'] = ['success' => true, 'confidence' => 85];
$hardFailureResults['processing_steps']['field_extraction']['present_fields'] = ['name']; // Missing id_number

$score = $scorer->calculateTrustScore($hardFailureResults);
echo "Hard failure field result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 8: Hard failure - LLM critical anomaly
echo "Test 8: Hard failure - LLM critical anomaly\n";
$hardFailureResults['document_id'] = 'test-hard-failure-llm';
$hardFailureResults['processing_steps']['field_extraction']['present_fields'] = ['name', 'id_number'];
$hardFailureResults['processing_steps']['llm_verification'] = [
    'status' => 'passed',
    'critical_anomalies' => ['Document appears forged']
];

$score = $scorer->calculateTrustScore($hardFailureResults);
echo "Hard failure LLM result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 9: Hard failure - Expired document
echo "Test 9: Hard failure - Expired document\n";
$hardFailureResults['document_id'] = 'test-hard-failure-expired';
$hardFailureResults['processing_steps']['llm_verification'] = ['status' => 'passed'];
$hardFailureResults['processing_steps']['validation'] = [
    'passed' => false,
    'expired' => true
];

$score = $scorer->calculateTrustScore($hardFailureResults);
echo "Hard failure expired result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

// Test 10: Hard failure - Document type mismatch
echo "Test 10: Hard failure - Document type mismatch\n";
$hardFailureResults['document_id'] = 'test-hard-failure-mismatch';
$hardFailureResults['processing_steps']['validation'] = [
    'passed' => false,
    'document_type_mismatch' => true
];

$score = $scorer->calculateTrustScore($hardFailureResults);
echo "Hard failure mismatch result: " . json_encode($score, JSON_PRETTY_PRINT) . "\n\n";

echo "Testing completed.\n";

// Test 11: Status-based ceiling enforcement - trusted status
echo "\nTest 11: Status-based ceiling enforcement - trusted status\n";
$trustedResults = [
    'document_id' => 'test-ceiling-trusted',
    'document_type' => 'identity_card',
    'processing_steps' => [
        'ocr' => ['success' => true, 'confidence' => 95],
        'field_extraction' => [
            'success' => true,
            'required_fields' => ['name', 'id_number'],
            'present_fields' => ['name', 'id_number'],
            'warnings' => []
        ],
        'validation' => ['passed' => true],
        'llm_verification' => ['success' => true, 'confidence' => 95]
    ]
];

$score = $scorer->calculateTrustScore($trustedResults);
echo "Trusted status ceiling test: " . json_encode($score, JSON_PRETTY_PRINT) . "\n";
echo "Expected trust_score <= 100: " . ($score['trust_score'] <= 100 ? 'PASS' : 'FAIL') . "\n";

// Test 12: Status-based ceiling enforcement - review_required status
echo "\nTest 12: Status-based ceiling enforcement - review_required status\n";
$reviewResults = [
    'document_id' => 'test-ceiling-review',
    'document_type' => 'identity_card',
    'processing_steps' => [
        'ocr' => ['success' => true, 'confidence' => 75],
        'field_extraction' => [
            'success' => true,
            'required_fields' => ['name', 'id_number'],
            'present_fields' => ['name', 'id_number'],
            'warnings' => []
        ],
        'validation' => ['passed' => true],
        'llm_verification' => ['success' => true, 'confidence' => 75]
    ]
];

$score = $scorer->calculateTrustScore($reviewResults);
echo "Review required ceiling test: " . json_encode($score, JSON_PRETTY_PRINT) . "\n";
echo "Expected trust_score <= 79: " . ($score['trust_score'] <= 79 ? 'PASS' : 'FAIL') . "\n";

// Test 13: Status-based ceiling enforcement - rejected status
echo "\nTest 13: Status-based ceiling enforcement - rejected status\n";
$rejectedResults = [
    'document_id' => 'test-ceiling-rejected',
    'document_type' => 'identity_card',
    'processing_steps' => [
        'ocr' => ['success' => true, 'confidence' => 45],
        'field_extraction' => [
            'success' => true,
            'required_fields' => ['name', 'id_number'],
            'present_fields' => ['name'],
            'warnings' => []
        ],
        'validation' => ['passed' => false],
        'llm_verification' => ['success' => true, 'confidence' => 45]
    ]
];

$score = $scorer->calculateTrustScore($rejectedResults);
echo "Rejected status ceiling test: " . json_encode($score, JSON_PRETTY_PRINT) . "\n";
echo "Expected trust_score = 0: " . ($score['trust_score'] == 0 ? 'PASS' : 'FAIL') . "\n";

// Test 14: Ceiling enforcement with high score that gets capped
echo "\nTest 14: Ceiling enforcement with high score that gets capped\n";
// Force a scenario where calculated score would be high but status is review_required
$highScoreResults = [
    'document_id' => 'test-ceiling-capped',
    'document_type' => 'identity_card',
    'processing_steps' => [
        'ocr' => ['success' => true, 'confidence' => 85], // This should give review_required status
        'field_extraction' => [
            'success' => true,
            'required_fields' => ['name', 'id_number'],
            'present_fields' => ['name', 'id_number'],
            'warnings' => []
        ],
        'validation' => ['passed' => true],
        'llm_verification' => ['success' => true, 'confidence' => 85]
    ]
];

$score = $scorer->calculateTrustScore($highScoreResults);
echo "High score capping test: " . json_encode($score, JSON_PRETTY_PRINT) . "\n";
if (isset($score['ceiling_applied']) && $score['ceiling_applied']) {
    echo "Ceiling applied correctly: PASS\n";
    echo "Original score: {$score['original_score']}, Capped score: {$score['trust_score']}\n";
} else {
    echo "Ceiling should have been applied but wasn't: FAIL\n";
}

echo "\nCeiling enforcement testing completed.\n";
?>
