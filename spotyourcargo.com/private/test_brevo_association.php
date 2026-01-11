<?php
require_once 'brevo_simple.php';

$brevo = new BrevoSimpleEmail();
$result = $brevo->sendAssociationNotification([
    'email' => 'bekahunde01@gmail.com', // Replace with your test email
    'name' => 'Test Association'
], 'approve', 'Test approval notes', 'approved');

echo 'Result: ' . json_encode($result) . PHP_EOL;
?>
