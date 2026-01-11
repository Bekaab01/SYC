<?php
// Test script to simulate loading the drafts tab directly
include '../private/session_config.php';
session_start();

// Simulate shipper session
$_SESSION['user_id'] = 8;
$_SESSION['user_type'] = 'shipper';

// Include database connection
include '../private/db.php';

echo "<h1>Testing Drafts Tab Loading</h1>";
echo "<p>Session user_id: " . ($_SESSION['user_id'] ?? 'Not set') . "</p>";
echo "<p>Session user_type: " . ($_SESSION['user_type'] ?? 'Not set') . "</p>";

// Include the drafts.php file directly
echo "<h2>Including drafts.php:</h2>";
include '../public_html/shipper-tabs/drafts.php';
?>
