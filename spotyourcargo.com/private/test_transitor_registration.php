<?php
require_once 'db.php';

try {
    // Test data for transitor registration
    $testData = [
        'first_name' => 'Test',
        'last_name' => 'Transitor',
        'email' => 'test.transitor@example.com',
        'password' => 'TestPass123!',
        'user_type' => 'transitor',
        'company_name' => 'Test Logistics Co',
        'company_contact_name' => 'Test Transitor',
        'company_contact_email' => 'test.transitor@example.com'
    ];

    // Check if test user already exists
    $checkStmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $checkStmt->execute([$testData['email']]);
    if ($checkStmt->fetch()) {
        echo "Test transitor user already exists. Skipping creation.\n";
        exit;
    }

    // Generate SYC ID
    $prefix = 'SYC-T-';
    $stmt = $pdo->prepare("SELECT syc_id FROM users WHERE syc_id LIKE ? ORDER BY syc_id DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $last_user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($last_user) {
        $last_number = intval(substr($last_user['syc_id'], strlen($prefix)));
        $new_number = $last_number + 1;
    } else {
        $new_number = 1;
    }
    $syc_id = $prefix . str_pad($new_number, 4, '0', STR_PAD_LEFT);

    // Hash password
    $password_hash = password_hash($testData['password'], PASSWORD_DEFAULT);
    $created_at = date('Y-m-d H:i:s');

    // Insert user
    $userSql = "INSERT INTO users (syc_id, first_name, last_name, full_name, email, username, password_hash, user_type, company_name, company_contact_name, company_contact_email, created_at, email_verified)
               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

    $userStmt = $pdo->prepare($userSql);
    $userStmt->execute([
        $syc_id,
        $testData['first_name'],
        $testData['last_name'],
        $testData['first_name'] . ' ' . $testData['last_name'],
        $testData['email'],
        $testData['email'],
        $password_hash,
        $testData['user_type'],
        $testData['company_name'],
        $testData['company_contact_name'],
        $testData['company_contact_email'],
        $created_at
    ]);

    $user_id = $pdo->lastInsertId();

    // Insert into transitors table
    $transitorSql = "INSERT INTO transitors (user_id, syc_id, company_name, email, company_contact_name, company_contact_email, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";

    $transitorStmt = $pdo->prepare($transitorSql);
    $transitorStmt->execute([
        $user_id,
        $syc_id,
        $testData['company_name'],
        $testData['email'],
        $testData['company_contact_name'],
        $testData['company_contact_email'],
        $created_at
    ]);

    echo "Test transitor user created successfully!\n";
    echo "Email: " . $testData['email'] . "\n";
    echo "Password: " . $testData['password'] . "\n";
    echo "SYC ID: " . $syc_id . "\n";

} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
