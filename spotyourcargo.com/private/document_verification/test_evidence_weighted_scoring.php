<?php
/**
 * Test Evidence-Weighted Trust Scoring
 *
 * Tests the new evidence-weighted trust scoring functionality with diminishing returns,
 * conflict detection, and quality dominance rules.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/TrustScorer.php';

// Initialize TrustScorer
$scorer = new TrustScorer();

echo "=== Evidence-Weighted Trust Scoring Tests ===\n\n";

// Test 1: Single document (existing functionality)
echo "Test 1: Single Document Scoring\n";
$singleDocResult = [
    'document_id' => 'doc_001',
    'document_type' => 'passport',
    'trust_score' => 85,
    'processing_steps' => [
        'ocr' => ['success' => true, 'confidence' => 90],
        'field_extraction' => ['success' => true, 'present_fields' => ['name', 'number'], 'required_fields' => ['name', 'number']],
        'validation' => ['passed' => true],
        'llm_verification' => ['confidence' => 85]
    ]
];

$singleScore = $scorer->calculateTrustScore($singleDocResult);
echo "Single document score: " . json_encode($singleScore, JSON_PRETTY_PRINT) . "\n\n";

// Test 2: Multiple documents with high confidence
echo "Test 2: Multiple High-Confidence Documents\n";
$highConfidenceDocs = [
    [
        'document_id' => 'doc_002',
        'document_type' => 'passport',
        'trust_score' => 90,
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 95],
            'field_extraction' => ['success' => true, 'present_fields' => ['name', 'number', 'expiry'], 'required_fields' => ['name', 'number', 'expiry']],
            'validation' => ['passed' => true],
            'llm_verification' => ['confidence' => 90]
        ]
    ],
    [
        'document_id' => 'doc_003',
        'document_type' => 'drivers_license',
        'trust_score' => 85,
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 88],
            'field_extraction' => ['success' => true, 'present_fields' => ['name', 'number'], 'required_fields' => ['name', 'number']],
            'validation' => ['passed' => true],
            'llm_verification' => ['confidence' => 82]
        ]
    ]
];

$multiScore = $scorer->calculateTrustScore($highConfidenceDocs);
echo "Multiple high-confidence documents score: " . json_encode($multiScore, JSON_PRETTY_PRINT) . "\n\n";

// Test 3: Multiple documents with diminishing returns
echo "Test 3: Diminishing Returns with Many Documents\n";
$manyDocs = [
    ['document_id' => 'doc_004', 'document_type' => 'passport', 'trust_score' => 90],
    ['document_id' => 'doc_005', 'document_type' => 'drivers_license', 'trust_score' => 85],
    ['document_id' => 'doc_006', 'document_type' => 'certificate', 'trust_score' => 80],
    ['document_id' => 'doc_007', 'document_type' => 'invoice', 'trust_score' => 75],
    ['document_id' => 'doc_008', 'document_type' => 'contract', 'trust_score' => 70]
];

$diminishingScore = $scorer->calculateTrustScore($manyDocs);
echo "Diminishing returns score: " . json_encode($diminishingScore, JSON_PRETTY_PRINT) . "\n\n";

// Test 4: Documents with conflicts
echo "Test 4: Documents with Semantic Conflicts\n";
$conflictingDocs = [
    [
        'document_id' => 'doc_009',
        'document_type' => 'passport',
        'trust_score' => 85,
        'processing_steps' => [
            'field_extraction' => ['extracted_fields' => ['name' => 'John Doe', 'number' => 'P123456']]
        ]
    ],
    [
        'document_id' => 'doc_010',
        'document_type' => 'drivers_license',
        'trust_score' => 80,
        'processing_steps' => [
            'field_extraction' => ['extracted_fields' => ['name' => 'Jane Smith', 'number' => 'D789012']]
        ]
    ]
];

$conflictScore = $scorer->calculateTrustScore($conflictingDocs);
echo "Conflicting documents score: " . json_encode($conflictScore, JSON_PRETTY_PRINT) . "\n\n";

// Test 5: Low confidence documents
echo "Test 5: Low Confidence Documents\n";
$lowConfidenceDocs = [
    [
        'document_id' => 'doc_011',
        'document_type' => 'passport',
        'trust_score' => 45, // Below threshold
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 50],
            'field_extraction' => ['success' => true, 'present_fields' => ['name'], 'required_fields' => ['name', 'number']],
            'validation' => ['passed' => false],
            'llm_verification' => ['confidence' => 40]
        ]
    ],
    [
        'document_id' => 'doc_012',
        'document_type' => 'certificate',
        'trust_score' => 55, // Above threshold
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 65],
            'field_extraction' => ['success' => true, 'present_fields' => ['title', 'issuer'], 'required_fields' => ['title', 'issuer']],
            'validation' => ['passed' => true],
            'llm_verification' => ['confidence' => 60]
        ]
    ]
];

$lowConfidenceScore = $scorer->calculateTrustScore($lowConfidenceDocs);
echo "Low confidence documents score: " . json_encode($lowConfidenceScore, JSON_PRETTY_PRINT) . "\n\n";

// Test 6: Quality dominance with required documents
echo "Test 6: Quality Dominance Rule\n";
$qualityDominanceDocs = [
    [
        'document_id' => 'doc_013',
        'document_type' => 'passport', // Required document type
        'trust_score' => 40, // Low score
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 45],
            'field_extraction' => ['success' => true, 'present_fields' => ['name'], 'required_fields' => ['name', 'number']],
            'validation' => ['passed' => false],
            'llm_verification' => ['confidence' => 35]
        ]
    ],
    [
        'document_id' => 'doc_014',
        'document_type' => 'certificate', // Optional document type
        'trust_score' => 95, // High score
        'processing_steps' => [
            'ocr' => ['success' => true, 'confidence' => 98],
            'field_extraction' => ['success' => true, 'present_fields' => ['title', 'issuer', 'date'], 'required_fields' => ['title', 'issuer', 'date']],
            'validation' => ['passed' => true],
            'llm_verification' => ['confidence' => 95]
        ]
    ]
];

$qualityDominanceScore = $scorer->calculateTrustScore($qualityDominanceDocs);
echo "Quality dominance score: " . json_encode($qualityDominanceScore, JSON_PRETTY_PRINT) . "\n\n";

echo "=== Test Summary ===\n";
echo "All evidence-weighted trust scoring tests completed.\n";
echo "Key features tested:\n";
echo "- Single document scoring (backward compatibility)\n";
echo "- Multiple document aggregation\n";
echo "- Diminishing returns for additional documents\n";
echo "- Confidence threshold weighting\n";
echo "- Semantic conflict detection and penalties\n";
echo "- Quality dominance rules for required documents\n";
?>
