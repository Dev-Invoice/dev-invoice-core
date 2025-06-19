<?php
$pageTitle = "Register";
require_once __DIR__ . '/../includes/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    if (isVerified()) {
        header("Location: " . SITE_URL . "/dashboard");
    } else {
        header("Location: " . SITE_URL . "/auth/verify.php");
    }
    exit;
}

$error = '';
$success = '';

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = cleanInput($_POST['username']);
    $email = cleanInput($_POST['email']);
    $password = $_POST['password'];
    $password_confirm = $_POST['password_confirm'];
    $company = cleanInput($_POST['company']);
    $phone = cleanInput($_POST['phone']);
    
    // Basic validation
    if (empty($username) || empty($email) || empty($password) || empty($password_confirm)) {
        $error = "All fields are required.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $password_confirm) {
        $error = "Passwords do not match.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } else {
        // Check if email already exists
        $checkEmailSql = "SELECT id FROM users WHERE email = ?";
        $checkEmailStmt = $conn->prepare($checkEmailSql);
        $checkEmailStmt->bind_param("s", $email);
        $checkEmailStmt->execute();
        $checkEmailResult = $checkEmailStmt->get_result();
        
        if ($checkEmailResult->num_rows > 0) {
            $error = "Email already exists. Please use a different email or login.";
        } else {
            // Generate verification code
            $verificationCode = generateVerificationCode();
            
            // Hash password
            $hashedPassword = hashPassword($password);
            
            // Insert new user
            $insertSql = "INSERT INTO users (username, email, password, company, phone, verification_code) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = $conn->prepare($insertSql);
            $insertStmt->bind_param("ssssss", $username, $email, $hashedPassword, $company, $phone, $verificationCode);
            
            if ($insertStmt->execute()) {
                // Send verification email
                if (sendVerificationEmail($email, $verificationCode)) {
                    // Set session variables
                    $_SESSION['user_id'] = $insertStmt->insert_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['is_admin'] = 0;
                    $_SESSION['is_verified'] = 0;
                    
                    // Redirect to verification page
                    header("Location: " . SITE_URL . "/auth/verify.php");
                    exit;
                } else {
                    $error = "Failed to send verification email. Please try again.";
                }
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
} ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    
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
    <div class="auth-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card auth-card">
                        <div class="auth-logo">
                            <h2 class="mt-3"><?php echo SITE_NAME; ?></h2>
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
                            <div class="mb-3">
                                <label for="username" class="form-label">Username</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email address</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="mb-3">
                                <label for="company" class="form-label">Company (Optional)</label>
                                <input type="text" class="form-control" id="company" name="company">
                            </div>
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone (Optional)</label>
                                <input type="text" class="form-control" id="phone" name="phone">
                            </div>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                                <progress id="password-strength-meter" value="0" max="5" class="password-strength-meter"></progress>
                                <small class="form-text text-muted">Password must be at least 8 characters long.</small>
                            </div>
                            <div class="mb-3">
                                <label for="password_confirm" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="password_confirm" name="password_confirm" required>
                                <small id="password-match-status" class="form-text"></small>
                            </div>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">Register</button>
                            </div>
                        </form>
                        
                        <div class="mt-4 text-center">
                            <p>Already have an account? <a href="<?php echo SITE_URL; ?>/auth/login.php">Login</a></p>
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
</html>