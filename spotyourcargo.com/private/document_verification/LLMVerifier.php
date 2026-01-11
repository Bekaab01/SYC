<?php
/**
 * LLM Verifier for Semantic Document Verification
 *
 * This class handles AI-powered semantic verification of extracted document fields
 * using a DeepSeek-like LLM engine to detect inconsistencies, anomalies, and logical errors.
 */

require_once __DIR__ . '/config.php';

class LLMVerifier {
    private $logger;
    private $config;

    public function __construct($logger = null) {
        $this->logger = $logger;
        $this->config = [
            'provider' => DOC_VERIFICATION_LLM_PROVIDER,
            'model' => DOC_VERIFICATION_LLM_MODEL,
            'timeout' => DOC_VERIFICATION_LLM_TIMEOUT,
            'enabled' => DOC_VERIFICATION_ENABLE_LLM
        ];
    }

    /**
     * Verify document fields using LLM semantic analysis
     *
     * @param array $fields Extracted fields from the document
     * @param string $documentType Type of document (e.g., 'Bill of Lading')
     * @param string|null $documentId Optional document ID for logging
     * @return array Verification report
     */
    public function verifyDocument($fields, $documentType, $documentId = null) {
        if (!$this->config['enabled']) {
            return $this->getDisabledResponse($documentId, $documentType);
        }

        $startTime = microtime(true);

        try {
            // Prepare LLM prompt
            $prompt = $this->buildVerificationPrompt($fields, $documentType);

            // Call LLM (mocked for now)
            $llmResponse = $this->callLLM($prompt);

            // Parse and structure the response
            $report = $this->parseLLMResponse($llmResponse, $documentId, $documentType);

            // Log the verification
            $this->logVerification($documentId, $report, microtime(true) - $startTime);

            return $report;

        } catch (Exception $e) {
            $this->logError($documentId, $e->getMessage());
            return $this->getErrorResponse($documentId, $documentType, $e->getMessage());
        }
    }

    /**
     * Build the verification prompt for the LLM
     */
    private function buildVerificationPrompt($fields, $documentType) {
        $fieldData = json_encode($fields, JSON_PRETTY_PRINT);

        return <<<PROMPT
You are an expert document verification AI specializing in logistics documents. Analyze the following extracted fields from a {$documentType} document for semantic consistency, logical errors, and anomalies.

Extracted Fields:
{$fieldData}

Please analyze for:
1. Logical consistency (e.g., dates in correct order, totals matching line items)
2. Plausibility of dates (not in future, reasonable ranges)
3. Pattern matching (e.g., names, addresses, codes matching expected formats)
4. Cross-field relationships (e.g., shipper/consignee consistency)

Provide your analysis in the following JSON format:
{
  "verification_status": "passed|review_required|failed",
  "field_flags": {
    "FieldName": "present|missing|anomalous"
  },
  "notes": {
    "FieldName": "Explanation of any issues or confirmation of validity"
  },
  "confidence": 0.0-1.0,
  "reasoning": "Brief explanation of your overall assessment"
}

Be conservative: flag potential issues for review rather than assuming validity.
PROMPT;
    }

    /**
     * Mock LLM call - simulates DeepSeek API response
     * In production, this would make actual API call to DeepSeek or similar
     */
    private function callLLM($prompt) {
        // Simulate API delay
        usleep(rand(500000, 2000000)); // 0.5-2 seconds

        // Mock response based on document type and fields
        // In real implementation, this would be replaced with actual API call
        return $this->generateMockResponse($prompt);
    }

    /**
     * Generate mock LLM response for testing
     * This simulates what a real LLM would return
     */
    private function generateMockResponse($prompt) {
        // Extract document type from prompt
        if (preg_match('/from a ([^ ]+) document/', $prompt, $matches)) {
            $docType = $matches[1];
        } else {
            $docType = 'Unknown';
        }

        // Parse fields from prompt (simplified)
        $fields = json_decode(substr($prompt, strpos($prompt, '{'), strrpos($prompt, '}') - strpos($prompt, '{') + 1), true) ?: [];

        // Generate realistic mock response
        $response = [
            'verification_status' => 'passed',
            'field_flags' => [],
            'notes' => [],
            'confidence' => 0.85,
            'reasoning' => 'Document appears consistent with standard patterns'
        ];

        // Simulate anomalies based on field content
        foreach ($fields as $field => $value) {
            if (empty($value)) {
                $response['field_flags'][$field] = 'missing';
                $response['notes'][$field] = 'Field is empty or not extracted';
                $response['verification_status'] = 'review_required';
                $response['confidence'] -= 0.1;
            } elseif (strpos(strtolower($field), 'date') !== false) {
                // Check date plausibility
                if ($this->isDateAnomalous($value)) {
                    $response['field_flags'][$field] = 'anomalous';
                    $response['notes'][$field] = 'Date appears implausible or in future';
                    $response['verification_status'] = 'review_required';
                    $response['confidence'] -= 0.15;
                } else {
                    $response['field_flags'][$field] = 'present';
                    $response['notes'][$field] = 'Valid date format and range';
                }
            } elseif (in_array(strtolower($field), ['shipper', 'consignee', 'buyer', 'seller'])) {
                // Simulate name validation
                if (strlen($value) < 3 || !preg_match('/[a-zA-Z]/', $value)) {
                    $response['field_flags'][$field] = 'anomalous';
                    $response['notes'][$field] = 'Name format appears unusual or incomplete';
                    $response['verification_status'] = 'review_required';
                    $response['confidence'] -= 0.1;
                } else {
                    $response['field_flags'][$field] = 'present';
                    $response['notes'][$field] = 'Name format appears valid';
                }
            } elseif (strpos(strtolower($field), 'number') !== false || strpos(strtolower($field), 'id') !== false) {
                // Check number formats
                if (!preg_match('/^[A-Z0-9\-]+$/', $value)) {
                    $response['field_flags'][$field] = 'anomalous';
                    $response['notes'][$field] = 'Identifier format does not match expected patterns';
                    $response['verification_status'] = 'review_required';
                    $response['confidence'] -= 0.05;
                } else {
                    $response['field_flags'][$field] = 'present';
                    $response['notes'][$field] = 'Identifier format appears valid';
                }
            } else {
                $response['field_flags'][$field] = 'present';
                $response['notes'][$field] = 'Field present and formatted appropriately';
            }
        }

        // Adjust confidence bounds
        $response['confidence'] = max(0.1, min(0.95, $response['confidence']));

        return json_encode($response);
    }

    /**
     * Check if a date value appears anomalous
     */
    private function isDateAnomalous($dateString) {
        try {
            $date = new DateTime($dateString);
            $now = new DateTime();
            $futureLimit = clone $now;
            $futureLimit->modify('+1 year');

            // Flag dates more than 1 year in future or more than 10 years in past
            return $date > $futureLimit || $date < $now->modify('-10 years');
        } catch (Exception $e) {
            // Invalid date format
            return true;
        }
    }

    /**
     * Parse LLM response into structured report
     */
    private function parseLLMResponse($llmResponse, $documentId, $documentType) {
        $parsed = json_decode($llmResponse, true);

        if (!$parsed) {
            throw new Exception('Failed to parse LLM response');
        }

        return [
            'document_id' => $documentId ?: 'unknown',
            'document_type' => $documentType,
            'verification_status' => $parsed['verification_status'] ?? 'failed',
            'field_flags' => $parsed['field_flags'] ?? [],
            'notes' => $parsed['notes'] ?? [],
            'confidence' => $parsed['confidence'] ?? 0.0,
            'engine' => 'DeepSeek v1',
            'timestamp' => date('c'), // ISO 8601 format
            'reasoning' => $parsed['reasoning'] ?? 'Analysis completed'
        ];
    }

    /**
     * Response when LLM verification is disabled
     */
    private function getDisabledResponse($documentId, $documentType) {
        return [
            'document_id' => $documentId ?: 'unknown',
            'document_type' => $documentType,
            'verification_status' => 'passed', // Default to passed when disabled
            'field_flags' => [],
            'notes' => ['system' => 'LLM verification disabled'],
            'confidence' => 0.5,
            'engine' => 'DeepSeek v1 (disabled)',
            'timestamp' => date('c')
        ];
    }

    /**
     * Error response
     */
    private function getErrorResponse($documentId, $documentType, $error) {
        return [
            'document_id' => $documentId ?: 'unknown',
            'document_type' => $documentType,
            'verification_status' => 'failed',
            'field_flags' => [],
            'notes' => ['error' => 'Verification failed: ' . $error],
            'confidence' => 0.0,
            'engine' => 'DeepSeek v1',
            'timestamp' => date('c')
        ];
    }

    /**
     * Log verification results
     */
    private function logVerification($documentId, $report, $processingTime) {
        if ($this->logger) {
            $this->logger->info('LLM Verification completed', [
                'document_id' => $documentId,
                'status' => $report['verification_status'],
                'confidence' => $report['confidence'],
                'processing_time_ms' => round($processingTime * 1000, 2)
            ]);
        }
    }

    /**
     * Log errors
     */
    private function logError($documentId, $message) {
        if ($this->logger) {
            $this->logger->error('LLM Verification failed', [
                'document_id' => $documentId,
                'error' => $message
            ]);
        }
    }
}
