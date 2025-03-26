<?php
session_start();
require_once 'config/database.php';

$error = '';
$success = '';

if (isset($_GET['token'])) {
    try {
        $token = $_GET['token'];
        
        // Find user with this token
        $stmt = $pdo->prepare("SELECT id FROM users WHERE verification_token = ? AND is_verified = 0");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if ($user) {
            // Update user as verified
            $stmt = $pdo->prepare("UPDATE users SET is_verified = 1, verification_token = NULL WHERE id = ?");
            $stmt->execute([$user['id']]);
            $success = 'Your account has been verified successfully. You can now login.';
        } else {
            $error = 'Invalid verification token or account already verified.';
        }
    } catch (Exception $e) {
        $error = 'Verification failed. Please try again.';
    }
} else {
    $error = 'No verification token provided.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Verification - MCQ Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .verify-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verify-container">
            <h2 class="mb-4">Account Verification</h2>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <p>Return to <a href="login.php">login page</a></p>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <p>Click here to <a href="login.php">login</a></p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>