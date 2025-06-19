<?php
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
$checkSql = "SELECT i.*, c.name as client_name, c.email as client_email, c.phone as client_phone, c.company as client_company, c.address as client_address 
             FROM invoices i 
             JOIN clients c ON i.client_id = c.id 
             WHERE i.id = ? AND i.user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $invoiceId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Invoice not found or you do not have permission to view this invoice.');
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

$invoice = $checkResult->fetch_assoc();

// Get invoice items
$itemsSql = "SELECT ii.*, t.title as task_title
             FROM invoice_items ii
             LEFT JOIN tasks t ON ii.task_id = t.id
             WHERE ii.invoice_id = ?";
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $invoiceId);
$itemsStmt->execute();
$items = $itemsStmt->get_result();

// Get user's company info from profile
$userSql = "SELECT company, phone FROM users WHERE id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

// Make sure uploads directory exists
$uploadsDir = __DIR__ . '/../../../uploads/invoices';
if (!is_dir($uploadsDir)) {
    mkdir($uploadsDir, 0755, true);
}

// Generate PDF using mPDF
require_once __DIR__ . '/../../vendor/autoload.php';

// Create new PDF instance
$mpdf = new \Mpdf\Mpdf([
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 10,
    'margin_bottom' => 10,
    'margin_header' => 0,
    'margin_footer' => 0
]);

// Set document information
$mpdf->SetTitle('Invoice #' . $invoice['invoice_number']);
$mpdf->SetAuthor(SITE_NAME);
$mpdf->SetCreator(SITE_NAME);

// Generate PDF content
$html = '
<style>
    body {
        font-family: Arial, sans-serif;
        font-size: 12pt;
        line-height: 1.5;
        color: #333;
    }
    .invoice-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
    }
    .invoice-title {
        font-size: 24pt;
        font-weight: bold;
        color: #0f172a;
        margin-bottom: 5px;
    }
    .invoice-status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 10pt;
        font-weight: bold;
        color: white;
    }
    .status-unpaid {
        background-color: #f59e0b;
    }
    .status-paid {
        background-color: #10b981;
    }
    .status-overdue {
        background-color: #ef4444;
    }
    .status-cancelled {
        background-color: #6b7280;
    }
    .invoice-company {
        text-align: right;
        font-size: 14pt;
    }
    .invoice-details {
        margin-bottom: 30px;
    }
    .bill-to {
        width: 40%;
        float: left;
    }
    .invoice-info {
        width: 40%;
        float: right;
        text-align: right;
    }
    .client-name {
        font-size: 14pt;
        font-weight: bold;
    }
    .clear {
        clear: both;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
    }
    table thead {
        background-color: #f8fafc;
    }
    table th, table td {
        padding: 8px;
        text-align: left;
        border-bottom: 1px solid #e2e8f0;
    }
    table th {
        font-weight: bold;
    }
    .text-right {
        text-align: right;
    }
    .text-center {
        text-align: center;
    }
    .invoice-total {
        width: 40%;
        float: right;
    }
    .invoice-total-row td {
        padding: 4px 8px;
    }
    .invoice-total-final td {
        font-weight: bold;
        font-size: 14pt;
        border-top: 2px solid #e2e8f0;
        padding-top: 8px;
    }
    .invoice-notes {
        margin-top: 40px;
        padding: 20px;
        background-color: #f8fafc;
        border-radius: 4px;
        font-size: 10pt;
    }
    .footer {
        margin-top: 40px;
        text-align: center;
        font-size: 10pt;
        color: #64748b;
    }
</style>

<div class="invoice-header">
    <div>
        <div class="invoice-title">Invoice #' . htmlspecialchars($invoice['invoice_number']) . '</div>
        <div>
            Status: 
            <span class="invoice-status status-' . strtolower($invoice['status']) . '">' . $invoice['status'] . '</span>
        </div>
    </div>
    <div class="invoice-company">
        <div>' . htmlspecialchars($user['company'] ? $user['company'] : SITE_NAME) . '</div>
        <div>' . htmlspecialchars($_SESSION['email']) . '</div>
        ' . (!empty($user['phone']) ? '<div>' . htmlspecialchars($user['phone']) . '</div>' : '') . '
    </div>
</div>

<div class="invoice-details">
    <div class="bill-to">
        <div style="color: #64748b;">Bill To:</div>
        <div class="client-name">' . htmlspecialchars($invoice['client_name']) . '</div>
        ' . (!empty($invoice['client_company']) ? '<div>' . htmlspecialchars($invoice['client_company']) . '</div>' : '') . '
        <div>' . htmlspecialchars($invoice['client_email']) . '</div>
        ' . (!empty($invoice['client_phone']) ? '<div>' . htmlspecialchars($invoice['client_phone']) . '</div>' : '') . '
        ' . (!empty($invoice['client_address']) ? '<div>' . nl2br(htmlspecialchars($invoice['client_address'])) . '</div>' : '') . '
    </div>
    
    <div class="invoice-info">
        <table>
            <tr>
                <td><strong>Invoice Date:</strong></td>
                <td>' . formatDate($invoice['issue_date']) . '</td>
            </tr>
            <tr>
                <td><strong>Due Date:</strong></td>
                <td>' . formatDate($invoice['due_date']) . '</td>
            </tr>
            ' . (!empty($invoice['sent_date']) ? '<tr><td><strong>Sent On:</strong></td><td>' . formatDateTime($invoice['sent_date']) . '</td></tr>' : '') . '
        </table>
    </div>
    
    <div class="clear"></div>
</div>

<table>
    <thead>
        <tr>
            <th style="width: 5%;">#</th>
            <th style="width: 45%;">Item</th>
            <th style="width: 10%;">Type</th>
            <th style="width: 10%;" class="text-right">Quantity</th>
            <th style="width: 15%;" class="text-right">Rate</th>
            <th style="width: 15%;" class="text-right">Amount</th>
        </tr>
    </thead>
    <tbody>';

$i = 1;
$items->data_seek(0);
while ($item = $items->fetch_assoc()) {
    $html .= '
        <tr>
            <td>' . $i++ . '</td>
            <td>
                ' . (!empty($item['task_title']) ? '<strong>' . htmlspecialchars($item['task_title']) . '</strong><br>' : '') . '
                ' . nl2br(htmlspecialchars($item['description'])) . '
            </td>
            <td>' . $item['rate_type'] . '</td>
            <td class="text-right">
                ' . $item['quantity'] . '
                ' . ($item['rate_type'] == 'Hourly' ? ' hrs' : '') . '
            </td>
            <td class="text-right">' . formatCurrency($item['rate']) . '</td>
            <td class="text-right">' . formatCurrency($item['amount']) . '</td>
        </tr>';
}

$html .= '
    </tbody>
</table>

<div class="invoice-total">
    <table>
        <tr class="invoice-total-row">
            <td class="text-right">Subtotal:</td>
            <td class="text-right" style="width: 100px;">' . formatCurrency($invoice['subtotal']) . '</td>
        </tr>';

if ($invoice['tax_rate'] > 0) {
    $html .= '
        <tr class="invoice-total-row">
            <td class="text-right">Tax (' . $invoice['tax_rate'] . '%):</td>
            <td class="text-right">' . formatCurrency($invoice['tax_amount']) . '</td>
        </tr>';
}

if ($invoice['discount'] > 0) {
    $html .= '
        <tr class="invoice-total-row">
            <td class="text-right">Discount:</td>
            <td class="text-right">' . formatCurrency($invoice['discount']) . '</td>
        </tr>';
}

$html .= '
        <tr class="invoice-total-row invoice-total-final">
            <td class="text-right">Total:</td>
            <td class="text-right">' . formatCurrency($invoice['total']) . '</td>
        </tr>
    </table>
</div>

<div class="clear"></div>';

if (!empty($invoice['notes'])) {
    $html .= '
<div class="invoice-notes">
    <strong>Notes:</strong><br>
    ' . nl2br(htmlspecialchars($invoice['notes'])) . '
</div>';
}

$html .= '
<div class="footer">
    &copy; ' . date('Y') . ' ' . SITE_NAME . '. All rights reserved.
</div>';

// Write HTML to PDF
$mpdf->WriteHTML($html);

// Save PDF to file for later use (email attachments)
$pdfPath = $uploadsDir . '/invoice_' . $invoiceId . '.pdf';
$mpdf->Output($pdfPath, \Mpdf\Output\Destination::FILE);

// Output PDF for download
$mpdf->Output('Invoice_' . $invoice['invoice_number'] . '.pdf', \Mpdf\Output\Destination::INLINE);
exit;