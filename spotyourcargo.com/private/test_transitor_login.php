<?php
require_once 'db.php';

try {
    // Test login credentials
    $email = 'test.transitor@example.com';
    $password = 'TestPass123!';

    // Check if user exists
    $stmt = $pdo->prepare('SELECT id, syc_id, full_name, email, password_hash, user_type, email_verified FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo "User not found.\n";
        exit;
    }

    echo "User found:\n";
    echo "ID: " . $user['id'] . "\n";
    echo "SYC ID: " . $user['syc_id'] . "\n";
    echo "Name: " . $user['full_name'] . "\n";
    echo "Email: " . $user['email'] . "\n";
    echo "User Type: " . $user['user_type'] . "\n";
    echo "Email Verified: " . ($user['email_verified'] ? 'Yes' : 'No') . "\n";

    // Test password verification
    if (password_verify($password, $user['password_hash'])) {
        echo "Password verification: SUCCESS\n";

        // Check transitor profile
        $transitor_stmt = $pdo->prepare("SELECT id, company_name FROM transitors WHERE user_id = ?");
        $transitor_stmt->execute([$user['id']]);
        $transitor = $transitor_stmt->fetch(PDO::FETCH_ASSOC);

        if ($transitor) {
            echo "Transitor profile found:\n";
            echo "Transitor ID: " . $transitor['id'] . "\n";
            echo "Company Name: " . $transitor['company_name'] . "\n";
        } else {
            echo "ERROR: Transitor profile not found!\n";
        }

    } else {
        echo "Password verification: FAILED\n";
    }

} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
}
?>
