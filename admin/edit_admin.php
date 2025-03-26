<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Check if user has super admin privileges
$stmt = $pdo->prepare("
    SELECT a.is_super_admin 
    FROM admin_profiles a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$current_admin = $stmt->fetch();

// Only super admins can edit other admins
if (!isset($current_admin['is_super_admin']) || !$current_admin['is_super_admin']) {
    $_SESSION['error'] = 'You do not have permission to perform this action.';
    header('Location: manage_admins.php');
    exit();
}

$admin_id = $_GET['id'] ?? 0;

// Get admin details
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.is_active,
           a.full_name, a.is_super_admin, a.phone
    FROM users u
    LEFT JOIN admin_profiles a ON u.id = a.user_id
    WHERE u.id = ? AND u.role = 'admin'
");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    $_SESSION['error'] = 'Admin user not found';
    header('Location: manage_admins.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Extract form data
        $email = sanitize_input($_POST['email'] ?? '');
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $new_password = $_POST['new_password'] ?? '';
        
        // Basic validation
        if (empty($email) || empty($full_name)) {
            throw new Exception('Email and full name are required.');
        }
        
        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format.');
        }
        
        // Check if email is already used by another user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->execute([$email, $admin_id]);
        if ($stmt->fetch()) {
            throw new Exception('Email address is already in use by another user.');
        }
        
        $pdo->beginTransaction();
        
        // Update user record
        $stmt = $pdo->prepare("UPDATE users SET email = ?, is_active = ? WHERE id = ?");
        $stmt->execute([$email, $is_active, $admin_id]);
        
        // Update admin profile
        $stmt = $pdo->prepare("
            UPDATE admin_profiles 
            SET full_name = ?, phone = ?, is_super_admin = ?
            WHERE user_id = ?
        ");
        $stmt->execute([$full_name, $phone, $is_super_admin, $admin_id]);
        
        // Update password if provided
        if (!empty($new_password)) {
            if (!validate_password($new_password)) {
                throw new Exception('Password must be at least 8 characters and include uppercase, lowercase, and numbers.');
            }
            
            $hashed_password = hash_password($new_password);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $admin_id]);
        }
        
        // Log the activity
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, details, ip_address)
            VALUES (?, 'update', 'admin', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $admin_id,
            "Updated admin account information",
            $_SERVER['REMOTE_ADDR']
        ]);
        
        $pdo->commit();
        
        $_SESSION['success'] = 'Admin account updated successfully.';
        header('Location: view_admin.php?id=' . $admin_id);
        exit();
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Edit Admin: " . $admin['username'];
include 'includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Admin</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_admins.php">Administrators</a></li>
                    <li class="breadcrumb-item active">Edit Admin</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            Edit Admin: <?php echo htmlspecialchars($admin['username']); ?>
                        </h3>
                    </div>
                    
                    <form method="POST" class="needs-validation" novalidate>
                        <div class="card-body">
                            <div class="row">
                                <!-- Left column -->
                                <div class="col-md-6">
                                    <h5 class="mb-3 border-bottom pb-2">Account Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="username" class="form-label">Username</label>
                                        <input type="text" class="form-control" id="username" 
                                               value="<?php echo htmlspecialchars($admin['username']); ?>" readonly>
                                        <div class="form-text text-muted">Username cannot be changed</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="new_password" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="new_password" name="new_password">
                                        <div class="form-text text-muted">Leave blank to keep current password</div>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                               <?php echo $admin['is_active'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_active">Account Active</label>
                                        <div class="form-text text-muted">Inactive accounts cannot log in</div>
                                    </div>
                                </div>
                                
                                <!-- Right column -->
                                <div class="col-md-6">
                                    <h5 class="mb-3 border-bottom pb-2">Admin Information</h5>
                                    
                                    <div class="mb-3">
                                        <label for="full_name" class="form-label">Full Name</label>
                                        <input type="text" class="form-control" id="full_name" name="full_name" 
                                               value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Phone (Optional)</label>
                                        <input type="text" class="form-control" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($admin['phone']); ?>">
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="is_super_admin" name="is_super_admin" 
                                               <?php echo $admin['is_super_admin'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="is_super_admin">Super Administrator</label>
                                        <div class="form-text text-muted">Super admins can manage other admins and have full system access</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card-footer">
                            <div class="d-flex justify-content-between">
                                <a href="view_admin.php?id=<?php echo $admin_id; ?>" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Admin
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Password validation if a new password is provided
            const password = document.getElementById('new_password').value;
            if (password) {
                const regex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
                if (!regex.test(password)) {
                    event.preventDefault();
                    alert('Password must be at least 8 characters and include uppercase, lowercase, and numbers.');
                }
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>