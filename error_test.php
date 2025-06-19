<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test basic database connection
$host = 'localhost';
$user = 'u793478388_taskbil'; // Replace with your actual DB username
$pass = '5Xn+=U$S'; // Replace with your actual DB password
$db = 'u793478388_taskbill';       // Replace with your actual DB name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Database connection successful!";

// Test autoloader
try {
    require_once 'vendor/autoload.php';
    echo "<br>Autoloader loaded successfully!";
} catch (Exception $e) {
    echo "<br>Autoloader error: " . $e->getMessage();
}