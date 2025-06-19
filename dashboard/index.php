<?php
$pageTitle = "Dashboard";
require_once __DIR__ . '/../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Get dashboard statistics
$totalClients = getTotalClients();
$activeTasks = getActiveTasks();
$pendingInvoices = getPendingInvoices();
$overdueTasks = getOverdueTasks();

// Get recent tasks
$sql = "SELECT t.*, c.name as client_name 
        FROM tasks t 
        JOIN clients c ON t.client_id = c.id 
        WHERE t.user_id = ? 
        ORDER BY t.due_date ASC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$recentTasks = $stmt->get_result();

// Get unpaid invoices
$sql = "SELECT i.*, c.name as client_name 
        FROM invoices i 
        JOIN clients c ON i.client_id = c.id 
        WHERE i.user_id = ? AND i.status = 'Unpaid' 
        ORDER BY i.due_date ASC 
        LIMIT 5";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$unpaidInvoices = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="row">
        <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
        
        <main class="main-content px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Dashboard</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus-circle"></i> New Task
                        </a>
                        <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-file-invoice-dollar"></i> New Invoice
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card clients">
                        <div>
                            <h2 class="stat-value"><?php echo $totalClients; ?></h2>
                            <div class="stat-label">Total Clients</div>
                        </div>
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card tasks">
                        <div>
                            <h2 class="stat-value"><?php echo $activeTasks; ?></h2>
                            <div class="stat-label">Active Tasks</div>
                        </div>
                        <i class="fas fa-tasks"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card invoices">
                        <div>
                            <h2 class="stat-value"><?php echo $pendingInvoices; ?></h2>
                            <div class="stat-label">Pending Invoices</div>
                        </div>
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6 mb-4">
                    <div class="stat-card overdue">
                        <div>
                            <h2 class="stat-value"><?php echo $overdueTasks; ?></h2>
                            <div class="stat-label">Overdue Tasks</div>
                        </div>
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Recent Tasks -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Recent Tasks</h5>
                            <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($recentTasks->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Task</th>
                                                <th>Due Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($task = $recentTasks->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($task['client_name']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $task['id']; ?>">
                                                            <?php echo htmlspecialchars($task['title']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php echo formatDate($task['due_date']); ?>
                                                        <?php if (strtotime($task['due_date']) < strtotime(date('Y-m-d')) && $task['status'] != 'Completed'): ?>
                                                            <span class="badge bg-danger">Overdue</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?php 
                                                            echo $task['status'] == 'Completed' ? 'success' : 
                                                                ($task['status'] == 'In Progress' ? 'primary' : 'warning'); 
                                                        ?>">
                                                            <?php echo $task['status']; ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-muted">No tasks found.</p>
                                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus-circle"></i> Add Task
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Unpaid Invoices -->
                <div class="col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">Unpaid Invoices</h5>
                            <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="btn btn-sm btn-outline-primary">View All</a>
                        </div>
                        <div class="card-body">
                            <?php if ($unpaidInvoices->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Client</th>
                                                <th>Invoice</th>
                                                <th>Due Date</th>
                                                <th>Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while ($invoice = $unpaidInvoices->fetch_assoc()): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                                                    <td>
                                                        <a href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoice['id']; ?>">
                                                            <?php echo htmlspecialchars($invoice['invoice_number']); ?>
                                                        </a>
                                                    </td>
                                                    <td>
                                                        <?php echo formatDate($invoice['due_date']); ?>
                                                        <?php if (strtotime($invoice['due_date']) < strtotime(date('Y-m-d'))): ?>
                                                            <span class="badge bg-danger">Overdue</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo formatCurrency($invoice['total']); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <p class="text-muted">No unpaid invoices found.</p>
                                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/add.php" class="btn btn-primary btn-sm">
                                        <i class="fas fa-plus-circle"></i> Create Invoice
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>