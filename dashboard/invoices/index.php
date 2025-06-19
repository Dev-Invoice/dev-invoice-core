<?php
$pageTitle = "Invoices";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Handle invoice deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $invoiceId = $_GET['delete'];
    
    // Check if invoice belongs to user
    $checkSql = "SELECT id FROM invoices WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $invoiceId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        // Delete invoice
        $deleteSql = "DELETE FROM invoices WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $invoiceId);
        
        if ($deleteStmt->execute()) {
            setFlashMessage('success', 'Invoice deleted successfully.');
        } else {
            setFlashMessage('danger', 'Failed to delete invoice. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Invoice not found or you do not have permission to delete this invoice.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

// Handle status update
if (isset($_GET['mark_paid']) && is_numeric($_GET['mark_paid'])) {
    $invoiceId = $_GET['mark_paid'];
    
    // Check if invoice belongs to user
    $checkSql = "SELECT id FROM invoices WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $invoiceId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        // Update invoice status
        $updateSql = "UPDATE invoices SET status = 'Paid' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $invoiceId);
        
        if ($updateStmt->execute()) {
            setFlashMessage('success', 'Invoice marked as paid.');
        } else {
            setFlashMessage('danger', 'Failed to update invoice status. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Invoice not found or you do not have permission to update this invoice.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

// Get filter parameters
$clientFilter = isset($_GET['client']) ? cleanInput($_GET['client']) : '';
$statusFilter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$dateFilter = isset($_GET['date']) ? cleanInput($_GET['date']) : '';
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Build query based on filters
$sql = "SELECT i.*, c.name as client_name FROM invoices i JOIN clients c ON i.client_id = c.id WHERE i.user_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

if (!empty($clientFilter)) {
    $sql .= " AND i.client_id = ?";
    $params[] = $clientFilter;
    $types .= "i";
}

if (!empty($statusFilter)) {
    $sql .= " AND i.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($dateFilter)) {
    if ($dateFilter === 'overdue') {
        $sql .= " AND i.due_date < CURDATE() AND i.status = 'Unpaid'";
    } elseif ($dateFilter === 'this_month') {
        $sql .= " AND MONTH(i.issue_date) = MONTH(CURDATE()) AND YEAR(i.issue_date) = YEAR(CURDATE())";
    } elseif ($dateFilter === 'last_month') {
        $sql .= " AND MONTH(i.issue_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(i.issue_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH))";
    } elseif ($dateFilter === 'this_year') {
        $sql .= " AND YEAR(i.issue_date) = YEAR(CURDATE())";
    }
}

if (!empty($search)) {
    $sql .= " AND (i.invoice_number LIKE ? OR c.name LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Order by issue date
$sql .= " ORDER BY i.issue_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$invoices = $stmt->get_result();

// Get clients for filter dropdown
$clientsSql = "SELECT id, name FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result();

// Calculate total due amount
$totalDueSql = "SELECT SUM(total) as total_due FROM invoices WHERE user_id = ? AND status = 'Unpaid'";
$totalDueStmt = $conn->prepare($totalDueSql);
$totalDueStmt->bind_param("i", $_SESSION['user_id']);
$totalDueStmt->execute();
$totalDueResult = $totalDueStmt->get_result();
$totalDue = $totalDueResult->fetch_assoc()['total_due'] ?? 0;
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Invoices</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Invoice
                    </a>
                </div>
            </div>
            
            <!-- Invoice Summary -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center border-end">
                            <h3 class="text-primary"><?php echo $invoices->num_rows; ?></h3>
                            <p class="text-muted mb-0">Total Invoices</p>
                        </div>
                        <div class="col-md-3 text-center border-end">
                            <h3 class="text-success">
                                <?php 
                                $paidCount = 0;
                                $unpaidCount = 0;
                                $overdueCount = 0;
                                $invoicesTemp = $invoices;
                                while($row = $invoicesTemp->fetch_assoc()) {
                                    if($row['status'] === 'Paid') $paidCount++;
                                    elseif($row['status'] === 'Unpaid') {
                                        $unpaidCount++;
                                        if(strtotime($row['due_date']) < strtotime(date('Y-m-d'))) {
                                            $overdueCount++;
                                        }
                                    }
                                }
                                $invoices->data_seek(0);
                                echo $paidCount;
                                ?>
                            </h3>
                            <p class="text-muted mb-0">Paid Invoices</p>
                        </div>
                        <div class="col-md-3 text-center border-end">
                            <h3 class="text-warning"><?php echo $unpaidCount; ?></h3>
                            <p class="text-muted mb-0">Unpaid Invoices</p>
                        </div>
                        <div class="col-md-3 text-center">
                            <h3 class="text-danger"><?php echo formatCurrency($totalDue); ?></h3>
                            <p class="text-muted mb-0">Total Due</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                        <div class="col-md-3">
                            <select name="client" class="form-select">
                                <option value="">All Clients</option>
                                <?php 
                                $clients->data_seek(0);
                                while ($client = $clients->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Unpaid" <?php echo $statusFilter == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                <option value="Paid" <?php echo $statusFilter == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                <option value="Overdue" <?php echo $statusFilter == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="Cancelled" <?php echo $statusFilter == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="date" class="form-select">
                                <option value="">All Dates</option>
                                <option value="overdue" <?php echo $dateFilter == 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                                <option value="this_month" <?php echo $dateFilter == 'this_month' ? 'selected' : ''; ?>>This Month</option>
                                <option value="last_month" <?php echo $dateFilter == 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="this_year" <?php echo $dateFilter == 'this_year' ? 'selected' : ''; ?>>This Year</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search invoices..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Invoices List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Invoices</h5>
                </div>
                <div class="card-body">
                    <?php if ($invoices->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
                                        <th>Client</th>
                                        <th>Issue Date</th>
                                        <th>Due Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($invoice = $invoices->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                            <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                                            <td><?php echo formatDate($invoice['issue_date']); ?></td>
                                            <td>
                                                <?php echo formatDate($invoice['due_date']); ?>
                                                <?php if (strtotime($invoice['due_date']) < strtotime(date('Y-m-d')) && $invoice['status'] == 'Unpaid'): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo formatCurrency($invoice['total']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $invoice['status'] == 'Paid' ? 'success' : 
                                                        ($invoice['status'] == 'Overdue' ? 'danger' : 
                                                            ($invoice['status'] == 'Cancelled' ? 'secondary' : 'warning')); 
                                                ?>">
                                                    <?php echo $invoice['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/pdf.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </a>
                                                    <?php if ($invoice['status'] == 'Unpaid'): ?>
                                                        <a href="javascript:void(0);" onclick="confirmPaid(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" class="btn btn-sm btn-success" title="Mark as Paid">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $invoice['id']; ?>, '<?php echo htmlspecialchars($invoice['invoice_number']); ?>')" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No invoices found.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Create Invoice
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete invoice "<span id="invoiceNumber"></span>"? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Confirmation Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1" aria-labelledby="markPaidModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="markPaidModalLabel">Confirm Mark as Paid</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to mark invoice "<span id="paidInvoiceNumber"></span>" as paid?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmPaidBtn" class="btn btn-success">Mark as Paid</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(invoiceId, invoiceNumber) {
    document.getElementById('invoiceNumber').textContent = invoiceNumber;
    document.getElementById('confirmDeleteBtn').href = '<?php echo SITE_URL; ?>/dashboard/invoices/index.php?delete=' + invoiceId;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}

function confirmPaid(invoiceId, invoiceNumber) {
    document.getElementById('paidInvoiceNumber').textContent = invoiceNumber;
    document.getElementById('confirmPaidBtn').href = '<?php echo SITE_URL; ?>/dashboard/invoices/index.php?mark_paid=' + invoiceId;
    var modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>