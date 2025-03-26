<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate input
        $username = sanitize_input($_POST['username']);
        $email = sanitize_input($_POST['email']);
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        $full_name = sanitize_input($_POST['full_name']);
        $is_super_admin = isset($_POST['is_super_admin']) ? 1 : 0;

        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
            throw new Exception('All required fields must be filled out');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Validate password match
        if ($password !== $confirm_password) {
            throw new Exception('Passwords do not match');
        }

        // Validate password strength
        if (!validate_password($password)) {
            throw new Exception('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number');
        }

        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Username or email already exists');
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Create admin user account
        $hashedPassword = hash_password($password);
        $stmt = $pdo->prepare("
            INSERT INTO users (
                username, 
                email, 
                password, 
                role,
                is_active, 
                is_verified,
                created_by
            ) VALUES (?, ?, ?, 'admin', 1, 1, ?)
        ");
        $stmt->execute([
            $username, 
            $email, 
            $hashedPassword,
            $_SESSION['user_id']
        ]);
        $userId = $pdo->lastInsertId();

        // Create admin profile
        $stmt = $pdo->prepare("
            INSERT INTO admin_profiles (
                user_id, 
                full_name,
                is_super_admin
            ) VALUES (?, ?, ?)
        ");
        $stmt->execute([
            $userId, 
            $full_name,
            $is_super_admin
        ]);

        $pdo->commit();

        $_SESSION['success'] = 'Admin account created successfully.';
        header('Location: create_admin.php');
        exit();

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

$pageTitle = "Create Admin Account";
include 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create New Admin Account</h3>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <!-- Account Information -->
                        <h5 class="text-muted mb-3 border-bottom pb-2">Account Information</h5>
                        
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="invalid-feedback">Please provide a username.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                            <div class="invalid-feedback">Please provide a valid email.</div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                    </div>
                                    <div class="invalid-feedback">Please provide a password.</div>
                                    <small class="form-text text-muted">
                                        Password must be at least 8 characters with uppercase, lowercase, and numbers.
                                    </small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                    </div>
                                    <div class="invalid-feedback">Please confirm the password.</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Admin Information -->
                        <h5 class="text-muted mb-3 border-bottom pb-2 mt-4">Admin Information</h5>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-user-circle"></i></span>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="invalid-feedback">Please provide the admin's full name.</div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="is_super_admin" name="is_super_admin">
                            <label class="form-check-label" for="is_super_admin">
                                Super Administrator (Full permissions)
                            </label>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus me-2"></i> Create Admin Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Password match validation
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                event.preventDefault();
                alert('Passwords do not match!');
            }
            
            // Password strength validation
            const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
            if (!passwordRegex.test(password)) {
                event.preventDefault();
                alert('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.');
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>