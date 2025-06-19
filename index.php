<?php
require_once __DIR__ . '/includes/functions.php';

// Redirect based on login status
if (isLoggedIn()) {
    if (isVerified()) {
        header("Location: " . SITE_URL . "/dashboard");
    } else {
        header("Location: " . SITE_URL . "/auth/verify.php");
    }
} else {
    header("Location: " . SITE_URL . "/auth/login.php");
}
exit;