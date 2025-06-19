<?php

$pageTitle = "Create Invoice";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

$error = '';
$success = '';

// Get client ID from URL if provided
$selectedClientId = isset($_GET['client_id']) && is_numeric($_GET['client_id']) ? $_GET['client_id'] : '';

// Get task ID from URL if provided
$selectedTaskId = isset($_GET['task_id']) && is_numeric($_GET['task_id']) ? $_GET['task_id'] : '';

// Get all clients for selection
$clientsSql = "SELECT id, name, email FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result();

// Check if there are any clients
if ($clients->num_rows === 0) {
    setFlashMessage('warning', 'You need to add a client first before creating an invoice.');
    header("Location: " . SITE_URL . "/dashboard/clients/add.php");
    exit;
}

// Get selected client's tasks for selection
$tasks = false;
if (!empty($selectedClientId)) {
    $tasksSql = "SELECT id, title FROM tasks WHERE client_id = ? AND user_id = ? ORDER BY title ASC";
    $tasksStmt = $conn->prepare($tasksSql);
    $tasksStmt->bind_param("ii", $selectedClientId, $_SESSION['user_id']);
    $tasksStmt->execute();
    $tasks = $tasksStmt->get_result();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clientId = cleanInput($_POST['client_id']);
    $issueDate = cleanInput($_POST['issue_date']);
    $dueDate = cleanInput($_POST['due_date']);
    $taxRate = cleanInput($_POST['tax_rate']);
    $discount = cleanInput($_POST['discount']);
    $notes = cleanInput($_POST['notes']);
    $subtotal = cleanInput($_POST['subtotal_hidden']);
    $taxAmount = cleanInput($_POST['tax_amount_hidden']);
    $total = cleanInput($_POST['total_hidden']);
    
    // Validate input
    if (empty($clientId) || empty($issueDate) || empty($dueDate)) {
        $error = "Required fields must be filled.";
    } else {
        // Check if client belongs to user
        $checkClientSql = "SELECT id FROM clients WHERE id = ? AND user_id = ?";
        $checkClientStmt = $conn->prepare($checkClientSql);
        $checkClientStmt->bind_param("ii", $clientId, $_SESSION['user_id']);
        $checkClientStmt->execute();
        $checkClientResult = $checkClientStmt->get_result();
        
        if ($checkClientResult->num_rows !== 1) {
            $error = "Invalid client selected.";
        } else {
            // Generate invoice number
            $invoiceNumber = generateInvoiceNumber();
            
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Insert invoice
                $insertSql = "INSERT INTO invoices (invoice_number, client_id, user_id, issue_date, due_date, subtotal, tax_rate, tax_amount, discount, total, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Unpaid')";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->bind_param("siissddddds", $invoiceNumber, $clientId, $_SESSION['user_id'], $issueDate, $dueDate, $subtotal, $taxRate, $taxAmount, $discount, $total, $notes);
                $insertStmt->execute();
                
                $invoiceId = $insertStmt->insert_id;
                
                // Insert invoice items
                $taskIds = isset($_POST['task_id']) ? $_POST['task_id'] : [];
                $descriptions = isset($_POST['description']) ? $_POST['description'] : [];
                $rateTypes = isset($_POST['rate_type']) ? $_POST['rate_type'] : [];
                $quantities = isset($_POST['quantity']) ? $_POST['quantity'] : [];
                $rates = isset($_POST['rate']) ? $_POST['rate'] : [];
                
                for ($i = 0; $i < count($descriptions); $i++) {
                    $taskId = !empty($taskIds[$i]) ? $taskIds[$i] : null;
                    $description = cleanInput($descriptions[$i]);
                    $rateType = cleanInput($rateTypes[$i]);
                    $quantity = floatval($quantities[$i]);
                    $rate = floatval($rates[$i]);
                    $amount = $quantity * $rate;
                    
                    $insertItemSql = "INSERT INTO invoice_items (invoice_id, task_id, description, quantity, rate_type, rate, amount) VALUES (?, ?, ?, ?, ?, ?, ?)";
                    $insertItemStmt = $conn->prepare($insertItemSql);
                    $insertItemStmt->bind_param("iisdsdd", $invoiceId, $taskId, $description, $quantity, $rateType, $rate, $amount);
                    $insertItemStmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                setFlashMessage('success', 'Invoice created successfully.');
                header("Location: " . SITE_URL . "/dashboard/invoices/view.php?id=" . $invoiceId);
                exit;
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Failed to create invoice. Please try again. Error: " . $e->getMessage();
            }
        }
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
                <h1 class="h2">Create Invoice</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Invoices
                    </a>
                </div>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success" role="alert">
                    <?php echo $success; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <div class="row">
                    <!-- Invoice Information -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Invoice Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client *</label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Select Client</option>
                                        <?php 
                                        $clients->data_seek(0);
                                        while ($client = $clients->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $client['id']; ?>" data-email="<?php echo $client['email']; ?>" <?php echo $client['id'] == $selectedClientId ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="issue_date" class="form-label">Issue Date *</label>
                                    <input type="date" class="form-control datepicker" id="issue_date" name="issue_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="due_date" class="form-label">Due Date *</label>
                                    <input type="date" class="form-control datepicker" id="due_date" name="due_date" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" min="0" step="0.01" value="0">
                                </div>
                                <div class="mb-3">
                                    <label for="discount" class="form-label">Discount Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="discount" name="discount" min="0" step="0.01" value="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Invoice Items -->
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Invoice Items</h5>
                                <button type="button" id="add-invoice-item" class="btn btn-sm btn-success">
                                    <i class="fas fa-plus-circle"></i> Add Item
                                </button>
                            </div>
                            <div class="card-body">
                                <div id="invoice-items-container">
                                    <!-- Invoice items will be added here -->
                                </div>
                                
                                <!-- Task template for JavaScript -->
                                <select id="task_template" class="d-none">
                                    <?php 
                                    if ($tasks && $tasks->num_rows > 0):
                                        while ($task = $tasks->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $task['id']; ?>" <?php echo $task['id'] == $selectedTaskId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($task['title']); ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    endif;
                                    ?>
                                </select>
                                
                                <!-- Invoice Totals -->
                                <div class="row mt-4">
                                    <div class="col-md-6"></div>
                                    <div class="col-md-6">
                                        <table class="table table-borderless">
                                            <tr>
                                                <th class="text-end">Subtotal:</th>
                                                <td class="text-end">$<span id="subtotal">0.00</span></td>
                                            </tr>
                                            <tr>
                                                <th class="text-end">Tax (<span id="tax_rate_display">0</span>%):</th>
                                                <td class="text-end">$<span id="tax_amount">0.00</span></td>
                                            </tr>
                                            <tr>
                                                <th class="text-end">Discount:</th>
                                                <td class="text-end">$<span id="discount_display">0.00</span></td>
                                            </tr>
                                            <tr class="border-top">
                                                <th class="text-end">Total:</th>
                                                <td class="text-end"><strong>$<span id="total">0.00</span></strong></td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Hidden fields to store calculated values -->
                                        <input type="hidden" id="subtotal_hidden" name="subtotal_hidden" value="0">
                                        <input type="hidden" id="tax_amount_hidden" name="tax_amount_hidden" value="0">
                                        <input type="hidden" id="total_hidden" name="total_hidden" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Create Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Update client change to load tasks
document.getElementById('client_id').addEventListener('change', function() {
    var clientId = this.value;
    if (clientId) {
        window.location.href = '<?php echo SITE_URL; ?>/dashboard/invoices/add.php?client_id=' + clientId;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>