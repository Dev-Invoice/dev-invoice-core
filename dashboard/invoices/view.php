<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$pageTitle = "View Invoice";
require_once __DIR__ . '/../../includes/header.php';

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

// Handle status update
if (isset($_GET['status']) && in_array($_GET['status'], ['Unpaid', 'Paid', 'Overdue', 'Cancelled'])) {
    $status = $_GET['status'];
    
    // Update invoice status
    $updateSql = "UPDATE invoices SET status = ? WHERE id = ? AND user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sii", $status, $invoiceId, $_SESSION['user_id']);
    
    if ($updateStmt->execute()) {
        setFlashMessage('success', 'Invoice status updated successfully.');
        
        // Refresh invoice data
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $invoice = $checkResult->fetch_assoc();
    } else {
        setFlashMessage('danger', 'Failed to update invoice status. Please try again.');
    }
}

// Handle send invoice email
if (isset($_GET['send_email']) && $_GET['send_email'] == 1) {
    // Generate PDF first
    $pdfPath = __DIR__ . '/../../../uploads/invoices/invoice_' . $invoiceId . '.pdf';
    
    // Include PDF library (using html2pdf)
    require_once __DIR__ . '/pdf.php';
    
    // Create PDF content and save to file
    if (file_exists($pdfPath)) {
        // Send email with PDF attachment
        if (sendInvoiceEmail($invoice['client_email'], $invoice['invoice_number'], $pdfPath, $invoice['client_name'], $invoice['total'])) {
            // Update sent_date
            $updateSentSql = "UPDATE invoices SET sent_date = NOW() WHERE id = ?";
            $updateSentStmt = $conn->prepare($updateSentSql);
            $updateSentStmt->bind_param("i", $invoiceId);
            $updateSentStmt->execute();
            
            setFlashMessage('success', 'Invoice sent to client successfully.');
            
            // Refresh invoice data
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $invoice = $checkResult->fetch_assoc();
        } else {
            setFlashMessage('danger', 'Failed to send invoice email. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Failed to generate PDF for email. Please try again.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/invoices/view.php?id=" . $invoiceId);
    exit;
}

// Get user's company info from profile
$userSql = "SELECT company, phone FROM users WHERE id = ?";
$userStmt = $conn->prepare($userSql);
$userStmt->bind_param("i", $_SESSION['user_id']);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Invoice Details</h1>
                                    <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Invoices
                    </a>
                    <div class="btn-group me-2">
                        <a href="<?php echo SITE_URL; ?>/dashboard/invoices/edit.php?id=<?php echo $invoiceId; ?>" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <a href="<?php echo SITE_URL; ?>/dashboard/invoices/pdf.php?id=<?php echo $invoiceId; ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                            <i class="fas fa-file-pdf"></i> Download PDF
                        </a>
                        <!--<a href="<?php //echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php //echo $invoiceId; ?>&send_email=1" class="btn btn-sm btn-outline-info">-->
                        <!--    <i class="fas fa-envelope"></i> Send to Client-->
                        <!--</a>-->
                        <a href="<?php echo SITE_URL; ?>/dashboard/invoices/send.php?id=<?php echo $invoiceId; ?>" class="btn btn-sm btn-outline-info">
                            <i class="fas fa-envelope"></i> Send to Client
                        </a>
                        <div class="btn-group">
                            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-check-circle"></i> Status
                            </button>
                            <ul class="dropdown-menu">
                                <li>
                                    <a class="dropdown-item <?php echo $invoice['status'] == 'Unpaid' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoiceId; ?>&status=Unpaid">
                                        <span class="badge bg-warning me-2">Unpaid</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $invoice['status'] == 'Paid' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoiceId; ?>&status=Paid">
                                        <span class="badge bg-success me-2">Paid</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $invoice['status'] == 'Overdue' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoiceId; ?>&status=Overdue">
                                        <span class="badge bg-danger me-2">Overdue</span>
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item <?php echo $invoice['status'] == 'Cancelled' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoiceId; ?>&status=Cancelled">
                                        <span class="badge bg-secondary me-2">Cancelled</span>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Invoice Preview -->
            <div class="invoice-preview mb-4">
                <div class="invoice-header">
                    <div>
                        <div class="invoice-title">Invoice #<?php echo htmlspecialchars($invoice['invoice_number']); ?></div>
                        <div>Status: 
                            <span class="badge bg-<?php 
                                echo $invoice['status'] == 'Paid' ? 'success' : 
                                    ($invoice['status'] == 'Overdue' ? 'danger' : 
                                        ($invoice['status'] == 'Cancelled' ? 'secondary' : 'warning')); 
                            ?>">
                                <?php echo $invoice['status']; ?>
                            </span>
                        </div>
                    </div>
                    <div class="invoice-company">
                        <div class="h5"><?php echo htmlspecialchars($user['company'] ? $user['company'] : SITE_NAME); ?></div>
                        <div><?php echo htmlspecialchars($_SESSION['email']); ?></div>
                        <?php if (!empty($user['phone'])): ?>
                            <div><?php echo htmlspecialchars($user['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <div class="text-muted">Bill To:</div>
                            <div class="h5"><?php echo htmlspecialchars($invoice['client_name']); ?></div>
                            <?php if (!empty($invoice['client_company'])): ?>
                                <div><?php echo htmlspecialchars($invoice['client_company']); ?></div>
                            <?php endif; ?>
                            <div><?php echo htmlspecialchars($invoice['client_email']); ?></div>
                            <?php if (!empty($invoice['client_phone'])): ?>
                                <div><?php echo htmlspecialchars($invoice['client_phone']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($invoice['client_address'])): ?>
                                <div><?php echo nl2br(htmlspecialchars($invoice['client_address'])); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless invoice-details-table">
                            <tr>
                                <td><strong>Invoice Date:</strong></td>
                                <td><?php echo formatDate($invoice['issue_date']); ?></td>
                            </tr>
                            <tr>
                                <td><strong>Due Date:</strong></td>
                                <td>
                                    <?php echo formatDate($invoice['due_date']); ?>
                                    <?php if (strtotime($invoice['due_date']) < strtotime(date('Y-m-d')) && $invoice['status'] == 'Unpaid'): ?>
                                        <span class="badge bg-danger">Overdue</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if (!empty($invoice['sent_date'])): ?>
                                <tr>
                                    <td><strong>Sent On:</strong></td>
                                    <td><?php echo formatDateTime($invoice['sent_date']); ?></td>
                                </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                </div>
                
                <div class="invoice-items">
                    <table class="table">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Type</th>
                                <th class="text-end">Quantity</th>
                                <th class="text-end">Rate</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $i = 1;
                            while ($item = $items->fetch_assoc()): 
                            ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td>
                                        <?php if (!empty($item['task_title'])): ?>
                                            <strong><?php echo htmlspecialchars($item['task_title']); ?></strong><br>
                                        <?php endif; ?>
                                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                                    </td>
                                    <td><?php echo $item['rate_type']; ?></td>
                                    <td class="text-end">
                                        <?php echo $item['quantity']; ?>
                                        <?php echo $item['rate_type'] == 'Hourly' ? ' hrs' : ''; ?>
                                    </td>
                                    <td class="text-end"><?php echo formatCurrency($item['rate']); ?></td>
                                    <td class="text-end"><?php echo formatCurrency($item['amount']); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="invoice-total">
                    <table class="table table-borderless">
                        <tr class="invoice-total-row">
                            <td class="invoice-total-label">Subtotal:</td>
                            <td class="invoice-total-value"><?php echo formatCurrency($invoice['subtotal']); ?></td>
                        </tr>
                        <?php if ($invoice['tax_rate'] > 0): ?>
                            <tr class="invoice-total-row">
                                <td class="invoice-total-label">Tax (<?php echo $invoice['tax_rate']; ?>%):</td>
                                <td class="invoice-total-value"><?php echo formatCurrency($invoice['tax_amount']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if ($invoice['discount'] > 0): ?>
                            <tr class="invoice-total-row">
                                <td class="invoice-total-label">Discount:</td>
                                <td class="invoice-total-value"><?php echo formatCurrency($invoice['discount']); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr class="invoice-total-row invoice-total-final">
                            <td class="invoice-total-label">Total:</td>
                            <td class="invoice-total-value"><?php echo formatCurrency($invoice['total']); ?></td>
                        </tr>
                    </table>
                </div>
                
                <?php if (!empty($invoice['notes'])): ?>
                    <div class="invoice-notes">
                        <strong>Notes:</strong><br>
                        <?php echo nl2br(htmlspecialchars($invoice['notes'])); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>