<?php
/**
 * Test script for LLMVerifier semantic verification
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/LLMVerifier.php';

// Mock logger for testing
class MockLogger {
    public function info($message, $context = []) {
        echo "[INFO] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }

    public function error($message, $context = []) {
        echo "[ERROR] $message\n";
        if (!empty($context)) {
            echo "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
    }
}

function testLLMVerifier() {
    echo "=== Testing LLMVerifier Class ===\n\n";

    $logger = new MockLogger();
    $verifier = new LLMVerifier($logger);

    // Test 1: Valid Bill of Lading fields
    echo "Test 1: Valid Bill of Lading fields\n";
    $fields1 = [
        'BL Number' => 'ABC123456789',
        'Shipper' => 'Global Shipping Co Ltd',
        'Consignee' => 'Import Corp Inc',
        'Vessel Name' => 'Ever Green',
        'Sailing Date' => '2024-01-15',
        'Issue Date' => '2024-01-10'
    ];

    $result1 = $verifier->verifyDocument($fields1, 'Bill of Lading', 'test-doc-001');
    echo "Result: " . json_encode($result1, JSON_PRETTY_PRINT) . "\n\n";

    // Test 2: Anomalous fields (future date, invalid name)
    echo "Test 2: Anomalous fields\n";
    $fields2 = [
        'BL Number' => 'XYZ999',
        'Shipper' => 'A', // Too short
        'Consignee' => 'Valid Company Name',
        'Vessel Name' => 'Ocean Voyager',
        'Sailing Date' => '2025-12-31', // Future date
        'Issue Date' => '2024-01-01'
    ];

    $result2 = $verifier->verifyDocument($fields2, 'Bill of Lading', 'test-doc-002');
    echo "Result: " . json_encode($result2, JSON_PRETTY_PRINT) . "\n\n";

    // Test 3: Missing fields
    echo "Test 3: Missing fields\n";
    $fields3 = [
        'BL Number' => '',
        'Shipper' => null,
        'Consignee' => 'Some Company',
        'Vessel Name' => 'Test Ship'
    ];

    $result3 = $verifier->verifyDocument($fields3, 'Bill of Lading', 'test-doc-003');
    echo "Result: " . json_encode($result3, JSON_PRETTY_PRINT) . "\n\n";

    // Test 4: Commercial Invoice fields
    echo "Test 4: Commercial Invoice fields\n";
    $fields4 = [
        'Invoice Number' => 'INV-2024-001',
        'Seller' => 'Export Company Ltd',
        'Buyer' => 'Import LLC',
        'Total Amount' => '15000.00',
        'Currency' => 'USD',
        'Issue Date' => '2024-02-01'
    ];

    $result4 = $verifier->verifyDocument($fields4, 'Commercial Invoice', 'test-doc-004');
    echo "Result: " . json_encode($result4, JSON_PRETTY_PRINT) . "\n\n";

    // Test 5: Error handling - invalid JSON in mock
    echo "Test 5: Error handling\n";
    try {
        // This should handle errors gracefully
        $result5 = $verifier->verifyDocument([], 'Unknown Type', 'test-doc-005');
        echo "Result: " . json_encode($result5, JSON_PRETTY_PRINT) . "\n\n";
    } catch (Exception $e) {
        echo "Exception caught: " . $e->getMessage() . "\n\n";
    }

    echo "=== Testing Complete ===\n";
}

// Run the tests
testLLMVerifier();
?>
