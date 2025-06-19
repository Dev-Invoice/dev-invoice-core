<?php
$pageTitle = "View Task";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Check if task id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    setFlashMessage('danger', 'Invalid task ID.');
    header("Location: " . SITE_URL . "/dashboard/tasks");
    exit;
}

$taskId = $_GET['id'];

// Check if task belongs to user
$checkSql = "SELECT t.*, c.name as client_name, c.email as client_email, c.phone as client_phone 
             FROM tasks t 
             JOIN clients c ON t.client_id = c.id 
             WHERE t.id = ? AND t.user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $taskId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Task not found or you do not have permission to view this task.');
    header("Location: " . SITE_URL . "/dashboard/tasks");
    exit;
}

$task = $checkResult->fetch_assoc();

// Handle status update
if (isset($_GET['status']) && in_array($_GET['status'], ['Pending', 'In Progress', 'Completed'])) {
    $status = $_GET['status'];
    
    // Update task status
    $updateSql = "UPDATE tasks SET status = ? WHERE id = ? AND user_id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("sii", $status, $taskId, $_SESSION['user_id']);
    
    if ($updateStmt->execute()) {
        setFlashMessage('success', 'Task status updated successfully.');
        
        // Refresh task data
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $task = $checkResult->fetch_assoc();
    } else {
        setFlashMessage('danger', 'Failed to update task status. Please try again.');
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Task Details</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Tasks
                    </a>
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/edit.php?id=<?php echo $taskId; ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $taskId; ?>, '<?php echo htmlspecialchars($task['title']); ?>')" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                </div>
            </div>
            
            <!-- Task Information -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Task Information</h5>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="statusDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-tasks"></i> Change Status
                        </button>
                        <ul class="dropdown-menu" aria-labelledby="statusDropdown">
                            <li>
                                <a class="dropdown-item <?php echo $task['status'] == 'Pending' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $taskId; ?>&status=Pending">
                                    <span class="badge bg-warning me-2">Pending</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $task['status'] == 'In Progress' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $taskId; ?>&status=In Progress">
                                    <span class="badge bg-primary me-2">In Progress</span>
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item <?php echo $task['status'] == 'Completed' ? 'active' : ''; ?>" href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $taskId; ?>&status=Completed">
                                    <span class="badge bg-success me-2">Completed</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <th style="width: 150px;">Title:</th>
                                    <td><?php echo htmlspecialchars($task['title']); ?></td>
                                </tr>
                                <tr>
                                    <th>Client:</th>
                                    <td>
                                        <a href="<?php echo SITE_URL; ?>/dashboard/clients/view.php?id=<?php echo $task['client_id']; ?>">
                                            <?php echo htmlspecialchars($task['client_name']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client Email:</th>
                                    <td>
                                        <a href="mailto:<?php echo htmlspecialchars($task['client_email']); ?>">
                                            <?php echo htmlspecialchars($task['client_email']); ?>
                                        </a>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Client Phone:</th>
                                    <td>
                                        <?php if (!empty($task['client_phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($task['client_phone']); ?>">
                                                <?php echo htmlspecialchars($task['client_phone']); ?>
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
                                    <th style="width: 150px;">Due Date:</th>
                                    <td>
                                        <?php echo formatDate($task['due_date']); ?>
                                        <?php if (strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] != 'Completed'): ?>
                                            <span class="badge bg-danger">Overdue</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Priority:</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $task['priority'] == 'High' ? 'danger' : 
                                                ($task['priority'] == 'Medium' ? 'warning' : 'info'); 
                                        ?>">
                                            <?php echo $task['priority']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Status:</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $task['status'] == 'Completed' ? 'success' : 
                                                ($task['status'] == 'In Progress' ? 'primary' : 'warning'); 
                                        ?>">
                                            <?php echo $task['status']; ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Created:</th>
                                    <td><?php echo formatDateTime($task['created_at']); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Description:</h6>
                        <div class="p-3 bg-light rounded">
                            <?php if (!empty($task['description'])): ?>
                                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
                            <?php else: ?>
                                <span class="text-muted">No description provided</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Related Invoices -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Related Invoices</h5>
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php?task_id=<?php echo $taskId; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Create Invoice
                    </a>
                </div>
                <div class="card-body">
                    <?php
                    // Get related invoices
                    $invoicesSql = "SELECT i.id, i.invoice_number, i.issue_date, i.due_date, i.total, i.status 
                                    FROM invoices i 
                                    JOIN invoice_items ii ON i.id = ii.invoice_id 
                                    WHERE ii.task_id = ? AND i.user_id = ? 
                                    GROUP BY i.id 
                                    ORDER BY i.issue_date DESC";
                    $invoicesStmt = $conn->prepare($invoicesSql);
                    $invoicesStmt->bind_param("ii", $taskId, $_SESSION['user_id']);
                    $invoicesStmt->execute();
                    $invoices = $invoicesStmt->get_result();
                    
                    if ($invoices->num_rows > 0):
                    ?>
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
                            <p class="text-muted">No invoices found for this task.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php?task_id=<?php echo $taskId; ?>" class="btn btn-primary">
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
                Are you sure you want to delete task "<span id="taskTitle"></span>"? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(taskId, taskTitle) {
    document.getElementById('taskTitle').textContent = taskTitle;
    document.getElementById('confirmDeleteBtn').href = '<?php echo SITE_URL; ?>/dashboard/tasks/index.php?delete=' + taskId;
    var modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>