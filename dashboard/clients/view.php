<?php
$pageTitle = "View Client";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Check if client id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('danger', 'Invalid client ID.');
    header("Location: " . SITE_URL . "/dashboard/clients");
    exit;
}

$clientId = $_GET['id'];

// Check if client belongs to user
$checkSql = "SELECT * FROM clients WHERE id = ? AND user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $clientId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Client not found or you do not have permission to view this client.');
    header("Location: " . SITE_URL . "/dashboard/clients");
    exit;
}

$client = $checkResult->fetch_assoc();

// Get client's tasks
$tasksSql = "SELECT * FROM tasks WHERE client_id = ? ORDER BY due_date DESC";
$tasksStmt = $conn->prepare($tasksSql);
$tasksStmt->bind_param("i", $clientId);
$tasksStmt->execute();
$tasks = $tasksStmt->get_result();

// Get client's invoices
$invoicesSql = "SELECT * FROM invoices WHERE client_id = ? ORDER BY issue_date DESC";
$invoicesStmt = $conn->prepare($invoicesSql);
$invoicesStmt->bind_param("i", $clientId);
$invoicesStmt->execute();
$invoices = $invoicesStmt->get_result();
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Client Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/clients" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Clients
                    </a>
                    <a href="<?php echo SITE_URL; ?>/dashboard/clients/edit.php?id=<?php echo $clientId; ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $clientId; ?>, '<?php echo htmlspecialchars($client['name']); ?>')" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
            
            <!-- Client Information -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Client Information</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Name:</th>
                                    <td><?php echo htmlspecialchars($client['name']); ?></td>
                                </tr>
                                <tr>
                                    <th>Email:</th>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($client['email']); ?>">
                                            <?php echo htmlspecialchars($client['email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Phone:</th>
                                    <td>
                                        <?php if (!empty($client['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>">
                                                <?php echo htmlspecialchars($client['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Company:</th>
                                    <td>
                                        <?php if (!empty($client['company'])): ?>
                                            <?php echo htmlspecialchars($client['company']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Address:</th>
                                    <td>
                                        <?php if (!empty($client['address'])): ?>
                                            <?php echo nl2br(htmlspecialchars($client['address'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not provided</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Added On:</th>
                                    <td><?php echo formatDateTime($client['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Client Tasks -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Tasks</h5>
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Add Task
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($tasks->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Due Date</th>
                                        <th>Priority</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($task = $tasks->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($task['title']); ?></td>
                                            <td>
                                                <?php echo formatDate($task['due_date']); ?>
                                                <?php if (strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] != 'Completed'): ?>
                                                    <span class="badge bg-danger">Overdue</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $task['priority'] == 'High' ? 'danger' : 
                                                        ($task['priority'] == 'Medium' ? 'warning' : 'info'); 
                                                ?>">
                                                    <?php echo $task['priority']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    echo $task['status'] == 'Completed' ? 'success' : 
                                                        ($task['status'] == 'In Progress' ? 'primary' : 'warning'); 
                                                ?>">
                                                    <?php echo $task['status']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/tasks/edit.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No tasks found for this client.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Task
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Client Invoices -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Invoices</h5>
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Invoice
                    </a>
                </div>
                <div class="card-body">
                    <?php if ($invoices->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Invoice #</th>
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
                                                <a href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/invoices/edit.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/invoices/pdf.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">
                                                    <i class="fas fa-file-pdf"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No invoices found for this client.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary">
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
                Are you sure you want to delete client <span id="clientName"></span>? This will also delete all associated tasks and invoices. This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(clientId, clientName) {
    document.getElementById('clientName').textContent = clientName;
    document.getElementById('confirmDeleteBtn').href = '<?php echo SITE_URL; ?>/dashboard/clients/index.php?delete=' + clientId;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>