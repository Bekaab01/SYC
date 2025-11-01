<?php
require_once 'db.php';

try {
    // Find transitor users without profiles
    $stmt = $pdo->prepare("
        SELECT u.id, u.syc_id, u.first_name, u.last_name, u.full_name, u.email,
               u.company_name, u.company_contact_name, u.company_contact_email
        FROM users u
        LEFT JOIN transitors t ON u.id = t.user_id
        WHERE u.user_type = 'transitor' AND t.id IS NULL
    ");
    $stmt->execute();
    $orphaned_transitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($orphaned_transitors) . " transitor users without profiles.\n\n";

    foreach ($orphaned_transitors as $user) {
        echo "Creating profile for: " . $user['full_name'] . " (" . $user['email'] . ")\n";

        // Insert into transitors table
        $insertStmt = $pdo->prepare("
            INSERT INTO transitors (user_id, syc_id, company_name, email, company_contact_name, company_contact_email, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $insertStmt->execute([
            $user['id'],
            $user['syc_id'],
            $user['company_name'] ?: $user['full_name'],
            $user['email'],
            $user['company_contact_name'] ?: $user['full_name'],
            $user['company_contact_email'] ?: $user['email']
        ]);

        echo "Profile created successfully.\n\n";
    }

    echo "All orphaned transitor profiles have been created.\n";

} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
