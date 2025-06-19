<?php
$pageTitle = "Clients";
require_once __DIR__ . '/../../includes/header.php';

// Redirect if not logged in or not verified
redirectIfNotLoggedIn();
redirectIfNotVerified();

// Handle client deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $clientId = $_GET['delete'];
    
    // Check if client belongs to user
    $checkSql = "SELECT id FROM clients WHERE id = ? AND user_id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("ii", $clientId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 1) {
        // Delete client
        $deleteSql = "DELETE FROM clients WHERE id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("i", $clientId);
        
        if ($deleteStmt->execute()) {
            setFlashMessage('success', 'Client deleted successfully.');
        } else {
            setFlashMessage('danger', 'Failed to delete client. Please try again.');
        }
    } else {
        setFlashMessage('danger', 'Client not found or you do not have permission to delete this client.');
    }
    
    // Redirect to avoid resubmission
    header("Location: " . SITE_URL . "/dashboard/clients");
    exit;
}

// Get clients
$search = '';
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = cleanInput($_GET['search']);
    $sql = "SELECT * FROM clients WHERE user_id = ? AND (name LIKE ? OR email LIKE ? OR company LIKE ?) ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $searchParam = '%' . $search . '%';
    $stmt->bind_param("isss", $_SESSION['user_id'], $searchParam, $searchParam, $searchParam);
} else {
    $sql = "SELECT * FROM clients WHERE user_id = ? ORDER BY name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $_SESSION['user_id']);
}

$stmt->execute();
$clients = $stmt->get_result();
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
        
        <!-- Main content -->
        <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Clients</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/clients/add.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus-circle"></i> Add Client
                    </a>
                </div>
            </div>
            
            <!-- Search -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" placeholder="Search clients..." value="<?php echo htmlspecialchars($search); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <?php if (!empty($search)): ?>
                            <div class="col-md-2">
                                <a href="<?php echo SITE_URL; ?>/dashboard/clients" class="btn btn-secondary w-100">Clear</a>
                            </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
            
            <!-- Clients List -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">All Clients</h5>
                </div>
                <div class="card-body">
                    <?php if ($clients->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Company</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while ($client = $clients->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                                            <td><?php echo htmlspecialchars($client['email']); ?></td>
                                            <td><?php echo htmlspecialchars($client['phone']); ?></td>
                                            <td><?php echo htmlspecialchars($client['company']); ?></td>
                                            <td>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/clients/view.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="<?php echo SITE_URL; ?>/dashboard/clients/edit.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="javascript:void(0);" onclick="confirmDelete(<?php echo $client['id']; ?>, '<?php echo htmlspecialchars($client['name']); ?>')" class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted">No clients found.</p>
                            <a href="<?php echo SITE_URL; ?>/dashboard/clients/add.php" class="btn btn-primary">
                                <i class="fas fa-plus-circle"></i> Add Client
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
                Are you sure you want to delete client <span id="clientName"></span>? This action cannot be undone.
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