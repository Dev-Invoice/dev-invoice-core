<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Test each component individually
echo "<h3>Component Testing</h3>";

// Test database configuration
echo "<h4>Testing database config</h4>";
try {
    require_once 'config/db.php';
    echo "Database config loaded successfully!";
} catch (Exception $e) {
    echo "Database config error: " . $e->getMessage();
}

// Test functions
echo "<h4>Testing functions</h4>";
try {
    require_once 'includes/functions.php';
    echo "Functions loaded successfully!";
} catch (Exception $e) {
    echo "Functions error: " . $e->getMessage();
}

// Test PHP libraries
echo "<h4>Testing libraries</h4>";
try {
    require_once 'vendor/autoload.php';
    
    // Test mPDF
    echo "<br>Testing mPDF: ";
    $mpdf = new \Mpdf\Mpdf();
    echo "OK";
    
    // Test PHPMailer
    echo "<br>Testing PHPMailer: ";
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    echo "OK";
} catch (Exception $e) {
    echo "Library error: " . $e->getMessage();
}