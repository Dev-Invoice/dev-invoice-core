<?php
require_once __DIR__ . '/config/db.php';
$pageTitle = "404 - Page Not Found";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo SITE_NAME; ?></title>
    
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
    <div class="container">
        <div class="row justify-content-center align-items-center min-vh-100">
            <div class="col-md-6 text-center">
                <h1 class="display-1 text-danger mb-4"><i class="fas fa-exclamation-triangle"></i></h1>
                <h1 class="mb-4">404 - Page Not Found</h1>
                <p class="lead mb-5">The page you are looking for does not exist or has been moved.</p>
                <a href="<?php echo SITE_URL; ?>" class="btn btn-primary">
                    <i class="fas fa-home me-2"></i> Back to Home
                </a>
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