<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../includes/functions.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Check if invoice id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('danger', 'Invalid invoice ID.');
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

$invoiceId = $_GET['id'];

// Check if invoice belongs to user
$checkSql = "SELECT i.*, c.name as client_name, c.email as client_email
             FROM invoices i 
             JOIN clients c ON i.client_id = c.id 
             WHERE i.id = ? AND i.user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $invoiceId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Invoice not found or you do not have permission to send this invoice.');
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

$invoice = $checkResult->fetch_assoc();

// Make sure uploads directory exists
$uploadsDir = __DIR__ . '/../../../uploads/invoices';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Generate PDF path
$pdfPath = $uploadsDir . '/invoice_' . $invoiceId . '.pdf';

// Check if we need to generate the PDF first
if (!file_exists($pdfPath)) {
    // Try to generate the PDF using pdf.php
    // We don't need to include pdf.php here since that would output the PDF directly
    // Instead, we'll access the file directly by URL to generate it
    
    // Create a simple file_get_contents request to pdf.php to generate the PDF
    $pdfGenUrl = SITE_URL . '/dashboard/invoices/pdf.php?id=' . $invoiceId;
    @file_get_contents($pdfGenUrl);
    
    // Wait a brief moment for the file to be created
    sleep(1);
}

// Double-check if the PDF file exists
if (file_exists($pdfPath)) {
    // Send email with PDF attachment
    if (sendInvoiceEmail($invoice['client_email'], $invoice['invoice_number'], $pdfPath, $invoice['client_name'], $invoice['total'])) {
        // Update sent_date
        $updateSentSql = "UPDATE invoices SET sent_date = NOW() WHERE id = ?";
        $updateSentStmt = $conn->prepare($updateSentSql);
        $updateSentStmt->bind_param("i", $invoiceId);
        $updateSentStmt->execute();
        
        setFlashMessage('success', 'Invoice sent to client successfully.');
    } else {
        setFlashMessage('danger', 'Failed to send invoice email. Please try again. Check SMTP settings.');
    }
} else {
    setFlashMessage('danger', 'Failed to generate PDF for email. Check file permissions (uploaded folder should be writable).');
}

// Redirect back to invoice view
header("Location: " . SITE_URL . "/dashboard/invoices/view.php?id=" . $invoiceId);
exit;