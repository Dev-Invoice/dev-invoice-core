<?php
$pageTitle = "Edit Client";
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
    setFlashMessage('danger', 'Client not found or you do not have permission to edit this client.');
    header("Location: " . SITE_URL . "/dashboard/clients");
    exit;
}

$client = $checkResult->fetch_assoc();

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name']);
    $email = cleanInput($_POST['email']);
    $phone = cleanInput($_POST['phone']);
    $company = cleanInput($_POST['company']);
    $address = cleanInput($_POST['address']);
    
    // Validate input
    if (empty($name) || empty($email)) {
        $error = "Name and email are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists (excluding current client)
        $checkEmailSql = "SELECT id FROM clients WHERE email = ? AND user_id = ? AND id != ?";
        $checkEmailStmt = $conn->prepare($checkEmailSql);
        $checkEmailStmt->bind_param("sii", $email, $_SESSION['user_id'], $clientId);
        $checkEmailStmt->execute();
        $checkEmailResult = $checkEmailStmt->get_result();
        
        if ($checkEmailResult->num_rows > 0) {
            $error = "A client with this email already exists.";
        } else {
            // Update client
            $updateSql = "UPDATE clients SET name = ?, email = ?, phone = ?, company = ?, address = ? WHERE id = ? AND user_id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("sssssii", $name, $email, $phone, $company, $address, $clientId, $_SESSION['user_id']);
            
            if ($updateStmt->execute()) {
                $success = "Client updated successfully.";
                
                // Refresh client data
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                $client = $checkResult->fetch_assoc();
            } else {
                $error = "Failed to update client. Please try again.";
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
                <h1 class="h2">Edit Client</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="<?php echo SITE_URL; ?>/dashboard/clients" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Clients
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
                    <h5 class="mb-0">Client Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?id=' . $clientId; ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($client['email']); ?>" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($client['phone']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="company" class="form-label">Company</label>
                                <input type="text" class="form-control" id="company" name="company" value="<?php echo htmlspecialchars($client['company']); ?>">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($client['address']); ?></textarea>
                        </div>
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Client
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>