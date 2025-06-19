<?php
$pageTitle = "Verify Email";
require_once __DIR__ . '/../includes/functions.php';

// Redirect if not logged in
if (!isLoggedIn()) {
    header("Location: " . SITE_URL . "/auth/login.php");
    exit;
}

// Redirect if already verified
if (isVerified()) {
    header("Location: " . SITE_URL . "/dashboard");
    exit;
}

$error = '';
$success = '';

// Handle verification form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect the verification code from the 6 inputs
    $code = '';
    for ($i = 1; $i <= 6; $i++) {
        $code .= cleanInput($_POST['code' . $i]);
    }
    
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        $error = "Please enter a valid 6-digit verification code.";
    } else {
        // Get user verification code from database
        $userId = $_SESSION['user_id'];
        $sql = "SELECT verification_code FROM users WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify code
            if ($user['verification_code'] === $code) {
                // Update user as verified
                $updateSql = "UPDATE users SET is_verified = 1, verification_code = NULL WHERE id = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $userId);
                
                if ($updateStmt->execute()) {
                    // Update session
                    $_SESSION['is_verified'] = 1;
                    
                    // Redirect to dashboard
                    header("Location: " . SITE_URL . "/dashboard");
                    exit;
                } else {
                    $error = "Verification failed. Please try again.";
                }
            } else {
                $error = "Invalid verification code.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Handle resend verification code
if (isset($_POST['resend'])) {
    // Generate new verification code
    $verificationCode = generateVerificationCode();
    
    // Update verification code in database
    $userId = $_SESSION['user_id'];
    $updateSql = "UPDATE users SET verification_code = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("si", $verificationCode, $userId);
    
    if ($updateStmt->execute()) {
        // Send verification email
        if (sendVerificationEmail($_SESSION['email'], $verificationCode)) {
            $success = "Verification code has been resent to your email.";
        } else {
            $error = "Failed to send verification email. Please try again.";
        }
    } else {
        $error = "Failed to generate new verification code. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Email - <?php echo SITE_NAME; ?></title>
    
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
                        
                        <h4 class="text-center mb-4">Verify Your Email</h4>
                        
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
                        
                        <p class="text-center">We've sent a verification code to <strong><?php echo $_SESSION['email']; ?></strong></p>
                        <p class="text-center">Please enter the 6-digit code below.</p>
                        
                        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                            <div class="verification-code">
                                <input type="text" name="code1" maxlength="1" pattern="[0-9]" inputmode="numeric" required autofocus>
                                <input type="text" name="code2" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                <input type="text" name="code3" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                <input type="text" name="code4" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                <input type="text" name="code5" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                                <input type="text" name="code6" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary">Verify</button>
                            </div>
                        </form>
                        
                        <div class="text-center">
                            <p>Didn't receive the code?</p>
                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                <button type="submit" name="resend" class="btn btn-link">Resend Code</button>
                            </form>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="<?php echo SITE_URL; ?>/auth/logout.php">Logout</a>
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