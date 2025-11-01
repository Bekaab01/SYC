<?php
// Test database connection and users query
include_once __DIR__ . '/../../spotyourcargo.com/private/db.php';

echo "Testing database connection...<br>";

try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $result = $stmt->fetch();
    echo "Total users: " . $result['count'] . "<br>";

    $stmt = $pdo->query("SELECT id, syc_id, first_name, last_name, email, user_type FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Users:</h3>";
    foreach($users as $user) {
        echo "ID: " . $user['id'] . ", SYC ID: " . $user['syc_id'] . ", Name: " . trim($user['first_name'] . ' ' . $user['last_name']) . ", Email: " . $user['email'] . ", Type: " . $user['user_type'] . "<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
