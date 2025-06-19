<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Authentication Functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function isVerified() {
    return isset($_SESSION['is_verified']) && $_SESSION['is_verified'] == 1;
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: " . SITE_URL . "/auth/login.php");
        exit;
    }
}

function redirectIfNotVerified() {
    if (isLoggedIn() && !isVerified()) {
        header("Location: " . SITE_URL . "/auth/verify.php");
        exit;
    }
}

function redirectIfNotAdmin() {
    if (!isAdmin()) {
        header("Location: " . SITE_URL . "/dashboard/index.php");
        exit;
    }
}

// Security Functions
function cleanInput($data) {
    global $conn;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    return $data;
}

function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function generateVerificationCode() {
    return sprintf("%06d", mt_rand(1, 999999));
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Email Functions
function sendEmail($to, $subject, $message) {
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendVerificationEmail($email, $code) {
    $subject = SITE_NAME . " - Email Verification";
    $message = "
    <html>
    <head>
        <title>Email Verification</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #0f172a; color: #fff; padding: 20px; text-align: center;'>
                <h1>" . SITE_NAME . " - Email Verification</h1>
            </div>
            <div style='padding: 20px; border: 1px solid #e2e8f0;'>
                <p>Thank you for registering with " . SITE_NAME . ". Please use the following code to verify your email address:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <div style='font-size: 24px; font-weight: bold; letter-spacing: 8px; padding: 10px; background-color: #f1f5f9; border: 1px solid #e2e8f0; display: inline-block;'>$code</div>
                </div>
                <p>If you did not request this code, please ignore this email.</p>
            </div>
            <div style='background-color: #f1f5f9; padding: 10px; text-align: center; font-size: 12px; color: #64748b;'>
                &copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

function sendPasswordResetEmail($email, $token) {
    $resetLink = SITE_URL . "/auth/reset-password.php?token=" . $token . "&email=" . urlencode($email);
    
    $subject = SITE_NAME . " - Password Reset";
    $message = "
    <html>
    <head>
        <title>Password Reset</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #0f172a; color: #fff; padding: 20px; text-align: center;'>
                <h1>" . SITE_NAME . " - Password Reset</h1>
            </div>
            <div style='padding: 20px; border: 1px solid #e2e8f0;'>
                <p>You have requested to reset your password. Please click the link below to reset your password:</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$resetLink' style='background-color: #0f172a; color: #fff; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Reset Password</a>
                </div>
                <p>If you did not request this password reset, please ignore this email.</p>
                <p>This link will expire in 1 hour.</p>
            </div>
            <div style='background-color: #f1f5f9; padding: 10px; text-align: center; font-size: 12px; color: #64748b;'>
                &copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

function sendInvoiceEmail($email, $invoiceNumber, $pdfPath, $clientName, $amount) {
    $subject = SITE_NAME . " - Invoice #" . $invoiceNumber;
    $message = "
    <html>
    <head>
        <title>Invoice #$invoiceNumber</title>
    </head>
    <body>
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background-color: #0f172a; color: #fff; padding: 20px; text-align: center;'>
                <h1>" . SITE_NAME . " - Invoice</h1>
            </div>
            <div style='padding: 20px; border: 1px solid #e2e8f0;'>
                <p>Dear $clientName,</p>
                <p>Please find attached your invoice #$invoiceNumber for the amount of $" . number_format($amount, 2) . ".</p>
                <p>If you have any questions, please don't hesitate to contact us.</p>
                <p>Thank you for your business!</p>
            </div>
            <div style='background-color: #f1f5f9; padding: 10px; text-align: center; font-size: 12px; color: #64748b;'>
                &copy; " . date('Y') . " " . SITE_NAME . ". All rights reserved.
            </div>
        </div>
    </body>
    </html>
    ";
    
    require_once __DIR__ . '/../vendor/autoload.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email, $clientName);
        
        // Attachment
        $mail->addAttachment($pdfPath, "Invoice-$invoiceNumber.pdf");
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Utility Functions
function generateInvoiceNumber() {
    return 'INV-' . date('Ymd') . '-' . sprintf("%04d", mt_rand(1, 9999));
}

function formatDate($date) {
    return date("M d, Y", strtotime($date));
}

function formatDateTime($datetime) {
    return date("M d, Y h:i A", strtotime($datetime));
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function calculateInvoiceTotal($subtotal, $taxRate, $discount) {
    $taxAmount = $subtotal * ($taxRate / 100);
    $total = $subtotal + $taxAmount - $discount;
    return [
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_amount' => $taxAmount,
        'discount' => $discount,
        'total' => $total
    ];
}

// Dashboard stat functions
function getTotalClients() {
    global $conn;
    $userId = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as count FROM clients WHERE user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getActiveTasks() {
    global $conn;
    $userId = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND status != 'Completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getPendingInvoices() {
    global $conn;
    $userId = $_SESSION['user_id'];
    $sql = "SELECT COUNT(*) as count FROM invoices WHERE user_id = ? AND status = 'Unpaid'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

function getOverdueTasks() {
    global $conn;
    $userId = $_SESSION['user_id'];
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as count FROM tasks WHERE user_id = ? AND due_date < ? AND status != 'Completed'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $today);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Flash messages
function setFlashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlashMessage() {
    $flash = getFlashMessage();
    if ($flash) {
        $type = $flash['type'];
        $message = $flash['message'];
        echo "<div class='alert alert-$type alert-dismissible fade show' role='alert'>
                $message
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}