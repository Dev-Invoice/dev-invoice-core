<?php
$pageTitle = "Add Task";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

$error = '';
$success = '';

// Get client ID from URL if provided
$selectedClientId = isset($_GET['client_id']) && is_numeric($_GET['client_id']) ? $_GET['client_id'] : '';

// Get all clients for selection
$clientsSql = "SELECT id, name, email FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result();

// Check if there are any clients
if ($clients->num_rows === 0) {
    setFlashMessage('warning', 'You need to add a client first before adding a task.');
    header("Location: " . SITE_URL . "/dashboard/clients/add.php");
    exit;
}

// Reset clients result for the form
$clients->data_seek(0);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = cleanInput($_POST['title']);
    $description = cleanInput($_POST['description']);
    $clientId = cleanInput($_POST['client_id']);
    $dueDate = cleanInput($_POST['due_date']);
    $priority = cleanInput($_POST['priority']);
    $status = cleanInput($_POST['status']);
    
    // Validate input
    if (empty($title) || empty($clientId) || empty($dueDate) || empty($priority) || empty($status)) {
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
            // Insert task
            $insertSql = "INSERT INTO tasks (title, description, client_id, user_id, due_date, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("ssiisss", $title, $description, $clientId, $_SESSION['user_id'], $dueDate, $priority, $status);
            
            if ($insertStmt->execute()) {
                $taskId = $insertStmt->insert_id;
                setFlashMessage('success', 'Task added successfully.');
                header("Location: " . SITE_URL . "/dashboard/tasks/view.php?id=" . $taskId);
                exit;
            } else {
                $error = "Failed to add task. Please try again.";
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
                <h1 class="h2">Add Task</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Tasks
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
            
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Task Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Client *</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Select Client</option>
                                    <?php while ($client = $clients->fetch_assoc()): ?>
                                        <option value="<?php echo $client['id']; ?>" data-email="<?php echo $client['email']; ?>" <?php echo $client['id'] == $selectedClientId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control datepicker" id="due_date" name="due_date" min="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="priority" class="form-label">Priority *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Pending" selected>Pending</option>
                                    <option value="In Progress">In Progress</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5"></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>