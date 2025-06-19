<?php
// Database connection configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'u793478388_devinvoice');
define('DB_PASS', '~J2tLUUhhWqo');
define('DB_NAME', 'u793478388_devinvoice');

// Establish database connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Email configuration for SMTP
define('SMTP_HOST', 'smtp.hostinger.com');
define('SMTP_PORT', 465);
define('SMTP_USERNAME', 'no-reply@devinvoice.io');
define('SMTP_PASSWORD', 'Alphadev123!@#');
define('SMTP_FROM_EMAIL', 'no-reply@devinvoice.io');
define('SMTP_FROM_NAME', 'Dev Invoice');

// Application settings
define('SITE_URL', 'https://app.devinvoice.io');  // Added the missing slash
define('SITE_NAME', 'Dev Invoice');