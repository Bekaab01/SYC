<?php
/**
 * Test script for FieldExtractor functionality
 */

require_once __DIR__ . '/FieldExtractor.php';

// Sample OCR text for different document types
$sampleData = [
    'Bill of Lading' => [
        'ocr_text' => "BILL OF LADING\nBL: ABC123456\nSHIPPER: ABC Shipping Corp\n123 Main St, City, State\nCONSIGNEE: XYZ Import Ltd\n456 Trade Ave, Port City\nVESSEL: MV Ocean Express\nSAILING DATE: 15/12/2023\nNOTIFY PARTY: Local Agent\nPort of Loading: Shanghai\nPort of Discharge: Rotterdam\nCARGO: 100 cartons of electronics\nGROSS WEIGHT: 5000 KGS",
        'expected_fields' => ['BL Number', 'Shipper', 'Consignee', 'Vessel Name', 'Sailing Date']
    ],
    'Commercial Invoice' => [
        'ocr_text' => "COMMERCIAL INVOICE\nINVOICE NO: INV-2023-001\nSELLER: Tech Corp Ltd\n123 Business Rd, Tech City\nBUYER: Import Solutions\n456 Commerce St, Trade City\nTOTAL AMOUNT: 50000.00\nCURRENCY: USD\nDATE: 01/12/2023\nPAYMENT TERMS: 30 days\nINCOTERMS: FOB",
        'expected_fields' => ['Invoice Number', 'Seller', 'Buyer', 'Total Amount', 'Currency', 'Issue Date']
    ],
    'Business License / ID' => [
        'ocr_text' => "BUSINESS LICENSE\nLICENSE NUMBER: BL-2023-789\nCOMPANY NAME: Global Trade Solutions Ltd\nISSUE DATE: 01/01/2023\nEXPIRATION DATE: 31/12/2025\nISSUING AUTHORITY: Department of Commerce\nADDRESS: 789 Business Park, Corporate City\nCONTACT: +1-555-0123\nBUSINESS TYPE: Import/Export",
        'expected_fields' => ['License Number', 'Company Name', 'Issue Date', 'Expiration Date', 'Issuing Authority']
    ],
    'Packing List' => [
        'ocr_text' => "PACKING LIST\nSHIPMENT NO: SH-2023-456\nDESCRIPTION: Electronic Components\nQUANTITY: 1000 pcs\nWEIGHT: 250 KGS\nDIMENSIONS: 120x80x60 cm\nMARKS: ABC-001 to ABC-100\nPACKAGING TYPE: Cartons\nTOTAL PACKAGES: 10\nNET WEIGHT: 225 KGS\nVOLUME: 0.576 CBM",
        'expected_fields' => ['Shipment Number', 'Description of Goods', 'Quantity', 'Weight', 'Dimensions']
    ],
    'Certificate of Origin' => [
        'ocr_text' => "CERTIFICATE OF ORIGIN\nCERTIFICATE NO: CO-2023-789\nEXPORTER: Manufacturing Corp\n123 Factory Rd, Industrial City\nIMPORTER: Distribution Ltd\n456 Warehouse St, Trade City\nCOUNTRY OF ORIGIN: China\nISSUE DATE: 15/11/2023\nDESCRIPTION: Machinery Parts\nHS CODE: 847990\nDECLARATION: The undersigned declares that the goods are of Chinese origin",
        'expected_fields' => ['Certificate Number', 'Exporter', 'Importer', 'Country of Origin', 'Issue Date']
    ],
    'Insurance Certificate' => [
        'ocr_text' => "INSURANCE CERTIFICATE\nPOLICY NO: IC-2023-123\nINSURED: ABC Shipping Corp\nINSURER: Global Insurance Co\nCOVERAGE AMOUNT: 100000 USD\nVALIDITY PERIOD: 01/12/2023 - 31/12/2023\nGOODS DESCRIPTION: Electronics\nVOYAGE: Shanghai to Rotterdam\nCLAIMS CONTACT: claims@globalinsurance.com\nDEDUCTIBLE: 1000 USD\nPREMIUM: 2500 USD",
        'expected_fields' => ['Policy Number', 'Insured', 'Insurer', 'Coverage Amount', 'Validity Period']
    ]
];

function runTests() {
    $extractor = new FieldExtractor();
    $results = [];

    echo "=== Field Extraction Testing ===\n\n";

    foreach ($sampleData as $docType => $data) {
        echo "Testing: $docType\n";
        echo str_repeat("-", 50) . "\n";

        try {
            $result = $extractor->extractFields($data['ocr_text'], $docType);

            // Check if all expected required fields are present
            $allRequiredPresent = true;
            $missingFields = [];

            foreach ($data['expected_fields'] as $field) {
                if (empty($result['extracted_fields'][$field])) {
                    $allRequiredPresent = false;
                    $missingFields[] = $field;
                }
            }

            $results[$docType] = [
                'status' => $allRequiredPresent ? 'PASS' : 'FAIL',
                'extraction_status' => $result['extraction_status'],
                'missing_fields' => $missingFields,
                'extracted_count' => count(array_filter($result['extracted_fields'])),
                'total_fields' => count($result['extracted_fields'])
            ];

            echo "Status: " . $results[$docType]['status'] . "\n";
            echo "Extraction Status: " . $result['extraction_status'] . "\n";
            echo "Fields Extracted: " . $results[$docType]['extracted_count'] . "/" . $results[$docType]['total_fields'] . "\n";

            if (!empty($missingFields)) {
                echo "Missing Required Fields: " . implode(', ', $missingFields) . "\n";
            }

            echo "Sample Extracted Fields:\n";
            foreach (array_slice($result['extracted_fields'], 0, 3, true) as $field => $value) {
                echo "  $field: " . (empty($value) ? '[NOT FOUND]' : substr($value, 0, 50) . (strlen($value) > 50 ? '...' : '')) . "\n";
            }

        } catch (Exception $e) {
            $results[$docType] = [
                'status' => 'ERROR',
                'error' => $e->getMessage()
            ];
            echo "ERROR: " . $e->getMessage() . "\n";
        }

        echo "\n";
    }

    // Test error handling
    echo "Testing Error Handling:\n";
    echo str_repeat("-", 30) . "\n";

    try {
        $errorResult = $extractor->extractFields("Some random text", "Unknown Document Type");
        echo "Unknown Document Type Test: " . ($errorResult['extraction_status'] === 'error' ? 'PASS' : 'FAIL') . "\n";
    } catch (Exception $e) {
        echo "Unknown Document Type Test: PASS (Exception caught: " . $e->getMessage() . ")\n";
    }

    // Summary
    echo "\n=== Test Summary ===\n";
    $passed = 0;
    $total = count($results);

    foreach ($results as $docType => $result) {
        if ($result['status'] === 'PASS') {
            $passed++;
        }
        echo "$docType: " . $result['status'];
        if ($result['status'] === 'FAIL' && isset($result['missing_fields'])) {
            echo " (Missing: " . implode(', ', $result['missing_fields']) . ")";
        }
        echo "\n";
    }

    echo "\nOverall: $passed/$total tests passed\n";

    return $passed === $total;
}

if (runTests()) {
    echo "\n✅ All tests passed!\n";
    exit(0);
} else {
    echo "\n❌ Some tests failed. Please review the implementation.\n";
    exit(1);
}
?>
