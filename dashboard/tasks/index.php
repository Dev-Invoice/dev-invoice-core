<?php
$pageTitle = "Tasks";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Handle task deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $taskId = $_GET['delete'];
    
    // Check if task belongs to user
    $checkSql = "SELECT id FROM tasks WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $taskId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        // Delete task
        $deleteSql = "DELETE FROM tasks WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $taskId);
        
        if ($deleteStmt->execute()) {
            setFlashMessage('success', 'Task deleted successfully.');
        } else {
            setFlashMessage('danger', 'Failed to delete task. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Task not found or you do not have permission to delete this task.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/tasks");
    exit;
}

// Handle status update
if (isset($_GET['complete']) && is_numeric($_GET['complete'])) {
    $taskId = $_GET['complete'];
    
    // Check if task belongs to user
    $checkSql = "SELECT id FROM tasks WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $taskId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        // Update task status
        $updateSql = "UPDATE tasks SET status = 'Completed' WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("i", $taskId);
        
        if ($updateStmt->execute()) {
            setFlashMessage('success', 'Task marked as completed.');
        } else {
            setFlashMessage('danger', 'Failed to update task status. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Task not found or you do not have permission to update this task.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/tasks");
    exit;
}

// Get filter parameters
$clientFilter = isset($_GET['client']) ? cleanInput($_GET['client']) : '';
$statusFilter = isset($_GET['status']) ? cleanInput($_GET['status']) : '';
$priorityFilter = isset($_GET['priority']) ? cleanInput($_GET['priority']) : '';
$search = isset($_GET['search']) ? cleanInput($_GET['search']) : '';

// Build query based on filters
$sql = "SELECT t.*, c.name as client_name FROM tasks t JOIN clients c ON t.client_id = c.id WHERE t.user_id = ?";
$params = [$_SESSION['user_id']];
$types = "i";

if (!empty($clientFilter)) {
    $sql .= " AND t.client_id = ?";
    $params[] = $clientFilter;
    $types .= "i";
}

if (!empty($statusFilter)) {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= "s";
}

if (!empty($priorityFilter)) {
    $sql .= " AND t.priority = ?";
    $params[] = $priorityFilter;
    $types .= "s";
}

if (!empty($search)) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $searchParam = "%" . $search . "%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $types .= "ss";
}

// Order by priority, status, and due date
$sql .= " ORDER BY 
    CASE 
        WHEN t.status = 'Completed' THEN 3
        WHEN t.status = 'In Progress' THEN 2
        ELSE 1
    END,
    CASE 
        WHEN t.priority = 'High' THEN 1
        WHEN t.priority = 'Medium' THEN 2
        ELSE 3
    END,
    t.due_date ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result();

// Get clients for filter dropdown
$clientsSql = "SELECT id, name FROM clients WHERE user_id = ? ORDER BY name ASC";
$clientsStmt = $conn->prepare($clientsSql);
$clientsStmt->bind_param("i", $_SESSION['user_id']);
$clientsStmt->execute();
$clients = $clientsStmt->get_result(); ?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Tasks</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Add Task
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                        <div class="col-md-3">
                            <select name="client" class="form-select">
                                <option value="">All Clients</option>
                                <?php while ($client = $clients->fetch_assoc()): ?>
                                    <option value="<?php echo $client['id']; ?>" <?php echo $clientFilter == $client['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($client['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-select">
                                <option value="">All Statuses</option>
                                <option value="Pending" <?php echo $statusFilter == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="In Progress" <?php echo $statusFilter == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="Completed" <?php echo $statusFilter == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="priority" class="form-select">
                                <option value="">All Priorities</option>
                                <option value="Low" <?php echo $priorityFilter == 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $priorityFilter == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $priorityFilter == 'High' ? 'selected' : ''; ?>>High</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search tasks..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <a href="<?php echo SITE_URL; ?>/dashboard/tasks" class="btn btn-secondary w-100">Reset</a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Tasks List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Tasks</h5>
                </div>
                <div class="card-body">
                    <?php if ($tasks->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Client</th>
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
                                            <td><?php echo htmlspecialchars($task['client_name']); ?></td>
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
                                                <div class="btn-group">
                                                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/view.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="<?php echo SITE_URL; ?>/dashboard/tasks/edit.php?id=<?php echo $task['id']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php if ($task['status'] != 'Completed'): ?>
                                                        <a href="javascript:void(0);" onclick="confirmComplete(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')" class="btn btn-sm btn-success" title="Mark as Completed">
                                                            <i class="fas fa-check"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $task['id']; ?>, '<?php echo htmlspecialchars($task['title']); ?>')" class="btn btn-sm btn-danger">
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
                            <p class="text-muted">No tasks found.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/tasks/add.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Task
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

<!-- Complete Confirmation Modal -->
<div class="modal fade" id="completeModal" tabindex="-1" aria-labelledby="completeModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="completeModalLabel">Confirm Completion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to mark task "<span id="completeTaskTitle"></span>" as completed?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmCompleteBtn" class="btn btn-success">Mark as Completed</a>
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

function confirmComplete(taskId, taskTitle) {
    document.getElementById('completeTaskTitle').textContent = taskTitle;
    document.getElementById('confirmCompleteBtn').href = '<?php echo SITE_URL; ?>/dashboard/tasks/index.php?complete=' + taskId;
    var modal = new bootstrap.Modal(document.getElementById('completeModal'));
    modal.show();
}
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>