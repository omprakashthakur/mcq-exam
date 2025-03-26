<?php
session_start();
require_once 'config/database.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Registration - MCQ Exam System</title>

    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- AdminLTE -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    <style>
        .register-page {
            align-items: center;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
        }
        .register-box {
            width: 450px;
            margin: 0 auto;
        }
        .btn-primary {
            background-color: #3a6acf;
            border-color: #3a6acf;
        }
        .btn-primary:hover {
            background-color: #2a5abf;
            border-color: #2a5abf;
        }
        .register-card-body {
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="hold-transition register-page">
<div class="register-box">
    <div class="register-logo">
        <a href="index.php"><b>MCQ</b> Exam System</a>
    </div>

    <div class="card">
        <div class="card-body register-card-body">
            <p class="login-box-msg">Student Registration</p>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <form action="auth/process_register.php" method="post">
                <input type="hidden" name="role" value="user">
                
                <!-- Account Information -->
                <h6 class="text-muted mb-3 border-bottom pb-2">Account Information</h6>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="username" placeholder="Username" required>
                    <div class="input-group-text">
                        <span class="fas fa-user"></span>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email" required>
                    <div class="input-group-text">
                        <span class="fas fa-envelope"></span>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="password" class="form-control" name="password" placeholder="Password" required>
                    <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="password" class="form-control" name="confirm_password" placeholder="Confirm password" required>
                    <div class="input-group-text">
                        <span class="fas fa-lock"></span>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <h6 class="text-muted mb-3 border-bottom pb-2 mt-4">Personal Information</h6>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="full_name" placeholder="Full Name" required>
                    <div class="input-group-text">
                        <span class="fas fa-user-circle"></span>
                    </div>
                </div>
                
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="phone" placeholder="Phone Number (optional)">
                    <div class="input-group-text">
                        <span class="fas fa-phone"></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <textarea class="form-control" name="address" placeholder="Address" required rows="2"></textarea>
                </div>
                
                <div class="row mb-3">
                    <div class="col-8">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="agreeTerms" name="terms" required>
                            <label class="form-check-label" for="agreeTerms">
                                I agree to the <a href="#">terms</a>
                            </label>
                        </div>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block w-100">Register</button>
                    </div>
                </div>
            </form>

            <div class="text-center mt-3">
                <p class="mb-1">
                    <a href="login.php">I already have an account</a>
                </p>
                <p class="mb-0">
                    <a href="index.php" class="text-center">Back to home</a>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>

<!-- Form validation script -->
<script>
document.querySelector('form').addEventListener('submit', function(e) {
    const password = document.querySelector('input[name="password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (password !== confirmPassword) {
        e.preventDefault();
        alert('Passwords do not match!');
    }
    
    // Password strength validation
    const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
    if (!passwordRegex.test(password)) {
        e.preventDefault();
        alert('Password must be at least 8 characters long and contain at least one uppercase letter, one lowercase letter, and one number.');
    }
});
</script>
</body>
</html>