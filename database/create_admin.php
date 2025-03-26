<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

try {
    $pdo->beginTransaction();
    
    // Check if admin exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'admin'");
    $stmt->execute();
    $admin = $stmt->fetch();
    
    if (!$admin) {
        // Create admin user - password is 'password123'
        $password = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
        $stmt = $pdo->prepare("
            INSERT INTO users (username, email, password, role, is_active, is_verified)
            VALUES ('admin', 'admin@mcqexam.com', ?, 'admin', 1, 1)
        ");
        $stmt->execute([$password]);
        $admin_id = $pdo->lastInsertId();
        
        // Create admin profile
        $stmt = $pdo->prepare("
            INSERT INTO admin_profiles (user_id, full_name, is_super_admin)
            VALUES (?, 'System Administrator', 1)
        ");
        $stmt->execute([$admin_id]);
        
        echo "Admin account created successfully!\n";
        echo "Username: admin\n";
        echo "Password: password123\n";
    } else {
        // Ensure admin profile exists
        $stmt = $pdo->prepare("SELECT id FROM admin_profiles WHERE user_id = ?");
        $stmt->execute([$admin['id']]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare("
                INSERT INTO admin_profiles (user_id, full_name, is_super_admin)
                VALUES (?, 'System Administrator', 1)
            ");
            $stmt->execute([$admin['id']]);
            echo "Admin profile created for existing admin account.\n";
        } else {
            echo "Admin account already exists and is properly configured.\n";
        }
    }
    
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage() . "\n";
}