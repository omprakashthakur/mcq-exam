<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!isset($_POST['student_id']) || !isset($_POST['status'])) {
            throw new Exception('Invalid request parameters');
        }

        $student_id = intval($_POST['student_id']);
        $new_status = intval($_POST['status']) ? 1 : 0;

        $pdo->beginTransaction();

        // Verify student exists
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ? AND role = 'user'");
        $stmt->execute([$student_id]);
        $student = $stmt->fetch();

        if (!$student) {
            throw new Exception('Student not found');
        }

        // Update student status
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'user'");
        $stmt->execute([$new_status, $student_id]);

        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (
                admin_id,
                action_type,
                entity_type,
                entity_id,
                details,
                ip_address
            ) VALUES (?, 'update', 'student', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $student_id,
            ($new_status ? 'Activated' : 'Deactivated') . ' student account: ' . $student['username'],
            $_SERVER['REMOTE_ADDR']
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Student status has been ' . ($new_status ? 'activated' : 'deactivated') . ' successfully.';

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

// Redirect back to previous page
$redirect = $_SERVER['HTTP_REFERER'] ?? 'manage_students.php';
header("Location: $redirect");
exit();
?>