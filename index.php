<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');

// Include required files
require_once 'includes/security.php';
require_once 'config/database.php';

// Check for maintenance mode
if (file_exists('maintenance.flag') && !isset($_SESSION['is_admin'])) {
    include 'includes/maintenance.php';
    exit();
}

// Redirect to login if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include header
include 'includes/header.php';

// Check user role and redirect accordingly
if (isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/dashboard.php');
        exit();
    } else {
        header('Location: student/dashboard.php');
        exit();
    }
}

// Include footer
include 'includes/footer.php';
?>