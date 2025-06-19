<?php
$pageTitle = "Edit Invoice";
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
$checkSql = "SELECT * FROM invoices WHERE id = ? AND user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $invoiceId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Invoice not found or you do not have permission to edit this invoice.');
    header("Location: " . SITE_URL . "/dashboard/invoices");
    exit;
}

$invoice = $checkResult->fetch_assoc();

// Get all clients for selection
$clientsSql = "SELECT id, name, email FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result();

// Get selected client's tasks for selection
$clientId = $invoice['client_id'];
$tasksSql = "SELECT id, title FROM tasks WHERE client_id = ? AND user_id = ? ORDER BY title ASC";
$tasksStmt = $conn->prepare($tasksSql);
$tasksStmt->bind_param("ii", $clientId, $_SESSION['user_id']);
$tasksStmt->execute();
$tasks = $tasksStmt->get_result();

// Get invoice items
$itemsSql = "SELECT * FROM invoice_items WHERE invoice_id = ?";
$itemsStmt = $conn->prepare($itemsSql);
$itemsStmt->bind_param("i", $invoiceId);
$itemsStmt->execute();
$items = $itemsStmt->get_result();

$error = '';
$success = '';

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
    $status = cleanInput($_POST['status']);
    
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
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Update invoice
                $updateSql = "UPDATE invoices SET client_id = ?, issue_date = ?, due_date = ?, subtotal = ?, tax_rate = ?, tax_amount = ?, discount = ?, total = ?, notes = ?, status = ? WHERE id = ? AND user_id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("issddddsssii", $clientId, $issueDate, $dueDate, $subtotal, $taxRate, $taxAmount, $discount, $total, $notes, $status, $invoiceId, $_SESSION['user_id']);
                $updateStmt->execute();
                
                // Delete existing invoice items
                $deleteItemsSql = "DELETE FROM invoice_items WHERE invoice_id = ?";
                $deleteItemsStmt = $conn->prepare($deleteItemsSql);
                $deleteItemsStmt->bind_param("i", $invoiceId);
                $deleteItemsStmt->execute();
                
                // Insert updated invoice items
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
                    $insertItemStmt->bind_param("iisdsddd", $invoiceId, $taskId, $description, $quantity, $rateType, $rate, $amount);
                    $insertItemStmt->execute();
                }
                
                // Commit transaction
                $conn->commit();
                
                $success = "Invoice updated successfully.";
                
                // Refresh invoice data
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $invoice = $checkResult->fetch_assoc();
                
                // Refresh invoice items
                $itemsStmt->execute();
                $items = $itemsStmt->get_result();
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Failed to update invoice. Please try again. Error: " . $e->getMessage();
            }
        }
    }
} ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Edit Invoice</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Invoices
                    </a>
                    <a href="<?php echo SITE_URL; ?>/dashboard/invoices/view.php?id=<?php echo $invoiceId; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> View Invoice
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
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $invoiceId; ?>">
                <div class="row">
                    <!-- Invoice Information -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Invoice Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="invoice_number" class="form-label">Invoice Number</label>
                                    <input type="text" class="form-control" id="invoice_number" value="<?php echo htmlspecialchars($invoice['invoice_number']); ?>" readonly>
                                </div>
                                <div class="mb-3">
                                    <label for="client_id" class="form-label">Client *</label>
                                    <select class="form-select" id="client_id" name="client_id" required>
                                        <option value="">Select Client</option>
                                        <?php 
                                        $clients->data_seek(0);
                                        while ($client = $clients->fetch_assoc()): 
                                        ?>
                                            <option value="<?php echo $client['id']; ?>" data-email="<?php echo $client['email']; ?>" <?php echo $client['id'] == $invoice['client_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['name']); ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="issue_date" class="form-label">Issue Date *</label>
                                    <input type="date" class="form-control datepicker" id="issue_date" name="issue_date" value="<?php echo $invoice['issue_date']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="due_date" class="form-label">Due Date *</label>
                                    <input type="date" class="form-control datepicker" id="due_date" name="due_date" value="<?php echo $invoice['due_date']; ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                                    <input type="number" class="form-control" id="tax_rate" name="tax_rate" min="0" step="0.01" value="<?php echo $invoice['tax_rate']; ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="discount" class="form-label">Discount Amount</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" id="discount" name="discount" min="0" step="0.01" value="<?php echo $invoice['discount']; ?>">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status *</label>
                                    <select class="form-select" id="status" name="status" required>
                                        <option value="Unpaid" <?php echo $invoice['status'] == 'Unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                                        <option value="Paid" <?php echo $invoice['status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                        <option value="Overdue" <?php echo $invoice['status'] == 'Overdue' ? 'selected' : ''; ?>>Overdue</option>
                                        <option value="Cancelled" <?php echo $invoice['status'] == 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="notes" class="form-label">Notes</label>
                                    <textarea class="form-control" id="notes" name="notes" rows="3"><?php echo htmlspecialchars($invoice['notes']); ?></textarea>
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
                                    <!-- Existing items will be loaded via JavaScript -->
                                    <?php 
                                    $itemsArray = [];
                                    while ($item = $items->fetch_assoc()): 
                                        $itemsArray[] = $item;
                                    endwhile;
                                    ?>
                                </div>
                                
                                <!-- Task template for JavaScript -->
                                <select id="task_template" class="d-none">
                                    <?php 
                                    if ($tasks && $tasks->num_rows > 0):
                                        while ($task = $tasks->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $task['id']; ?>">
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
                                                <td class="text-end">$<span id="subtotal"><?php echo number_format($invoice['subtotal'], 2); ?></span></td>
                                            </tr>
                                            <tr>
                                                <th class="text-end">Tax (<span id="tax_rate_display"><?php echo $invoice['tax_rate']; ?></span>%):</th>
                                                <td class="text-end">$<span id="tax_amount"><?php echo number_format($invoice['tax_amount'], 2); ?></span></td>
                                            </tr>
                                            <tr>
                                                <th class="text-end">Discount:</th>
                                                <td class="text-end">$<span id="discount_display"><?php echo number_format($invoice['discount'], 2); ?></span></td>
                                            </tr>
                                            <tr class="border-top">
                                                <th class="text-end">Total:</th>
                                                <td class="text-end"><strong>$<span id="total"><?php echo number_format($invoice['total'], 2); ?></span></strong></td>
                                            </tr>
                                        </table>
                                        
                                        <!-- Hidden fields to store calculated values -->
                                        <input type="hidden" id="subtotal_hidden" name="subtotal_hidden" value="<?php echo $invoice['subtotal']; ?>">
                                        <input type="hidden" id="tax_amount_hidden" name="tax_amount_hidden" value="<?php echo $invoice['tax_amount']; ?>">
                                        <input type="hidden" id="total_hidden" name="total_hidden" value="<?php echo $invoice['total']; ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Invoice
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Client change handling
document.getElementById('client_id').addEventListener('change', function() {
    var clientId = this.value;
    if (clientId) {
        window.location.href = '<?php echo SITE_URL; ?>/dashboard/invoices/edit.php?id=<?php echo $invoiceId; ?>&client_id=' + clientId;
    }
});

// Load existing items
var existingItems = <?php echo json_encode($itemsArray); ?>;
window.addEventListener('DOMContentLoaded', function() {
    if (existingItems.length > 0) {
        var container = document.getElementById('invoice-items-container');
        container.innerHTML = '';
        
        existingItems.forEach(function(item, index) {
            var newItem = document.createElement('div');
            newItem.className = 'invoice-item card mb-3';
            newItem.innerHTML = `
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <div class="d-flex justify-content-between">
                                <h5 class="card-title">Item #${index + 1}</h5>
                                <button type="button" class="btn btn-sm btn-danger remove-item" title="Remove Item">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-12 mb-3">
                            <select name="task_id[]" class="form-select">
                                <option value="">Select a task (optional)</option>
                                ${document.getElementById('task_template').innerHTML}
                            </select>
                        </div>
                        <div class="col-md-12 mb-3">
                            <textarea name="description[]" class="form-control" placeholder="Description" required>${item.description}</textarea>
                        </div>
                        <div class="col-md-4 mb-3">
                            <select name="rate_type[]" class="form-select rate-type-select">
                                <option value="Hourly" ${item.rate_type === 'Hourly' ? 'selected' : ''}>Hourly Rate</option>
                                <option value="Flat" ${item.rate_type === 'Flat' ? 'selected' : ''}>Flat Rate</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="number" name="quantity[]" class="form-control quantity-input" placeholder="Hours/Quantity" min="0.01" step="0.01" value="${item.quantity}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <input type="number" name="rate[]" class="form-control rate-input" placeholder="Rate" min="0.01" step="0.01" value="${item.rate}" required>
                        </div>
                    </div>
                </div>
            `;
            
            container.appendChild(newItem);
            
            // Select the correct task if available
            if (item.task_id) {
                var taskSelect = newItem.querySelector('select[name="task_id[]"]');
                for (var i = 0; i < taskSelect.options.length; i++) {
                    if (taskSelect.options[i].value == item.task_id) {
                        taskSelect.options[i].selected = true;
                        break;
                    }
                }
            }
            
            // Add event listeners
            newItem.querySelector('.remove-item').addEventListener('click', function() {
                container.removeChild(newItem);
                updateItemNumbers();
                calculateInvoiceTotal();
            });
            
            newItem.querySelector('.quantity-input').addEventListener('input', calculateInvoiceTotal);
            newItem.querySelector('.rate-input').addEventListener('input', calculateInvoiceTotal);
            newItem.querySelector('.rate-type-select').addEventListener('change', updateQuantityLabel);
        });
        
        updateQuantityLabel();
        calculateInvoiceTotal();
    } else {
        // Add an initial empty item if no existing items
        document.getElementById('add-invoice-item').click();
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>