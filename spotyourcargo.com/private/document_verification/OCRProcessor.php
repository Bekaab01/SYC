<?php
/**
 * OCR Processor
 *
 * Handles Optical Character Recognition processing for documents.
 * Supports pluggable OCR engines (Tesseract, Google Vision, AWS Textract).
 */

require_once __DIR__ . '/config.php';

class OCRProcessor
{
    private $engines = [];
    private $pdo;

    public function __construct($pdo = null)
    {
        $this->pdo = $pdo;

        // Initialize OCR engines
        $this->engines = [
            'tesseract' => new TesseractEngine(),
            'google_vision' => new GoogleVisionEngine(),
            'aws_textract' => new AWSTextractEngine()
        ];
    }

    /**
     * Process document with OCR
     *
     * @param string $documentId
     * @param string $filePath
     * @return array OCR result
     */
    public function processDocument($documentId, $filePath)
    {
        if (!DOC_VERIFICATION_ENABLE_OCR) {
            return [
                'success' => false,
                'message' => 'OCR processing is disabled',
                'extracted_text' => null,
                'confidence' => 0,
                'processing_time' => 0,
                'warnings' => ['OCR disabled in configuration']
            ];
        }

        $startTime = microtime(true);
        $warnings = [];

        try {
            // Determine file type and select appropriate engine
            $fileInfo = pathinfo($filePath);
            $mimeType = mime_content_type($filePath);

            $engine = DOC_VERIFICATION_OCR_ENGINE;

            // Validate file exists and is readable
            if (!file_exists($filePath) || !is_readable($filePath)) {
                throw new Exception("File not found or not readable: $filePath");
            }

            // Process with selected engine
            $result = $this->engines[$engine]->process($filePath, $mimeType);

            $processingTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

            // Log OCR operation
            $this->logOCRProcessing($documentId, $engine, $result, $processingTime, $warnings);

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'extracted_text' => $result['text'],
                'confidence' => $result['confidence'],
                'processing_time' => $processingTime,
                'warnings' => array_merge($warnings, $result['warnings'] ?? [])
            ];

        } catch (Exception $e) {
            $processingTime = (microtime(true) - $startTime) * 1000;
            $warnings[] = 'OCR processing failed: ' . $e->getMessage();

            // Log failed OCR operation
            $this->logOCRProcessing($documentId, $engine ?? DOC_VERIFICATION_OCR_ENGINE, [
                'success' => false,
                'message' => $e->getMessage(),
                'text' => null,
                'confidence' => 0,
                'warnings' => $warnings
            ], $processingTime, $warnings);

            return [
                'success' => false,
                'message' => 'OCR processing failed: ' . $e->getMessage(),
                'extracted_text' => null,
                'confidence' => 0,
                'processing_time' => $processingTime,
                'warnings' => $warnings
            ];
        }
    }

    /**
     * Get available OCR engines
     *
     * @return array List of available engines
     */
    public function getAvailableEngines()
    {
        return array_keys($this->engines);
    }

    /**
     * Test OCR engine connectivity
     *
     * @param string $engineName
     * @return bool
     */
    public function testEngine($engineName)
    {
        if (!isset($this->engines[$engineName])) {
            return false;
        }

        return $this->engines[$engineName]->testConnectivity();
    }

    /**
     * Log OCR processing operation
     *
     * @param string $documentId
     * @param string $engine
     * @param array $result
     * @param float $processingTime
     * @param array $warnings
     */
    private function logOCRProcessing($documentId, $engine, $result, $processingTime, $warnings)
    {
        if (!$this->pdo) {
            return; // Skip logging if no database connection
        }

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO document_verification_logs
                (document_id, action, status, details, processing_time_ms, created_at)
                VALUES (?, 'ocr_processing', ?, ?, ?, NOW())
            ");

            $status = $result['success'] ? 'completed' : 'failed';
            $details = json_encode([
                'engine' => $engine,
                'confidence' => $result['confidence'] ?? 0,
                'warnings' => $warnings,
                'text_length' => strlen($result['text'] ?? '')
            ]);

            $stmt->execute([$documentId, $status, $details, (int)$processingTime]);

        } catch (Exception $e) {
            error_log("Failed to log OCR processing: " . $e->getMessage());
        }
    }
}

// Tesseract OCR Engine Implementation
class TesseractEngine
{
    public function process($filePath, $mimeType) {
        $warnings = [];

        try {
            // Determine if file needs preprocessing
            $isPdf = $mimeType === 'application/pdf';
            $isImage = in_array($mimeType, ['image/jpeg', 'image/png']);

            if (!$isPdf && !$isImage) {
                throw new Exception("Unsupported file type for Tesseract: $mimeType");
            }

            $tempDir = sys_get_temp_dir() . '/ocr_' . uniqid();
            if (!mkdir($tempDir, 0755, true)) {
                throw new Exception("Failed to create temp directory");
            }

            $output = [];
            $returnCode = 0;

            if ($isPdf) {
                // Convert PDF to images first using ImageMagick or pdftoppm
                $imagePath = $tempDir . '/page.png';

                // Try pdftoppm first (usually available on Linux)
                $cmd = "pdftoppm -png -f 1 -l 1 \"$filePath\" \"$tempDir/page\" 2>&1";
                exec($cmd, $output, $returnCode);

                if ($returnCode !== 0) {
                    // Fallback to ImageMagick
                    $cmd = "convert -density 300 \"$filePath\"[0] \"$imagePath\" 2>&1";
                    exec($cmd, $output, $returnCode);

                    if ($returnCode !== 0) {
                        throw new Exception("Failed to convert PDF to image: " . implode("\n", $output));
                    }
                } else {
                    $imagePath = $tempDir . '/page-1.png';
                }

                $filePath = $imagePath;
            }

            // Run Tesseract OCR
            $textFile = $tempDir . '/output';
            $cmd = "tesseract \"$filePath\" \"$textFile\" -l eng --psm 6 txt 2>&1";
            exec($cmd, $output, $returnCode);

            if ($returnCode !== 0) {
                throw new Exception("Tesseract failed: " . implode("\n", $output));
            }

            $text = file_get_contents($textFile . '.txt');
            if ($text === false) {
                throw new Exception("Failed to read OCR output file");
            }

            // Clean up temp files
            $this->cleanupTempFiles($tempDir);

            // Calculate confidence (Tesseract doesn't provide direct confidence, use text length as proxy)
            $confidence = strlen(trim($text)) > 0 ? 85.0 : 0.0; // Default confidence

            return [
                'success' => true,
                'text' => trim($text),
                'confidence' => $confidence,
                'message' => 'OCR completed successfully',
                'warnings' => $warnings
            ];

        } catch (Exception $e) {
            // Clean up temp files on error
            if (isset($tempDir) && is_dir($tempDir)) {
                $this->cleanupTempFiles($tempDir);
            }

            return [
                'success' => false,
                'text' => '',
                'confidence' => 0,
                'message' => $e->getMessage(),
                'warnings' => array_merge($warnings, ['Tesseract processing failed'])
            ];
        }
    }

    public function testConnectivity() {
        $output = [];
        $returnCode = 0;
        exec("tesseract --version 2>&1", $output, $returnCode);
        return $returnCode === 0;
    }

    private function cleanupTempFiles($tempDir) {
        if (is_dir($tempDir)) {
            $files = glob($tempDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($tempDir);
        }
    }
}

// Stub implementations for other engines (to be implemented later)
class GoogleVisionEngine
{
    public function process($filePath, $mimeType) {
        return [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'message' => 'Google Vision API not yet implemented',
            'warnings' => ['Engine not implemented']
        ];
    }

    public function testConnectivity() {
        return false;
    }
}

class AWSTextractEngine
{
    public function process($filePath, $mimeType) {
        return [
            'success' => false,
            'text' => '',
            'confidence' => 0,
            'message' => 'AWS Textract not yet implemented',
            'warnings' => ['Engine not implemented']
        ];
    }

    public function testConnectivity() {
        return false;
    }
}
