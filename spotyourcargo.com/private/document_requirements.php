<?php
/**
 * Document Requirements Configuration
 *
 * Defines required and optional documents for shipment verification
 * This replaces hardcoded document types in the Drafts dashboard
 */

// Document requirements for shipments
$DOCUMENT_REQUIREMENTS = [
    'required' => [
        'Invoice' => [
            'label' => 'Commercial Invoice',
            'description' => 'Official invoice showing goods, prices, and terms'
        ],
        'Warehouse Release' => [
            'label' => 'Warehouse Release',
            'description' => 'Document authorizing release of goods from warehouse'
        ],
        'SAD' => [
            'label' => 'SAD (Single Administrative Document)',
            'description' => 'Customs declaration document'
        ]
    ],
    'optional' => [
        'Insurance' => [
            'label' => 'Insurance Certificate',
            'description' => 'Proof of cargo insurance coverage'
        ]
    ]
];

// Helper functions for document requirements
class DocumentRequirements
{
    private static $requirements = null;

    /**
     * Get all document requirements
     */
    public static function getAll()
    {
        if (self::$requirements === null) {
            global $DOCUMENT_REQUIREMENTS;
            self::$requirements = $DOCUMENT_REQUIREMENTS;
        }
        return self::$requirements;
    }

    /**
     * Get required documents only
     */
    public static function getRequired()
    {
        $all = self::getAll();
        return $all['required'] ?? [];
    }

    /**
     * Get optional documents only
     */
    public static function getOptional()
    {
        $all = self::getAll();
        return $all['optional'] ?? [];
    }

    /**
     * Get all document types (required + optional)
     */
    public static function getAllTypes()
    {
        $all = self::getAll();
        return array_merge(
            array_keys($all['required'] ?? []),
            array_keys($all['optional'] ?? [])
        );
    }

    /**
     * Check if a document type is required
     */
    public static function isRequired($docType)
    {
        $required = self::getRequired();
        return isset($required[$docType]);
    }

    /**
     * Check if a document type is optional
     */
    public static function isOptional($docType)
    {
        $optional = self::getOptional();
        return isset($optional[$docType]);
    }

    /**
     * Check if a document type is valid
     */
    public static function isValidType($docType)
    {
        return in_array($docType, self::getAllTypes());
    }

    /**
     * Get document label
     */
    public static function getLabel($docType)
    {
        $all = self::getAll();
        $allDocs = array_merge($all['required'], $all['optional']);
        return $allDocs[$docType]['label'] ?? $docType;
    }

    /**
     * Get document description
     */
    public static function getDescription($docType)
    {
        $all = self::getAll();
        $allDocs = array_merge($all['required'], $all['optional']);
        return $allDocs[$docType]['description'] ?? '';
    }

    /**
     * Get count of required documents
     */
    public static function getRequiredCount()
    {
        return count(self::getRequired());
    }

    /**
     * Get count of optional documents
     */
    public static function getOptionalCount()
    {
        return count(self::getOptional());
    }

    /**
     * Get total count of all documents
     */
    public static function getTotalCount()
    {
        return count(self::getAllTypes());
    }
}
?>
