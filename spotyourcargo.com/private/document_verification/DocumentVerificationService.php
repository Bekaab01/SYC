<?php
/**
 * Document Verification Service
 *
 * Main service class that orchestrates the document verification process.
 * This is the central boundary for all document verification operations.
 */

require_once __DIR__ . '/config.php';
require_once '../../private/db.php';

class DocumentVerificationService
{
    private $pdo;
    private $uploadHandler;
    private $ocrProcessor;
    private $fieldExtractor;
    private $validator;
    private $llmVerifier;
    private $trustScorer;

    public function __construct()
    {
        global $pdo;
        $this->pdo = $pdo;

        // Initialize service components (stubs for now)
        // $this->uploadHandler = new DocumentUploadHandler(); // Commented out - class doesn't exist
        $this->ocrProcessor = new OCRProcessor();
        $this->fieldExtractor = new FieldExtractor();
        $this->validator = new DocumentValidator();
        $this->llmVerifier = new LLMVerifier();
        $this->trustScorer = new TrustScorer();
    }

    /**
     * Upload and initiate verification process for a document
     *
     * @param array $file Uploaded file data
     * @param int $ownerId User ID who owns the document
     * @param string $documentType Type of document
     * @return array Result with document ID and status
     */
    public function uploadDocument($file, $ownerId, $documentType)
    {
        // TODO: Implement full verification pipeline
        // For now, just handle upload and return document ID

        $result = $this->uploadHandler->handleUpload($file, $ownerId, $documentType);

        if ($result['success']) {
            // TODO: Trigger async processing pipeline
            // $this->processDocumentAsync($result['document_id']);
        }

        return $result;
    }

    /**
     * Get document verification status
     *
     * @param string $documentId
     * @return array Document status and details
     */
    public function getDocumentStatus($documentId)
    {
        // TODO: Implement status retrieval from database
        return [
            'document_id' => $documentId,
            'status' => DOC_STATUS_PENDING,
            'trust_score' => null,
            'message' => 'Document uploaded, processing pending implementation'
        ];
    }

    /**
     * Process document through verification pipeline
     *
     * @param string $documentId
     * @return array Processing results
     */
    public function processDocument($documentId)
    {
        try {
            // Get document details from database
            $stmt = $this->pdo->prepare("
                SELECT ld.*, u.id as user_id
                FROM load_documents ld
                JOIN users u ON ld.uploader_id = u.id
                WHERE ld.document_id = ?
            ");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                throw new Exception("Document not found: $documentId");
            }

            // Build file path
            $userDir = DOC_VERIFICATION_BASE_STORAGE_PATH . $document['user_id'] . '/';
            $filePath = $userDir . $document['stored_filename'];

            if (!file_exists($filePath)) {
                throw new Exception("Document file not found: $filePath");
            }

            $results = [
                'document_id' => $documentId,
                'document_type' => $document['doc_type'] ?? 'unknown',
                'processing_steps' => []
            ];

            // 1. OCR Processing
            if (DOC_VERIFICATION_ENABLE_OCR) {
                $ocrResult = $this->ocrProcessor->processDocument($documentId, $filePath);
                $results['processing_steps']['ocr'] = $ocrResult;

                // Update OCR results in database
                $this->updateDocumentOCRResults($documentId, $ocrResult);
            }

            // 2. Field Extraction (future implementation)
            if (isset($ocrResult) && $ocrResult['success']) {
                $extractionResult = $this->fieldExtractor->extractFields($ocrResult['extracted_text'], $document['doc_type']);
                $results['processing_steps']['field_extraction'] = $extractionResult;

                // Store field extraction result
                $this->updateDocumentFieldExtractionResults($documentId, $extractionResult);
            }

            // 3. Rule-based Validation (future implementation)
            if (isset($extractionResult) && $extractionResult['success']) {
                $validationResult = $this->validator->validateDocument($extractionResult['fields'], $document['doc_type']);
                $results['processing_steps']['validation'] = $validationResult;

                // Store validation result
                $this->updateDocumentValidationResults($documentId, $validationResult);
            }

            // 4. LLM Verification (future implementation - disabled)
            if (DOC_VERIFICATION_ENABLE_LLM && isset($validationResult) && $validationResult['success']) {
                $llmResult = $this->llmVerifier->verifyDocument($extractionResult['fields'], $document['doc_type']);
                $results['processing_steps']['llm_verification'] = $llmResult;

                // Store LLM verification result
                $this->updateDocumentLLMVerificationResults($documentId, $llmResult);
            }

            // 5. Trust Score Calculation (future implementation)
            $trustScore = $this->trustScorer->calculateTrustScore($results);
            $results['processing_steps']['trust_scoring'] = $trustScore;

            // Update final status
            $this->updateDocumentStatus($documentId, DOC_STATUS_COMPLETED, $trustScore['trust_score']);

            return [
                'success' => true,
                'document_id' => $documentId,
                'status' => DOC_STATUS_COMPLETED,
                'trust_score' => $trustScore['trust_score'],
                'results' => $results
            ];

        } catch (Exception $e) {
            // Log error and update status
            error_log("Document processing failed for $documentId: " . $e->getMessage());
            $this->updateDocumentStatus($documentId, DOC_STATUS_FAILED, 0, $e->getMessage());

            return [
                'success' => false,
                'document_id' => $documentId,
                'status' => DOC_STATUS_FAILED,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update OCR results in database
     *
     * @param string $documentId
     * @param array $ocrResult
     */
    private function updateDocumentOCRResults($documentId, $ocrResult)
    {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE load_documents
                SET ocr_text = ?, ocr_engine = ?, ocr_confidence = ?, ocr_status = ?, ocr_processed_at = NOW()
                WHERE document_id = ?
            ");
            $stmt->execute([
                $ocrResult['extracted_text'],
                DOC_VERIFICATION_OCR_ENGINE,
                $ocrResult['confidence'],
                $ocrResult['success'] ? 'completed' : 'failed',
                $documentId
            ]);
        } catch (Exception $e) {
            error_log("Failed to update OCR results for document $documentId: " . $e->getMessage());
        }
    }

    /**
     * Update document processing status
     *
     * @param string $documentId
     * @param string $status
     * @param float $trustScore
     * @param string $errorMessage
     */
    private function updateDocumentStatus($documentId, $status, $trustScore = null, $errorMessage = null)
    {
        if (!$this->pdo) return;

        try {
            $stmt = $this->pdo->prepare("
                UPDATE load_documents
                SET verification_status = ?, trust_score = ?, processed_at = NOW(), error_message = ?
                WHERE document_id = ?
            ");
            $stmt->execute([$status, $trustScore, $errorMessage, $documentId]);
        } catch (Exception $e) {
            error_log("Failed to update document status for $documentId: " . $e->getMessage());
        }
    }

    /**
     * Update field extraction results in database (stub)
     *
     * @param string $documentId
     * @param array $extractionResult
     */
    private function updateDocumentFieldExtractionResults($documentId, $extractionResult)
    {
        // TODO: Implement field extraction result storage
        // For now, just log that this method was called
        error_log("Field extraction results update called for document $documentId - not implemented yet");
    }

    /**
     * Update validation results in database (stub)
     *
     * @param string $documentId
     * @param array $validationResult
     */
    private function updateDocumentValidationResults($documentId, $validationResult)
    {
        // TODO: Implement validation result storage
        // For now, just log that this method was called
        error_log("Validation results update called for document $documentId - not implemented yet");
    }

    /**
     * Update LLM verification results in database (stub)
     *
     * @param string $documentId
     * @param array $llmResult
     */
    private function updateDocumentLLMVerificationResults($documentId, $llmResult)
    {
        // TODO: Implement LLM verification result storage
        // For now, just log that this method was called
        error_log("LLM verification results update called for document $documentId - not implemented yet");
    }
}
