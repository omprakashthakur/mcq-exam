<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Check if user has permission to manage admins (super admin only)
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

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['admin_id'], $_POST['new_status'])) {
    $admin_id = intval($_POST['admin_id']);
    $new_status = intval($_POST['new_status']) ? 1 : 0;
    
    try {
        // Prevent deactivating yourself
        if ($admin_id === $_SESSION['user_id']) {
            throw new Exception('You cannot change your own status.');
        }
        
        $pdo->beginTransaction();
        
        // Update admin status
        $stmt = $pdo->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'admin'");
        $stmt->execute([$new_status, $admin_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, details, ip_address)
            VALUES (?, ?, 'admin', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $new_status ? 'update' : 'update',
            $admin_id,
            $new_status ? 'Activated admin account' : 'Deactivated admin account',
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Admin status updated successfully.';
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