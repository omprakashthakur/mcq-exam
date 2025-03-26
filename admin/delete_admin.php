<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Check if user has permission (super admin only)
$stmt = $pdo->prepare("
    SELECT a.is_super_admin 
    FROM admin_profiles a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$admin_info = $stmt->fetch();

if (!isset($admin_info['is_super_admin']) || !$admin_info['is_super_admin']) {
    $_SESSION['error'] = 'You do not have permission to perform this action.';
    header('Location: manage_admins.php');
    exit();
}

// Process delete request
if (isset($_GET['id'])) {
    $admin_id = intval($_GET['id']);
    
    try {
        // Prevent deleting yourself
        if ($admin_id === $_SESSION['user_id']) {
            throw new Exception('You cannot delete your own account.');
        }
        
        // Get admin info for logging
        $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin_to_delete = $stmt->fetch();
        
        if (!$admin_to_delete) {
            throw new Exception('Admin user not found.');
        }
        
        $pdo->beginTransaction();
        
        // Delete admin profile first (due to foreign key constraints)
        $stmt = $pdo->prepare("DELETE FROM admin_profiles WHERE user_id = ?");
        $stmt->execute([$admin_id]);
        
        // Delete the user account
        $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
        $stmt->execute([$admin_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, action_type, entity_type, details, ip_address)
            VALUES (?, 'delete', 'admin', ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Deleted admin account: " . $admin_to_delete['username'],
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Admin user deleted successfully.';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid request.';
}

header('Location: manage_admins.php');
exit();