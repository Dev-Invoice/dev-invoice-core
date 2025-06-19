<?php
$pageTitle = "Edit Task";
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
$checkSql = "SELECT * FROM tasks WHERE id = ? AND user_id = ?";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("ii", $taskId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows !== 1) {
    setFlashMessage('danger', 'Task not found or you do not have permission to edit this task.');
    header("Location: " . SITE_URL . "/dashboard/tasks");
    exit;
}

$task = $checkResult->fetch_assoc();

// Get all clients for selection
$clientsSql = "SELECT id, name, email FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result();

$error = '';
$success = '';

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
            // Update task
            $updateSql = "UPDATE tasks SET title = ?, description = ?, client_id = ?, due_date = ?, priority = ?, status = ? WHERE id = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("ssssssii", $title, $description, $clientId, $dueDate, $priority, $status, $taskId, $_SESSION['user_id']);
            
            if ($updateStmt->execute()) {
                $success = "Task updated successfully.";
                
                // Refresh task data
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $task = $checkResult->fetch_assoc();
            } else {
                $error = "Failed to update task. Please try again.";
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
                <h1 class="h2">Edit Task</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> Back to Tasks
                    </a>
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $taskId; ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-eye"></i> View Task
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
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $taskId; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Title *</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($task['title']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="client_id" class="form-label">Client *</label>
                                <select class="form-select" id="client_id" name="client_id" required>
                                    <option value="">Select Client</option>
                                    <?php 
                                    $clients->data_seek(0);
                                    while ($client = $clients->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $client['id']; ?>" data-email="<?php echo $client['email']; ?>" <?php echo $client['id'] == $task['client_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($client['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="due_date" class="form-label">Due Date *</label>
                                <input type="date" class="form-control datepicker" id="due_date" name="due_date" value="<?php echo $task['due_date']; ?>" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="priority" class="form-label">Priority *</label>
                                <select class="form-select" id="priority" name="priority" required>
                                    <option value="Low" <?php echo $task['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="Medium" <?php echo $task['priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="High" <?php echo $task['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status *</label>
                                <select class="form-select" id="status" name="status" required>
                                    <option value="Pending" <?php echo $task['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $task['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $task['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="5"><?php echo htmlspecialchars($task['description']); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Task
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>