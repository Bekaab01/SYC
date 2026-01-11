<?php
/**
 * Load Reference Generator for SpotYourCargo
 * Generates SYC-LD-XXXXXX format public load reference IDs
 */

/**
 * Generate a unique load reference ID
 * Format: SYC-LD-XXXXXX (where XXXXXX is random uppercase alphanumeric)
 *
 * @return string The generated load reference ID
 */
function generateLoadRef() {
    return 'SYC-LD-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Check if a load reference already exists in the database
 *
 * @param PDO $pdo Database connection
 * @param string $load_ref The load reference to check
 * @return bool True if exists, false otherwise
 */
function loadRefExists($pdo, $load_ref) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM shipments WHERE load_ref = ?");
    $stmt->execute([$load_ref]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Generate a unique load reference ID with collision checking
 *
 * @param PDO $pdo Database connection
 * @param int $max_attempts Maximum number of generation attempts (default: 10)
 * @return string The unique load reference ID
 * @throws Exception If unable to generate unique ID after max attempts
 */
function generateUniqueLoadRef($pdo, $max_attempts = 10) {
    for ($i = 0; $i < $max_attempts; $i++) {
        $load_ref = generateLoadRef();
        if (!loadRefExists($pdo, $load_ref)) {
            return $load_ref;
        }
    }
    throw new Exception("Unable to generate unique load reference after {$max_attempts} attempts");
}
?>
