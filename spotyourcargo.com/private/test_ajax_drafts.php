<?php
// Test script to directly access the AJAX drafts endpoint
include 'session_config.php';
session_start();

// Simulate shipper session
$_SESSION['user_id'] = 8;
$_SESSION['user_type'] = 'shipper';

// Include database connection
include 'db.php';

// Simulate GET parameters for AJAX request
$_GET['ajax'] = '1';
$_GET['tab'] = 'drafts';

// Include the shipper-dashboard.php to test AJAX loading
include '../public_html/shipper-dashboard.php';
?>
