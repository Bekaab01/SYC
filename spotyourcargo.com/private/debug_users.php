<?php
require_once 'db.php';

try {
    $stmt = $pdo->query('SELECT COUNT(*) as count FROM users');
    $result = $stmt->fetch();
    echo 'Total users: ' . $result['count'] . PHP_EOL;

    $stmt = $pdo->query('SELECT id, syc_id, first_name, last_name, email, user_type FROM users LIMIT 5');
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($users as $user) {
        echo 'ID: ' . $user['id'] . ', SYC ID: ' . $user['syc_id'] . ', Name: ' . trim($user['first_name'] . ' ' . $user['last_name']) . ', Email: ' . $user['email'] . ', Type: ' . $user['user_type'] . PHP_EOL;
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
?>
