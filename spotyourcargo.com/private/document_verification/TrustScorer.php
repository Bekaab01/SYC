<?php
/**
 * Trust Scorer
 *
 * Calculates trust scores for documents based on multiple factors.
 * Combines deterministic validation results with optional AI verification.
 * Currently contains only stub functions.
 */

require_once __DIR__ . '/config.php';

class TrustScorer
{
    /**
     * Check for hard failures that categorically block trust
     *
     * @param array $verificationResults Processing results from verification pipeline
     * @return array Hard failure check results
     */
    public function checkHardFailures(array $verificationResults): array
    {
        $failures = [];
        $processingSteps = $verificationResults['processing_steps'] ?? [];

        // 1. OCR Failures
        $ocrResult = $processingSteps['ocr'] ?? null;
        if ($ocrResult) {
            if (isset($ocrResult['status']) && $ocrResult['status'] === 'failed') {
                $failures[] = 'OCR processing failed';
            } elseif (!isset($ocrResult['confidence']) || !is_numeric($ocrResult['confidence'])) {
                $failures[] = 'OCR confidence missing or invalid';
            }
        } else {
            $failures[] = 'OCR processing not performed';
        }

        // 2. Required Field Failures
        $fieldExtractionResult = $processingSteps['field_extraction'] ?? null;
        if ($fieldExtractionResult) {
            if (isset($fieldExtractionResult['success']) && !$fieldExtractionResult['success']) {
                $failures[] = 'Field extraction failed';
            }

            $requiredFields = $fieldExtractionResult['required_fields'] ?? [];
            $presentFields = $fieldExtractionResult['present_fields'] ?? [];
            $missingFields = array_diff($requiredFields, $presentFields);

            if (!empty($missingFields)) {
                $failures[] = 'Missing required fields: ' . implode(', ', $missingFields);
            }
        } else {
            $failures[] = 'Field extraction not performed';
        }

        // 3. Semantic Verification Failures
        $llmResult = $processingSteps['llm_verification'] ?? null;
        if ($llmResult) {
            if (isset($llmResult['status']) && $llmResult['status'] === 'failed') {
                $failures[] = 'LLM verification failed';
            }

            if (isset($llmResult['critical_anomalies']) && !empty($llmResult['critical_anomalies'])) {
                $failures[] = 'Critical semantic anomalies detected';
            }
        }

        // 4. Document Validity Failures
        $validationResult = $processingSteps['validation'] ?? null;
        if ($validationResult) {
            if (isset($validationResult['document_type_mismatch']) && $validationResult['document_type_mismatch']) {
                $failures[] = 'Document type mismatch';
            }

            if (isset($validationResult['expired']) && $validationResult['expired']) {
                $failures[] = 'Document expired';
            }

            if (isset($validationResult['invalid_dates']) && $validationResult['invalid_dates']) {
                $failures[] = 'Invalid issue/expiry date logic';
            }
        }

        return [
            'hasFailures' => !empty($failures),
            'failures' => $failures
        ];
    }

    /**
     * Calculate trust score for a document or set of documents
     *
     * @param array $results Processing results from verification pipeline (single doc) or array of results (multiple docs)
     * @return array Trust score calculation
     */
    public function calculateTrustScore($results)
    {
        if (!DOC_VERIFICATION_ENABLE_SCORING) {
            return [
                'enabled' => false,
                'score' => null,
                'message' => 'Trust scoring is disabled',
                'factors' => []
            ];
        }

        // Check if this is a single document or multiple documents
        if (isset($results['document_id'])) {
            // Single document - use existing logic
            return $this->calculateSingleDocumentTrustScore($results);
        } elseif (is_array($results) && !empty($results) && isset($results[0]['document_id'])) {
            // Multiple documents - use evidence-weighted aggregation
            return $this->calculateEvidenceWeightedTrustScore($results);
        } else {
            // Invalid input
            return [
                'enabled' => true,
                'success' => false,
                'error' => 'Invalid input format for trust scoring',
                'trust_score' => 0,
                'status' => 'rejected'
            ];
        }
    }

    /**
     * Calculate trust score for a single document (existing logic)
     *
     * @param array $results Processing results from verification pipeline
     * @return array Trust score calculation
     */
    private function calculateSingleDocumentTrustScore($results)
    {
        $documentId = $results['document_id'] ?? null;
        $processingSteps = $results['processing_steps'] ?? [];

        // Check for hard failures first - these categorically block trust
        $hardFailures = $this->checkHardFailures($results);

        if ($hardFailures['hasFailures']) {
            // Log hard failures
            $this->logHardFailures($documentId, $hardFailures['failures']);

            return [
                'enabled' => true,
                'success' => false,
                'document_id' => $documentId,
                'document_type' => $results['document_type'] ?? 'unknown',
                'trust_score' => 0, // Hard failure caps at 0
                'status' => 'rejected',
                'hard_failures' => $hardFailures['failures'],
                'notes' => ['Hard failures detected: ' . implode('; ', $hardFailures['failures'])],
                'timestamp' => date('c'),
                'factors' => [],
                'weights' => [],
                'thresholds' => [
                    'trusted' => DOC_VERIFICATION_HIGH_TRUST_SCORE,
                    'review_required' => DOC_VERIFICATION_MIN_TRUST_SCORE,
                    'rejected' => 0
                ]
            ];
        }

        // Extract results from processing steps
        $ocrResult = $processingSteps['ocr'] ?? null;
        $fieldExtractionResult = $processingSteps['field_extraction'] ?? null;
        $validationResult = $processingSteps['validation'] ?? null;
        $llmResult = $processingSteps['llm_verification'] ?? null;

        // Get document type for weighting
        $documentType = $results['document_type'] ?? 'unknown';

        // Calculate individual scores
        $ocrScore = $this->calculateOCRScore($ocrResult);
        $fieldScore = $this->calculateFieldScore($fieldExtractionResult);
        $validationScore = $this->calculateValidationScore($validationResult);
        $llmScore = $this->calculateLLMScore($llmResult);
        $metadataScore = $this->calculateMetadataScore($ocrResult);

        // Get weights for this document type
        $weights = $this->getScoringWeights($documentType);

        // Calculate weighted total score
        $totalScore = (
            ($ocrScore * ($weights['ocr_confidence'] ?? 25)) +
            ($fieldScore * ($weights['field_extraction'] ?? 30)) +
            ($validationScore * ($weights['validation_passed'] ?? 25)) +
            ($llmScore * ($weights['llm_verification'] ?? 15)) +
            ($metadataScore * ($weights['metadata_consistency'] ?? 5))
        ) / 100;

        // Ensure score is within 0-100 range
        $totalScore = max(0, min(100, round($totalScore, 1)));

        // Apply time-based decay if document date is available
        $documentDate = $results['document_date'] ?? null;
        if (!$documentDate) {
            // Try to get from validation result
            $validationResult = $processingSteps['validation'] ?? null;
            $documentDate = $validationResult['issue_date'] ?? $validationResult['created_date'] ?? null;
        }

        $decayApplied = false;
        $decayFactor = 1.0;
        $daysOld = 0;
        if ($documentDate) {
            $decayResult = $this->applyTimeDecay($totalScore, $documentDate, $documentType, ['document_id' => $documentId]);
            $totalScore = $decayResult['adjusted_score'];
            $decayApplied = $decayResult['decay_applied'];
            $decayFactor = $decayResult['decay_factor'];
            $daysOld = $decayResult['days_old'];
        }

        // Ensure score is within 0-100 range after decay
        $totalScore = max(0, min(100, round($totalScore, 1)));

        // Determine status based on thresholds
        $status = $this->determineStatus($totalScore);

        // Generate notes
        $notes = $this->generateNotes($ocrResult, $fieldExtractionResult, $validationResult, $llmResult, $totalScore);
        if ($decayApplied) {
            $notes[] = sprintf("Time decay applied: factor=%.4f, days_old=%.1f", $decayFactor, $daysOld);
        }

        // Log the scoring attempt
        $this->logScoringAttempt($documentId, $totalScore, $status);

        // Build initial result
        $result = [
            'enabled' => true,
            'success' => true,
            'document_id' => $documentId,
            'document_type' => $documentType,
            'trust_score' => $totalScore,
            'status' => $status,
            'notes' => $notes,
            'timestamp' => date('c'),
            'factors' => [
                'ocr_score' => $ocrScore,
                'field_score' => $fieldScore,
                'validation_score' => $validationScore,
                'llm_score' => $llmScore,
                'metadata_score' => $metadataScore
            ],
            'weights' => $weights,
            'thresholds' => [
                'trusted' => DOC_VERIFICATION_HIGH_TRUST_SCORE,
                'review_required' => DOC_VERIFICATION_MIN_TRUST_SCORE,
                'rejected' => 0
            ]
        ];

        // Apply status-based ceiling enforcement
        $result = $this->applyStatusBasedCeiling($result);

        return $result;
    }

    /**
     * Calculate evidence-weighted trust score for multiple documents
     *
     * @param array $documentResults Array of document processing results
     * @return array Aggregated trust score calculation
     */
    public function calculateEvidenceWeightedTrustScore(array $documentResults): array
    {
        if (empty($documentResults)) {
            return [
                'enabled' => true,
                'success' => false,
                'error' => 'No documents provided for scoring',
                'trust_score' => 0,
                'status' => 'rejected',
                'document_count' => 0
            ];
        }

        $documentCount = count($documentResults);
        $validDocuments = [];
        $documentContributions = [];
        $totalWeightedScore = 0;
        $totalWeight = 0;
        $conflictPenalty = 1.0;
        $conflictSources = [];
        $qualityDominanceApplied = false;

        // Process each document
        foreach ($documentResults as $index => $docResult) {
            // Calculate individual document confidence
            $individualConfidence = $this->calculateIndividualDocumentConfidence($docResult);

            // Apply confidence threshold weighting
            $confidenceWeight = ($individualConfidence >= DOC_VERIFICATION_CONFIDENCE_THRESHOLD)
                ? 1.0
                : DOC_VERIFICATION_LOW_CONFIDENCE_WEIGHT;

            // Apply diminishing returns based on document position
            $position = $index + 1;
            $diminishingFactor = DOC_VERIFICATION_DIMINISHING_RETURNS[$position] ??
                                DOC_VERIFICATION_DIMINISHING_RETURNS[4]; // Default to 4th+ weight

            // Calculate effective weight
            $effectiveWeight = $confidenceWeight * $diminishingFactor;

            // Calculate the document's trust score (this will apply time decay if available)
            $singleDocScore = $this->calculateSingleDocumentTrustScore($docResult);
            $docTrustScore = $singleDocScore['trust_score'];

            // Calculate contribution
            $contribution = $docTrustScore * $effectiveWeight;

            $documentContributions[] = [
                'document_id' => $docResult['document_id'] ?? 'unknown',
                'document_type' => $docResult['document_type'] ?? 'unknown',
                'individual_confidence' => $individualConfidence,
                'confidence_weight' => $confidenceWeight,
                'diminishing_factor' => $diminishingFactor,
                'effective_weight' => $effectiveWeight,
                'trust_score' => $docTrustScore,
                'contribution' => $contribution
            ];

            // Only include valid documents in aggregation
            if ($docTrustScore > 0) {
                $validDocuments[] = $docResult;
                $totalWeightedScore += $contribution;
                $totalWeight += $effectiveWeight;
            }
        }

        // Check for conflicts
        $conflictResult = $this->detectSemanticConflicts($documentResults);
        $conflictPenalty = 1.0;
        $totalPenaltyPoints = 0;
        if ($conflictResult['hasConflicts']) {
            $conflictPenalty = $conflictResult['penalty'];
            $conflictSources = $conflictResult['sources'];
            $totalPenaltyPoints = $conflictResult['totalPenaltyPoints'];
        }

        // Calculate base aggregated score
        $baseScore = ($totalWeight > 0) ? ($totalWeightedScore / $totalWeight) : 0;

        // Apply conflict penalties as subtractive points (before other adjustments)
        $finalScore = max(0, $baseScore - $totalPenaltyPoints);

        // Check quality dominance rule
        $qualityDominanceResult = $this->applyQualityDominanceRule($documentResults);
        if ($qualityDominanceResult['applied']) {
            $qualityDominanceApplied = true;
            $finalScore = min($finalScore, $qualityDominanceResult['max_score']);
        }

        // Apply legacy multiplicative penalty for backward compatibility (if any)
        $finalScore = $finalScore * $conflictPenalty;

        $finalScore = max(0, min(100, round($finalScore, 1)));

        // Determine status
        $status = $this->determineStatus($finalScore);

        // Generate comprehensive notes
        $notes = $this->generateEvidenceWeightedNotes(
            $documentContributions,
            $conflictPenalty,
            $conflictSources,
            $qualityDominanceApplied,
            $finalScore
        );

        // Build result
        $result = [
            'enabled' => true,
            'success' => true,
            'trust_score' => $finalScore,
            'status' => $status,
            'document_count' => $documentCount,
            'valid_document_count' => count($validDocuments),
            'notes' => $notes,
            'timestamp' => date('c'),
            'evidence_weighting' => [
                'document_contributions' => $documentContributions,
                'total_weighted_score' => $totalWeightedScore,
                'total_weight' => $totalWeight,
                'conflict_penalty' => $conflictPenalty,
                'quality_dominance_applied' => $qualityDominanceApplied
            ],
            'thresholds' => [
                'trusted' => DOC_VERIFICATION_HIGH_TRUST_SCORE,
                'review_required' => DOC_VERIFICATION_MIN_TRUST_SCORE,
                'rejected' => 0
            ]
        ];

        // Apply status-based ceiling enforcement
        $result = $this->applyStatusBasedCeiling($result);

        // Log the aggregated scoring attempt
        $this->logAggregatedScoringAttempt($result);

        return $result;
    }

    /**
     * Get scoring weights for different factors
     *
     * @param string $documentType
     * @return array Scoring weights
     */
    public function getScoringWeights($documentType)
    {
        // Base weights for different scoring factors
        $baseWeights = [
            'ocr_confidence' => 25,
            'field_extraction' => 30,
            'validation_passed' => 25,
            'llm_verification' => 15,
            'metadata_consistency' => 5
        ];

        // Adjust weights based on document type
        switch ($documentType) {
            case 'identity_card':
            case 'passport':
                // Higher weight on validation for identity documents
                $baseWeights['validation_passed'] = 35;
                $baseWeights['field_extraction'] = 25;
                $baseWeights['ocr_confidence'] = 20;
                break;
            case 'certificate':
                // Higher weight on LLM for semantic verification
                $baseWeights['llm_verification'] = 25;
                $baseWeights['validation_passed'] = 20;
                break;
            case 'contract':
            case 'invoice':
                // Balanced weights for business documents
                $baseWeights['field_extraction'] = 35;
                $baseWeights['validation_passed'] = 25;
                break;
        }

        return $baseWeights;
    }

    /**
     * Calculate score for validation results
     *
     * @param array $validationResults
     * @return float Score from validation
     */
    public function calculateValidationScore($validationResults)
    {
        if (!$validationResults || !isset($validationResults['passed'])) {
            return 0.0;
        }

        $baseScore = $validationResults['passed'] ? 100.0 : 0.0;

        // Deduct points for errors
        if (isset($validationResults['errors']) && is_array($validationResults['errors'])) {
            $errorPenalty = min(50, count($validationResults['errors']) * 10); // Max 50 point deduction
            $baseScore = max(0, $baseScore - $errorPenalty);
        }

        // Additional deductions for specific validation failures
        if (isset($validationResults['expired']) && $validationResults['expired']) {
            $baseScore = max(0, $baseScore - 30);
        }

        if (isset($validationResults['document_type_mismatch']) && $validationResults['document_type_mismatch']) {
            $baseScore = max(0, $baseScore - 40);
        }

        if (isset($validationResults['invalid_dates']) && $validationResults['invalid_dates']) {
            $baseScore = max(0, $baseScore - 20);
        }

        return $baseScore;
    }

    /**
     * Calculate score for LLM verification
     *
     * @param array $llmResults
     * @return float Score from LLM verification
     */
    public function calculateLLMScore($llmResults)
    {
        if (!$llmResults || !isset($llmResults['success']) || !$llmResults['success']) {
            return 0.0;
        }

        $baseScore = 100.0;

        // Deduct points for anomalies
        if (isset($llmResults['anomalies']) && is_array($llmResults['anomalies'])) {
            $anomalyPenalty = min(50, count($llmResults['anomalies']) * 15); // Max 50 point deduction
            $baseScore = max(0, $baseScore - $anomalyPenalty);
        }

        // Deduct points for critical anomalies
        if (isset($llmResults['critical_anomalies']) && is_array($llmResults['critical_anomalies'])) {
            $criticalPenalty = min(100, count($llmResults['critical_anomalies']) * 25); // Max 100 point deduction
            $baseScore = max(0, $baseScore - $criticalPenalty);
        }

        // Adjust based on confidence if available
        if (isset($llmResults['confidence'])) {
            $confidence = min(100, max(0, (float)$llmResults['confidence']));
            $baseScore = ($baseScore * $confidence) / 100;
        }

        return $baseScore;
    }

    /**
     * Calculate score for document metadata
     *
     * @param array $metadata
     * @return float Score from metadata analysis
     */
    public function calculateMetadataScore($metadata)
    {
        // TODO: Implement metadata score calculation
        // - File integrity checks
        // - Creation/modification dates
        // - Digital signature presence
        // - Tampering indicators

        return 0.0;
    }

    /**
     * Get user reputation score
     *
     * @param int $userId
     * @return float User reputation score
     */
    public function getUserReputationScore($userId)
    {
        // TODO: Implement user reputation scoring
        // - Historical verification success rate
        // - Account age and activity
        // - Previous fraud indicators

        return 0.0;
    }

    /**
     * Apply time-based decay to score
     *
     * @param float $score Original trust score
     * @param string $documentDate Document creation/verification date (ISO 8601 or timestamp)
     * @param string $documentType Document type for sensitivity adjustment
     * @param array $additionalContext Additional context (reverification info, etc.)
     * @return array Decay result with adjusted score and metadata
     */
    public function applyTimeDecay($score, $documentDate, $documentType = 'unknown', $additionalContext = [])
    {
        // Check if decay is enabled
        if (!DOC_VERIFICATION_DECAY_ENABLED) {
            return [
                'adjusted_score' => $score,
                'decay_applied' => false,
                'decay_factor' => 1.0,
                'days_old' => 0,
                'decay_period' => 'none',
                'reason' => 'decay_disabled'
            ];
        }

        // Parse document date
        $documentTimestamp = $this->parseDocumentDate($documentDate);
        if (!$documentTimestamp) {
            return [
                'adjusted_score' => $score,
                'decay_applied' => false,
                'decay_factor' => 1.0,
                'days_old' => 0,
                'decay_period' => 'invalid_date',
                'reason' => 'invalid_document_date'
            ];
        }

        // Calculate document age in days
        $currentTimestamp = time();
        $daysOld = ($currentTimestamp - $documentTimestamp) / (60 * 60 * 24); // Convert seconds to days

        // Apply reverification boost if applicable
        $reverificationBoost = $this->calculateReverificationBoost($additionalContext, $daysOld);
        $effectiveDaysOld = max(0, $daysOld - $reverificationBoost);

        // Determine decay period and calculate decay factor
        $decayResult = $this->calculateDecayFactor($effectiveDaysOld, $documentType);

        // Apply decay to score
        $decayFactor = $decayResult['decay_factor'];
        $adjustedScore = max(0, $score * $decayFactor);

        // Log decay application for audit trail
        $this->logTimeDecay($additionalContext['document_id'] ?? null, $score, $adjustedScore, $decayFactor, $daysOld, $decayResult['period']);

        return [
            'adjusted_score' => round($adjustedScore, 1),
            'decay_applied' => ($decayFactor < 1.0),
            'decay_factor' => $decayFactor,
            'days_old' => round($daysOld, 1),
            'effective_days_old' => round($effectiveDaysOld, 1),
            'decay_period' => $decayResult['period'],
            'reverification_boost' => $reverificationBoost,
            'document_type_sensitivity' => DOC_VERIFICATION_DECAY_SENSITIVITIES[$documentType] ?? 1.0
        ];
    }

    /**
     * Parse document date from various formats
     *
     * @param string $documentDate
     * @return int|null Unix timestamp or null if invalid
     */
    private function parseDocumentDate($documentDate)
    {
        if (is_numeric($documentDate)) {
            return (int)$documentDate;
        }

        // Try ISO 8601 format first
        $timestamp = strtotime($documentDate);
        if ($timestamp !== false) {
            return $timestamp;
        }

        // Try common date formats
        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'm/d/Y', 'd/m/Y'];
        foreach ($formats as $format) {
            $parsed = DateTime::createFromFormat($format, $documentDate);
            if ($parsed !== false) {
                return $parsed->getTimestamp();
            }
        }

        return null;
    }

    /**
     * Calculate reverification boost
     *
     * @param array $context Additional context
     * @param float $daysOld Document age in days
     * @return float Boost in days (to subtract from effective age)
     */
    private function calculateReverificationBoost($context, $daysOld)
    {
        if (!DOC_VERIFICATION_REVERIFICATION_BOOST_ENABLED) {
            return 0.0;
        }

        // Check if document has been reverified recently
        $lastReverification = $context['last_reverification_date'] ?? null;
        if (!$lastReverification) {
            return 0.0;
        }

        $reverificationTimestamp = $this->parseDocumentDate($lastReverification);
        if (!$reverificationTimestamp) {
            return 0.0;
        }

        // Check if reverification is within eligibility period
        $daysSinceReverification = (time() - $reverificationTimestamp) / (60 * 60 * 24);
        if ($daysSinceReverification > DOC_VERIFICATION_REVERIFICATION_MAX_DAYS) {
            return 0.0;
        }

        // Calculate boost - restore portion of decay
        $boostDays = min($daysOld * DOC_VERIFICATION_REVERIFICATION_BOOST_FACTOR, $daysOld);

        return $boostDays;
    }

    /**
     * Calculate decay factor based on document age and type
     *
     * @param float $daysOld Effective document age in days
     * @param string $documentType
     * @return array Decay calculation result
     */
    private function calculateDecayFactor($daysOld, $documentType)
    {
        $breakpoints = DOC_VERIFICATION_DECAY_BREAKPOINTS;
        $rates = DOC_VERIFICATION_DECAY_RATES;
        $sensitivities = DOC_VERIFICATION_DECAY_SENSITIVITIES;
        $decayType = DOC_VERIFICATION_DECAY_TYPE;

        // Get document type sensitivity multiplier
        $sensitivity = $sensitivities[$documentType] ?? 1.0;

        // Determine decay period
        if ($daysOld <= $breakpoints['no_decay']) {
            return [
                'decay_factor' => 1.0,
                'period' => 'no_decay'
            ];
        } elseif ($daysOld <= $breakpoints['gradual']) {
            $period = 'gradual';
            $daysInPeriod = $daysOld - $breakpoints['no_decay'];
            $periodLength = $breakpoints['gradual'] - $breakpoints['no_decay'];
        } else {
            $period = 'aggressive';
            $daysInPeriod = $daysOld - $breakpoints['gradual'];
            $periodLength = 365; // Arbitrary long period for aggressive decay
        }

        // Calculate decay factor based on type
        if ($decayType === 'linear') {
            $rate = ($period === 'gradual')
                ? $rates['gradual_linear']
                : $rates['aggressive_linear'];

            // Apply sensitivity multiplier
            $effectiveRate = $rate * $sensitivity;

            // Calculate linear decay
            $decayAmount = min(100, $daysInPeriod * $effectiveRate); // Cap at 100% decay
            $decayFactor = max(0, (100 - $decayAmount) / 100);
        } else { // exponential
            $rate = ($period === 'gradual')
                ? $rates['gradual_exponential']
                : $rates['aggressive_exponential'];

            // Apply sensitivity multiplier
            $effectiveRate = $rate * $sensitivity;

            // Calculate exponential decay
            $decayFactor = exp(-$effectiveRate * $daysInPeriod);
            $decayFactor = max(0, min(1, $decayFactor)); // Clamp between 0 and 1
        }

        return [
            'decay_factor' => round($decayFactor, 4),
            'period' => $period
        ];
    }

    /**
     * Log time decay application for audit purposes
     *
     * @param string|null $documentId
     * @param float $originalScore
     * @param float $adjustedScore
     * @param float $decayFactor
     * @param float $daysOld
     * @param string $period
     */
    private function logTimeDecay($documentId, $originalScore, $adjustedScore, $decayFactor, $daysOld, $period)
    {
        if (!$documentId) {
            return;
        }

        // Log to file for audit trail
        $logMessage = sprintf(
            "[%s] TIME DECAY for document %s: original_score=%.1f, adjusted_score=%.1f, decay_factor=%.4f, days_old=%.1f, period=%s\n",
            date('Y-m-d H:i:s'),
            $documentId,
            $originalScore,
            $adjustedScore,
            $decayFactor,
            $daysOld,
            $period
        );

        error_log($logMessage);
    }

    /**
     * Calculate OCR score based on confidence
     *
     * @param array|null $ocrResult
     * @return float Score from 0-100
     */
    private function calculateOCRScore($ocrResult)
    {
        if (!$ocrResult || !isset($ocrResult['success']) || !$ocrResult['success']) {
            return 0.0;
        }

        $confidence = $ocrResult['confidence'] ?? 0;
        // OCR confidence is typically 0-100, convert to 0-100 score
        return min(100.0, max(0.0, (float)$confidence));
    }

    /**
     * Calculate field extraction score
     *
     * @param array|null $fieldResult
     * @return float Score from 0-100
     */
    private function calculateFieldScore($fieldResult)
    {
        if (!$fieldResult || !isset($fieldResult['success']) || !$fieldResult['success']) {
            return 0.0;
        }

        // Base score on number of required fields present vs missing
        $requiredFields = $fieldResult['required_fields'] ?? [];
        $presentFields = $fieldResult['present_fields'] ?? [];
        $warnings = $fieldResult['warnings'] ?? [];

        $totalRequired = count($requiredFields);
        if ($totalRequired === 0) {
            return 100.0; // No required fields means perfect score
        }

        $presentCount = count(array_intersect($requiredFields, $presentFields));
        $baseScore = ($presentCount / $totalRequired) * 100;

        // Deduct points for warnings (each warning reduces score by 5 points)
        $warningPenalty = min(50, count($warnings) * 5); // Max 50 point deduction

        return max(0.0, $baseScore - $warningPenalty);
    }

    /**
     * Determine status based on score
     *
     * @param float $score
     * @return string Status: trusted, review_required, rejected
     */
    private function determineStatus($score)
    {
        if ($score >= DOC_VERIFICATION_HIGH_TRUST_SCORE) {
            return 'trusted';
        } elseif ($score >= DOC_VERIFICATION_MIN_TRUST_SCORE) {
            return 'review_required';
        } else {
            return 'rejected';
        }
    }

    /**
     * Generate explanatory notes
     *
     * @param array|null $ocrResult
     * @param array|null $fieldResult
     * @param array|null $validationResult
     * @param array|null $llmResult
     * @param float $totalScore
     * @return array Notes explaining the score
     */
    private function generateNotes($ocrResult, $fieldResult, $validationResult, $llmResult, $totalScore)
    {
        $notes = [];

        // OCR notes
        if ($ocrResult && isset($ocrResult['confidence'])) {
            $notes[] = "OCR confidence: {$ocrResult['confidence']}%";
        } else {
            $notes[] = "OCR processing failed or not available";
        }

        // Field extraction notes
        if ($fieldResult && isset($fieldResult['present_fields'])) {
            $present = count($fieldResult['present_fields']);
            $required = count($fieldResult['required_fields'] ?? []);
            $notes[] = "Required fields present: {$present}/{$required}";
            if (!empty($fieldResult['warnings'])) {
                $notes[] = "Field extraction warnings: " . count($fieldResult['warnings']);
            }
        } else {
            $notes[] = "Field extraction not available";
        }

        // Validation notes
        if ($validationResult && isset($validationResult['passed'])) {
            $passed = $validationResult['passed'] ? 'passed' : 'failed';
            $notes[] = "Validation: {$passed}";
            if (!empty($validationResult['errors'])) {
                $notes[] = "Validation errors: " . count($validationResult['errors']);
            }
        } else {
            $notes[] = "Validation not performed";
        }

        // LLM verification notes
        if ($llmResult && isset($llmResult['confidence'])) {
            $notes[] = "Semantic verification confidence: {$llmResult['confidence']}%";
        } elseif (DOC_VERIFICATION_ENABLE_LLM) {
            $notes[] = "Semantic verification not available";
        }

        // Overall score note
        $status = $this->determineStatus($totalScore);
        $notes[] = "Overall trust score: {$totalScore}/100 ({$status})";

        return $notes;
    }

    /**
     * Log hard failures
     *
     * @param string|null $documentId
     * @param array $failures
     */
    private function logHardFailures($documentId, array $failures)
    {
        if (!$documentId) {
            return;
        }

        // Log to file for now (could be enhanced to log to database)
        $logMessage = sprintf(
            "[%s] HARD FAILURE for document %s: %s\n",
            date('Y-m-d H:i:s'),
            $documentId,
            implode('; ', $failures)
        );

        error_log($logMessage);
    }

    /**
     * Apply status-based trust score ceiling as mandatory post-processing control
     *
     * @param array $scoringResult Current scoring result
     * @return array Modified scoring result with ceiling applied
     */
    private function applyStatusBasedCeiling(array $scoringResult): array
    {
        $originalScore = $scoringResult['trust_score'];
        $status = $scoringResult['status'];
        $documentId = $scoringResult['document_id'] ?? null;

        $cappedScore = $originalScore;

        switch ($status) {
            case 'trusted':
                $cappedScore = min($originalScore, DOC_VERIFICATION_CEILING_TRUSTED);
                break;
            case 'review_required':
                $cappedScore = min($originalScore, DOC_VERIFICATION_CEILING_REVIEW_REQUIRED);
                break;
            case 'rejected':
            case 'failed':
                $cappedScore = DOC_VERIFICATION_CEILING_REJECTED;
                break;
        }

        // If score was capped, log the event and update result
        if ($cappedScore !== $originalScore) {
            $this->logScoreCapping($documentId, $originalScore, $cappedScore, $status);

            $scoringResult['trust_score'] = $cappedScore;
            $scoringResult['ceiling_applied'] = true;
            $scoringResult['original_score'] = $originalScore;
            $scoringResult['ceiling_reason'] = 'status-based ceiling enforcement';

            // Update notes to reflect capping
            $scoringResult['notes'][] = sprintf(
                "Score capped from %.1f to %.1f due to %s status",
                $originalScore,
                $cappedScore,
                $status
            );
        } else {
            $scoringResult['ceiling_applied'] = false;
        }

        return $scoringResult;
    }

    /**
     * Log score capping events for audit purposes
     *
     * @param string|null $documentId
     * @param float $originalScore
     * @param float $cappedScore
     * @param string $status
     */
    private function logScoreCapping($documentId, $originalScore, $cappedScore, $status)
    {
        if (!$documentId) {
            return;
        }

        // Log to file for audit trail
        $logMessage = sprintf(
            "[%s] SCORE CAPPING for document %s: original_score=%.1f, capped_score=%.1f, status=%s, reason=status-based ceiling enforcement\n",
            date('Y-m-d H:i:s'),
            $documentId,
            $originalScore,
            $cappedScore,
            $status
        );

        error_log($logMessage);
    }

    /**
     * Log scoring attempt
     *
     * @param string|null $documentId
     * @param float $score
     * @param string $status
     */
    private function logScoringAttempt($documentId, $score, $status)
    {
        if (!$documentId) {
            return;
        }

        // Log to file for now (could be enhanced to log to database)
        $logMessage = sprintf(
            "[%s] Trust scoring for document %s: score=%.1f, status=%s\n",
            date('Y-m-d H:i:s'),
            $documentId,
            $score,
            $status
        );

        error_log($logMessage);
    }

    /**
     * Calculate individual document confidence score
     *
     * @param array $documentResult Single document processing result
     * @return float Confidence score from 0-100
     */
    private function calculateIndividualDocumentConfidence(array $documentResult): float
    {
        $processingSteps = $documentResult['processing_steps'] ?? [];

        // Get individual factor scores
        $ocrResult = $processingSteps['ocr'] ?? null;
        $fieldResult = $processingSteps['field_extraction'] ?? null;
        $validationResult = $processingSteps['validation'] ?? null;
        $llmResult = $processingSteps['llm_verification'] ?? null;

        $ocrScore = $this->calculateOCRScore($ocrResult);
        $fieldScore = $this->calculateFieldScore($fieldResult);
        $validationScore = $this->calculateValidationScore($validationResult);
        $llmScore = $this->calculateLLMScore($llmResult);

        // Weight the factors for confidence calculation
        $weights = [
            'ocr' => 0.3,
            'field' => 0.3,
            'validation' => 0.25,
            'llm' => 0.15
        ];

        $confidence = (
            ($ocrScore * $weights['ocr']) +
            ($fieldScore * $weights['field']) +
            ($validationScore * $weights['validation']) +
            ($llmScore * $weights['llm'])
        );

        return min(100.0, max(0.0, round($confidence, 1)));
    }

    /**
     * Detect semantic conflicts between documents
     *
     * @param array $documentResults Array of document processing results
     * @return array Conflict detection result
     */
    private function detectSemanticConflicts(array $documentResults): array
    {
        if (!DOC_VERIFICATION_CROSS_DOCUMENT_CHECKS_ENABLED) {
            return [
                'hasConflicts' => false,
                'conflictLevel' => 'none',
                'sources' => [],
                'penalty' => 1.0,
                'totalPenaltyPoints' => 0
            ];
        }

        $conflicts = [];
        $totalPenaltyPoints = 0;

        // Group documents by type for relationship-based checking
        $documentsByType = [];
        foreach ($documentResults as $doc) {
            $docType = $this->normalizeDocumentType($doc['document_type'] ?? 'unknown');
            if (!isset($documentsByType[$docType])) {
                $documentsByType[$docType] = [];
            }
            $documentsByType[$docType][] = $doc;
        }

        // Check conflicts between related document types
        $relationships = DOC_VERIFICATION_DOCUMENT_RELATIONSHIPS;
        foreach ($relationships as $docType => $relatedTypes) {
            if (!isset($documentsByType[$docType])) {
                continue;
            }

            foreach ($relatedTypes as $relatedType) {
                if (!isset($documentsByType[$relatedType])) {
                    continue;
                }

                // Check conflicts between documents of these related types
                $typeConflicts = $this->checkConflictsBetweenTypes(
                    $documentsByType[$docType],
                    $documentsByType[$relatedType],
                    $docType,
                    $relatedType
                );

                $conflicts = array_merge($conflicts, $typeConflicts['conflicts']);
                $totalPenaltyPoints += $typeConflicts['penaltyPoints'];
            }
        }

        // Also check for any conflicts within the same document type if multiple exist
        foreach ($documentsByType as $docType => $docs) {
            if (count($docs) > 1) {
                $sameTypeConflicts = $this->checkConflictsWithinType($docs, $docType);
                $conflicts = array_merge($conflicts, $sameTypeConflicts['conflicts']);
                $totalPenaltyPoints += $sameTypeConflicts['penaltyPoints'];
            }
        }

        // Cap the total penalty
        $totalPenaltyPoints = max($totalPenaltyPoints, DOC_VERIFICATION_MAX_CONFLICT_PENALTY);

        // Determine overall conflict level
        $conflictLevel = $this->determineOverallConflictLevel($conflicts);

        // Calculate multiplicative penalty (legacy compatibility)
        $penalty = $this->calculateMultiplicativePenalty($conflictLevel);

        return [
            'hasConflicts' => !empty($conflicts),
            'conflictLevel' => $conflictLevel,
            'sources' => $conflicts,
            'penalty' => $penalty,
            'totalPenaltyPoints' => $totalPenaltyPoints
        ];
    }

    /**
     * Check conflicts between two related document types
     *
     * @param array $docsA Documents of type A
     * @param array $docsB Documents of type B
     * @param string $typeA Document type A
     * @param string $typeB Document type B
     * @return array Conflict results
     */
    private function checkConflictsBetweenTypes(array $docsA, array $docsB, string $typeA, string $typeB): array
    {
        $conflicts = [];
        $penaltyPoints = 0;

        foreach ($docsA as $docA) {
            foreach ($docsB as $docB) {
                $fieldConflicts = $this->compareDocumentFields($docA, $docB, $typeA, $typeB);
                foreach ($fieldConflicts as $conflict) {
                    $conflicts[] = $conflict;
                    $penaltyPoints += $this->getPenaltyPointsForSeverity($conflict['severity']);
                }
            }
        }

        return [
            'conflicts' => $conflicts,
            'penaltyPoints' => $penaltyPoints
        ];
    }

    /**
     * Check conflicts within documents of the same type
     *
     * @param array $docs Documents of the same type
     * @param string $docType Document type
     * @return array Conflict results
     */
    private function checkConflictsWithinType(array $docs, string $docType): array
    {
        $conflicts = [];
        $penaltyPoints = 0;

        // For documents of the same type, check for consistency (e.g., multiple IDs should match)
        for ($i = 0; $i < count($docs) - 1; $i++) {
            for ($j = $i + 1; $j < count($docs); $j++) {
                $fieldConflicts = $this->compareDocumentFields($docs[$i], $docs[$j], $docType, $docType);
                foreach ($fieldConflicts as $conflict) {
                    $conflicts[] = $conflict;
                    $penaltyPoints += $this->getPenaltyPointsForSeverity($conflict['severity']);
                }
            }
        }

        return [
            'conflicts' => $conflicts,
            'penaltyPoints' => $penaltyPoints
        ];
    }

    /**
     * Compare fields between two documents
     *
     * @param array $docA First document
     * @param array $docB Second document
     * @param string $typeA Type of first document
     * @param string $typeB Type of second document
     * @return array Field conflicts found
     */
    private function compareDocumentFields(array $docA, array $docB, string $typeA, string $typeB): array
    {
        $conflicts = [];

        $fieldsA = $this->extractFieldsFromDocument($docA);
        $fieldsB = $this->extractFieldsFromDocument($docB);

        $conflictRules = DOC_VERIFICATION_FIELD_CONFLICT_RULES;

        foreach ($conflictRules as $ruleName => $rule) {
            $ruleFields = $rule['fields'];
            $severity = $rule['severity'];

            // Find matching fields in both documents
            $matchingFields = array_intersect(array_keys($fieldsA), array_keys($fieldsB), $ruleFields);

            foreach ($matchingFields as $fieldName) {
                $valueA = $this->normalizeFieldValue($fieldsA[$fieldName]);
                $valueB = $this->normalizeFieldValue($fieldsB[$fieldName]);

                if (!$this->areFieldValuesConsistent($valueA, $valueB, $fieldName)) {
                    $conflicts[] = [
                        'field' => $fieldName,
                        'severity' => $severity,
                        'documents' => [
                            [
                                'id' => $docA['document_id'] ?? 'unknown',
                                'type' => $typeA,
                                'value' => $fieldsA[$fieldName]
                            ],
                            [
                                'id' => $docB['document_id'] ?? 'unknown',
                                'type' => $typeB,
                                'value' => $fieldsB[$fieldName]
                            ]
                        ],
                        'rule' => $ruleName,
                        'timestamp' => date('c')
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Extract fields from a document's processing results
     *
     * @param array $document Document processing result
     * @return array Extracted fields
     */
    private function extractFieldsFromDocument(array $document): array
    {
        $fields = [];

        $processingSteps = $document['processing_steps'] ?? [];
        $fieldResult = $processingSteps['field_extraction'] ?? null;

        if ($fieldResult && isset($fieldResult['extracted_fields'])) {
            $fields = $fieldResult['extracted_fields'];
        }

        // Also check validation results for additional fields
        $validationResult = $processingSteps['validation'] ?? null;
        if ($validationResult) {
            $validationFields = [
                'issue_date', 'expiration_date', 'expiry_date', 'created_date'
            ];
            foreach ($validationFields as $field) {
                if (isset($validationResult[$field])) {
                    $fields[$field] = $validationResult[$field];
                }
            }
        }

        return $fields;
    }

    /**
     * Normalize field value for comparison
     *
     * @param mixed $value Field value
     * @return string Normalized value
     */
    private function normalizeFieldValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $normalized = trim(strtolower((string)$value));

        // Remove common separators and normalize spaces
        $normalized = preg_replace('/[,\.\-\s]+/', ' ', $normalized);
        $normalized = trim($normalized);

        return $normalized;
    }

    /**
     * Check if two field values are consistent
     *
     * @param string $valueA First value
     * @param string $valueB Second value
     * @param string $fieldName Field name
     * @return bool True if consistent
     */
    private function areFieldValuesConsistent(string $valueA, string $valueB, string $fieldName): bool
    {
        // Empty values are considered consistent (missing data)
        if (empty($valueA) || empty($valueB)) {
            return true;
        }

        // Exact match
        if ($valueA === $valueB) {
            return true;
        }

        // For names and identifiers, allow for minor variations
        if (in_array($fieldName, ['name', 'company_name', 'identifier', 'number'])) {
            return $this->areNamesSimilar($valueA, $valueB);
        }

        // For addresses, check for substantial similarity
        if (strpos($fieldName, 'address') !== false) {
            return $this->areAddressesSimilar($valueA, $valueB);
        }

        // For dates, parse and compare
        if (strpos($fieldName, 'date') !== false) {
            return $this->areDatesSimilar($valueA, $valueB);
        }

        // For quantities and amounts, allow small differences
        if (in_array($fieldName, ['quantity', 'total_amount', 'weight'])) {
            return $this->areQuantitiesSimilar($valueA, $valueB);
        }

        // Default: require exact match
        return false;
    }

    /**
     * Check if two names/identifiers are similar
     *
     * @param string $nameA First name
     * @param string $nameB Second name
     * @return bool True if similar
     */
    private function areNamesSimilar(string $nameA, string $nameB): bool
    {
        // Simple similarity check - could be enhanced with more sophisticated algorithms
        $levenshtein = levenshtein($nameA, $nameB);
        $maxLength = max(strlen($nameA), strlen($nameB));

        // Allow up to 20% difference or 3 character difference, whichever is smaller
        $threshold = min(3, $maxLength * 0.2);

        return $levenshtein <= $threshold;
    }

    /**
     * Check if two addresses are similar
     *
     * @param string $addrA First address
     * @param string $addrB Second address
     * @return bool True if similar
     */
    private function areAddressesSimilar(string $addrA, string $addrB): bool
    {
        // For addresses, require higher similarity
        $levenshtein = levenshtein($addrA, $addrB);
        $maxLength = max(strlen($addrA), strlen($addrB));

        // Allow up to 15% difference
        $threshold = $maxLength * 0.15;

        return $levenshtein <= $threshold;
    }

    /**
     * Check if two dates are similar
     *
     * @param string $dateA First date
     * @param string $dateB Second date
     * @return bool True if similar
     */
    private function areDatesSimilar(string $dateA, string $dateB): bool
    {
        // Try to parse dates
        $timestampA = strtotime($dateA);
        $timestampB = strtotime($dateB);

        if ($timestampA === false || $timestampB === false) {
            // If can't parse, fall back to string comparison
            return $this->areNamesSimilar($dateA, $dateB);
        }

        // Allow 1 day difference for date fields
        $diff = abs($timestampA - $timestampB);
        return $diff <= (24 * 60 * 60); // 1 day in seconds
    }

    /**
     * Check if two quantities are similar
     *
     * @param string $qtyA First quantity
     * @param string $qtyB Second quantity
     * @return bool True if similar
     */
    private function areQuantitiesSimilar(string $qtyA, string $qtyB): bool
    {
        // Extract numeric values
        $numA = $this->extractNumericValue($qtyA);
        $numB = $this->extractNumericValue($qtyB);

        if ($numA === null || $numB === null) {
            return $this->areNamesSimilar($qtyA, $qtyB);
        }

        // Allow 5% difference for quantities
        $avg = ($numA + $numB) / 2;
        $diff = abs($numA - $numB);

        return $diff <= ($avg * 0.05);
    }

    /**
     * Extract numeric value from a string
     *
     * @param string $str String containing a number
     * @return float|null Extracted number or null
     */
    private function extractNumericValue(string $str): ?float
    {
        // Remove currency symbols, commas, etc.
        $cleaned = preg_replace('/[^\d\.]/', '', $str);
        $num = (float)$cleaned;

        return is_numeric($cleaned) ? $num : null;
    }

    /**
     * Normalize document type to match configuration keys
     *
     * @param string $docType Document type
     * @return string Normalized type
     */
    private function normalizeDocumentType(string $docType): string
    {
        // Convert spaces and special chars to underscores, lowercase
        $normalized = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $docType));

        // Handle specific mappings
        $mappings = [
            'bill_of_lading' => 'bill_of_lading',
            'commercial_invoice' => 'commercial_invoice',
            'packing_list' => 'packing_list',
            'business_license' => 'business_license',
            'identity_card' => 'identity_card',
            'certificate_of_origin' => 'certificate_of_origin',
            'insurance_certificate' => 'insurance_certificate'
        ];

        return $mappings[$normalized] ?? $normalized;
    }

    /**
     * Get penalty points for a severity level
     *
     * @param string $severity Severity level
     * @return int Penalty points
     */
    private function getPenaltyPointsForSeverity(string $severity): int
    {
        switch ($severity) {
            case 'critical':
                return abs(DOC_VERIFICATION_CONFLICT_SEVERITY_CRITICAL);
            case 'high':
                return abs(DOC_VERIFICATION_CONFLICT_SEVERITY_HIGH);
            case 'minor':
                return abs(DOC_VERIFICATION_CONFLICT_SEVERITY_MINOR);
            default:
                return 0;
        }
    }

    /**
     * Determine overall conflict level from conflicts
     *
     * @param array $conflicts Array of conflicts
     * @return string Overall conflict level
     */
    private function determineOverallConflictLevel(array $conflicts): string
    {
        if (empty($conflicts)) {
            return 'none';
        }

        $hasCritical = false;
        $hasHigh = false;

        foreach ($conflicts as $conflict) {
            $severity = $conflict['severity'] ?? 'minor';
            if ($severity === 'critical') {
                $hasCritical = true;
            } elseif ($severity === 'high') {
                $hasHigh = true;
            }
        }

        if ($hasCritical) {
            return 'critical';
        } elseif ($hasHigh) {
            return 'high';
        } else {
            return 'minor';
        }
    }

    /**
     * Calculate multiplicative penalty for backward compatibility
     *
     * @param string $conflictLevel Conflict level
     * @return float Multiplicative penalty
     */
    private function calculateMultiplicativePenalty(string $conflictLevel): float
    {
        switch ($conflictLevel) {
            case 'critical':
                return DOC_VERIFICATION_CONFLICT_PENALTY_CRITICAL;
            case 'high':
                return DOC_VERIFICATION_CONFLICT_PENALTY_HIGH;
            case 'minor':
                return DOC_VERIFICATION_CONFLICT_PENALTY_LOW;
            default:
                return 1.0;
        }
    }

    /**
     * Apply quality dominance rule for required documents
     *
     * @param array $documentResults Array of document processing results
     * @return array Quality dominance result
     */
    private function applyQualityDominanceRule(array $documentResults): array
    {
        // Identify required document types (this could be configurable)
        $requiredTypes = ['identity_card', 'passport', 'drivers_license'];

        $requiredDocs = [];
        $optionalDocs = [];

        foreach ($documentResults as $doc) {
            $docType = $doc['document_type'] ?? 'unknown';
            if (in_array($docType, $requiredTypes)) {
                $requiredDocs[] = $doc;
            } else {
                $optionalDocs[] = $doc;
            }
        }

        // If we have required documents, check their average confidence
        if (!empty($requiredDocs)) {
            $totalConfidence = 0;
            foreach ($requiredDocs as $doc) {
                $totalConfidence += $this->calculateIndividualDocumentConfidence($doc);
            }
            $avgConfidence = $totalConfidence / count($requiredDocs);

            // If required documents don't meet threshold, cap the overall score
            if ($avgConfidence < DOC_VERIFICATION_REQUIRED_DOC_THRESHOLD) {
                $maxScore = $avgConfidence; // Cap at average required document confidence
                return [
                    'applied' => true,
                    'reason' => 'Required documents below confidence threshold',
                    'max_score' => $maxScore,
                    'avg_required_confidence' => $avgConfidence
                ];
            }
        }

        return ['applied' => false];
    }

    /**
     * Generate comprehensive notes for evidence-weighted scoring
     *
     * @param array $documentContributions Document contribution details
     * @param float $conflictPenalty Applied conflict penalty
     * @param array $conflictSources Conflict sources
     * @param bool $qualityDominanceApplied Whether quality dominance was applied
     * @param float $finalScore Final aggregated score
     * @return array Comprehensive notes
     */
    private function generateEvidenceWeightedNotes(
        array $documentContributions,
        float $conflictPenalty,
        array $conflictSources,
        bool $qualityDominanceApplied,
        float $finalScore
    ): array {
        $notes = [];

        // Document count and contributions
        $totalDocs = count($documentContributions);
        $notes[] = "Evidence-weighted scoring for {$totalDocs} documents";

        // Individual document contributions
        foreach ($documentContributions as $contrib) {
            $notes[] = sprintf(
                "Document %s (%s): confidence=%.1f, weight=%.2f, contribution=%.1f",
                $contrib['document_id'],
                $contrib['document_type'],
                $contrib['individual_confidence'],
                $contrib['effective_weight'],
                $contrib['contribution']
            );
        }

        // Conflict information
        if ($conflictPenalty < 1.0) {
            $notes[] = sprintf("Conflict penalty applied: %.2f", $conflictPenalty);
            if (!empty($conflictSources)) {
                $notes[] = "Conflicts detected in fields: " . implode(', ', array_column($conflictSources, 'field'));
            }
        }

        // Quality dominance
        if ($qualityDominanceApplied) {
            $notes[] = "Quality dominance rule applied - score capped by required document quality";
        }

        // Final score
        $status = $this->determineStatus($finalScore);
        $notes[] = sprintf("Aggregated trust score: %.1f/100 (%s)", $finalScore, $status);

        return $notes;
    }

    /**
     * Log aggregated scoring attempt
     *
     * @param array $result Aggregated scoring result
     */
    private function logAggregatedScoringAttempt(array $result)
    {
        $documentCount = $result['document_count'] ?? 0;
        $score = $result['trust_score'] ?? 0;
        $status = $result['status'] ?? 'unknown';

        // Log to file for audit trail
        $logMessage = sprintf(
            "[%s] AGGREGATED Trust scoring for %d documents: score=%.1f, status=%s\n",
            date('Y-m-d H:i:s'),
            $documentCount,
            $score,
            $status
        );

        error_log($logMessage);
    }
}
