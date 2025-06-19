<?php
$pageTitle = "Reset Password";
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: " . SITE_URL . "/dashboard");
    exit;
}

$error = '';
$success = '';
$validToken = false;

// Check for token and email in URL
if (isset($_GET['token']) && isset($_GET['email'])) {
    $token = cleanInput($_GET['token']);
    $email = cleanInput($_GET['email']);
    
    // Validate token
    $sql = "SELECT id FROM users WHERE email = ? AND reset_token = ? AND reset_token_expires > NOW()";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $email, $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $validToken = true;
    } else {
        $error = "Invalid or expired reset token. Please request a new password reset.";
    }
} else {
    $error = "Missing reset token or email. Please request a new password reset.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    
    if (empty($password) || empty($password_confirm)) {
        $error = "Both password fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } else {
        // Hash new password
        $hashedPassword = hashPassword($password);
        
        // Update password and clear reset token
        $updateSql = "UPDATE users SET password = ?, reset_token = NULL, reset_token_expires = NULL WHERE email = ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param("ss", $hashedPassword, $email);
        
        if ($updateStmt->execute()) {
            $success = "Password has been reset successfully. You can now login with your new password.";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - <?php echo SITE_NAME; ?></title>
    
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/bootstrap.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/img/favicon.ico" type="image/x-icon">
</head>
<body>
</html>
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card auth-card">
                        <div class="auth-logo">
                            <h2 class="mt-3"><?php echo SITE_NAME; ?></h2>
                        </div>
                        
                        <h4 class="text-center mb-4">Reset Password</h4>
                        
                        <?php if (!empty($error)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?php echo $error; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success)): ?>
                            <div class="alert alert-success" role="alert">
                                <?php echo $success; ?>
                                <div class="mt-3 text-center">
                                    <a href="<?php echo SITE_URL; ?>/auth/login.php" class="btn btn-primary">Go to Login</a>
                                </div>
                            </div>
                        <?php elseif ($validToken): ?>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?token=' . urlencode($token) . '&email=' . urlencode($email); ?>">
                                <div class="mb-3">
                                    <label for="password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                    <progress id="password-strength-meter" value="0" max="5" class="password-strength-meter"></progress>
                                    <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                                </div>
                                <div class="mb-3">
                                    <label for="password_confirm" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                    <small id="password-match-status" class="form-text"></small>
                                </div>
                                <div class="d-grid mb-3">
                                    <button type="submit" class="btn btn-primary">Reset Password</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-center">
                                <a href="<?php echo SITE_URL; ?>/auth/forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 text-center">
                            <p>Remember your password? <a href="<?php echo SITE_URL; ?>/auth/login.php">Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- jQuery -->
    <script src="<?php echo SITE_URL; ?>/assets/js/jquery.min.js"></script>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="<?php echo SITE_URL; ?>/assets/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>
</body>