<?php
/**
 * Document Verification System Configuration
 *
 * This file contains configuration for the future document verification system.
 * The system will support OCR processing, LLM-based verification, and trust scoring.
 */

// Environment detection (reuse existing pattern)
$host = $_SERVER['HTTP_HOST'] ?? '';
$isLocalhost = ($host === 'localhost' || preg_match('/\.local$/i', $host)) || php_sapi_name() === 'cli';

// File storage configuration
define('DOC_VERIFICATION_STORAGE_PATH', $isLocalhost
    ? __DIR__ . '/../../uploads/documents/'
    : '/home/spotyojn/domains/spotyourcargo.com/private/uploads/documents/');

// Feature flags for future capabilities
define('DOC_VERIFICATION_ENABLE_OCR', true); // Enable OCR processing
define('DOC_VERIFICATION_ENABLE_LLM', false); // TODO: Enable LLM verification
define('DOC_VERIFICATION_ENABLE_SCORING', true); // Enable trust scoring

// OCR Engine configuration (pluggable)
define('DOC_VERIFICATION_OCR_ENGINE', 'tesseract'); // Options: tesseract, google_vision, aws_textract
define('DOC_VERIFICATION_OCR_FALLBACK_ENABLED', true);

// LLM Provider configuration (optional and gated)
define('DOC_VERIFICATION_LLM_PROVIDER', 'openai'); // Options: openai, anthropic, google
define('DOC_VERIFICATION_LLM_MODEL', 'gpt-4'); // Model to use for verification

// Processing timeouts (in seconds)
define('DOC_VERIFICATION_OCR_TIMEOUT', 30);
define('DOC_VERIFICATION_LLM_TIMEOUT', 60);

// Trust scoring thresholds
define('DOC_VERIFICATION_MIN_TRUST_SCORE', 70); // Minimum score for approval
define('DOC_VERIFICATION_HIGH_TRUST_SCORE', 90); // Score considered very trustworthy

// Status-based trust score ceilings (mandatory post-processing control)
define('DOC_VERIFICATION_CEILING_TRUSTED', 100); // Max score when status is "trusted"
define('DOC_VERIFICATION_CEILING_REVIEW_REQUIRED', 79); // Max score when status is "review_required"
define('DOC_VERIFICATION_CEILING_REJECTED', 0); // Force score to 0 when status is "rejected" or "failed"

// Evidence-weighted trust scoring configuration
define('DOC_VERIFICATION_CONFIDENCE_THRESHOLD', 60); // Minimum confidence for full document weight
define('DOC_VERIFICATION_LOW_CONFIDENCE_WEIGHT', 0.2); // Weight multiplier for documents below threshold

// Diminishing returns curve for multiple documents (weight multipliers)
define('DOC_VERIFICATION_DIMINISHING_RETURNS', [
    1 => 1.0,   // First document: 100% weight
    2 => 0.7,   // Second document: 70% weight
    3 => 0.4,   // Third document: 40% weight
    4 => 0.2,   // Fourth+ documents: 20% weight each
]);

// Quality dominance rule thresholds
define('DOC_VERIFICATION_REQUIRED_DOC_THRESHOLD', 60); // Average confidence threshold for required documents

// Conflict penalty configuration
define('DOC_VERIFICATION_CONFLICT_PENALTY_LOW', 0.8); // ×0.8 for low conflicts
define('DOC_VERIFICATION_CONFLICT_PENALTY_HIGH', 0.6); // ×0.6 for high conflicts
define('DOC_VERIFICATION_CONFLICT_PENALTY_CRITICAL', 0.4); // ×0.4 for critical conflicts

// Cross-document consistency verification configuration
define('DOC_VERIFICATION_CROSS_DOCUMENT_CHECKS_ENABLED', true); // Enable/disable cross-document checks

// Conflict severity penalties (subtractive points)
define('DOC_VERIFICATION_CONFLICT_SEVERITY_MINOR', -5); // -5 points for minor conflicts
define('DOC_VERIFICATION_CONFLICT_SEVERITY_HIGH', -15); // -15 points for high conflicts
define('DOC_VERIFICATION_CONFLICT_SEVERITY_CRITICAL', -25); // -25 points for critical conflicts

// Document relationship mappings for conflict detection
define('DOC_VERIFICATION_DOCUMENT_RELATIONSHIPS', [
    'bill_of_lading' => ['commercial_invoice', 'packing_list'],
    'commercial_invoice' => ['bill_of_lading', 'packing_list', 'certificate_of_origin'],
    'packing_list' => ['bill_of_lading', 'commercial_invoice'],
    'business_license' => ['identity_card'],
    'identity_card' => ['business_license'],
    'certificate_of_origin' => ['commercial_invoice']
]);

// Field conflict rules - which fields should be compared between document types
define('DOC_VERIFICATION_FIELD_CONFLICT_RULES', [
    // Names and identifiers
    'name' => ['severity' => 'critical', 'fields' => ['name', 'company_name', 'shipper', 'consignee', 'buyer', 'seller', 'exporter', 'importer']],
    'identifier' => ['severity' => 'critical', 'fields' => ['number', 'license_number', 'bl_number', 'invoice_number', 'policy_number', 'certificate_number']],

    // Addresses
    'address' => ['severity' => 'high', 'fields' => ['address', 'port_of_loading', 'port_of_discharge']],

    // Dates
    'date' => ['severity' => 'high', 'fields' => ['issue_date', 'expiration_date', 'expiry_date', 'sailing_date', 'delivery_date', 'creation_date']],

    // Quantities and amounts
    'quantity' => ['severity' => 'high', 'fields' => ['quantity', 'total_amount', 'gross_weight', 'net_weight', 'coverage_amount']],

    // Other fields
    'description' => ['severity' => 'minor', 'fields' => ['description', 'cargo_description', 'goods_description']]
]);

// Maximum cumulative conflict penalty (to prevent over-penalty)
define('DOC_VERIFICATION_MAX_CONFLICT_PENALTY', -50); // Cap at -50 points total

// Time-based trust decay configuration
define('DOC_VERIFICATION_DECAY_ENABLED', true); // Enable/disable time-based decay
define('DOC_VERIFICATION_DECAY_TYPE', 'exponential'); // 'linear' or 'exponential'

// Decay curve breakpoints (in days)
define('DOC_VERIFICATION_DECAY_BREAKPOINTS', [
    'no_decay' => 30,      // 0-30 days: no decay
    'gradual' => 90,       // 30-90 days: gradual decay
    'aggressive' => 90     // 90+ days: aggressive decay
]);

// Decay rates (percentage reduction per day)
define('DOC_VERIFICATION_DECAY_RATES', [
    'gradual_linear' => 0.5,     // 0.5% per day for gradual linear decay
    'gradual_exponential' => 0.02, // Exponential decay factor for gradual period
    'aggressive_linear' => 1.5,    // 1.5% per day for aggressive linear decay
    'aggressive_exponential' => 0.05 // Exponential decay factor for aggressive period
]);

// Document-type specific decay sensitivities (multipliers)
define('DOC_VERIFICATION_DECAY_SENSITIVITIES', [
    'identity_card' => 1.0,      // Fast decay
    'passport' => 1.0,           // Fast decay
    'drivers_license' => 1.0,    // Fast decay
    'business_license' => 0.7,   // Slower decay
    'certificate' => 0.7,        // Slower decay
    'contract' => 0.5,           // Medium decay
    'invoice' => 0.5,            // Medium decay
    'bill_of_lading' => 0.5,     // Medium decay
    'commercial_invoice' => 0.5, // Medium decay
    'packing_list' => 0.5,       // Medium decay
    'certificate_of_origin' => 0.7, // Slower decay
    'insurance_certificate' => 0.7  // Slower decay
]);

// Reverification boost configuration
define('DOC_VERIFICATION_REVERIFICATION_BOOST_ENABLED', true);
define('DOC_VERIFICATION_REVERIFICATION_BOOST_FACTOR', 0.8); // Restore 80% of decay
define('DOC_VERIFICATION_REVERIFICATION_MAX_DAYS', 365); // Max days for boost eligibility

// File constraints for upload safety
define('DOC_VERIFICATION_MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB - configurable max upload size
define('DOC_VERIFICATION_ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png']); // Allowed MIME types for upload validation
define('DOC_VERIFICATION_BASE_STORAGE_PATH', $isLocalhost
    ? __DIR__ . '/../../uploads/documents/'
    : '/home/spotyojn/domains/spotyourcargo.com/private/uploads/documents/'); // Base path for secure file storage

// Database table names (to avoid conflicts with existing shipment verification)
define('DOC_VERIFICATION_DOCUMENTS_TABLE', 'document_verification_documents');
define('DOC_VERIFICATION_LOGS_TABLE', 'document_verification_logs');

// Processing status constants
define('DOC_STATUS_PENDING', 'pending');
define('DOC_STATUS_PROCESSING', 'processing');
define('DOC_STATUS_COMPLETED', 'completed');
define('DOC_STATUS_FAILED', 'failed');

// Document types (extensible)
define('DOC_TYPES', [
    'identity_card' => 'Identity Card',
    'passport' => 'Passport',
    'drivers_license' => 'Driver\'s License',
    'certificate' => 'Certificate',
    'contract' => 'Contract',
    'invoice' => 'Invoice'
]);
?>
