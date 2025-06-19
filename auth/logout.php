<?php
require_once __DIR__ . '/../includes/functions.php';

// Destroy session
session_start();
session_unset();
session_destroy();

// Redirect to login page
header("Location: " . SITE_URL . "/auth/login.php");
exit;