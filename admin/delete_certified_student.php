<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM certified_students WHERE id = ?");
        $stmt->execute([$_GET['id']]);

        $_SESSION['success'] = 'Certified student deleted successfully.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error deleting student: ' . $e->getMessage();
    }
}

header('Location: manage_certified_students.php');
exit();