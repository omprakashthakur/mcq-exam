<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Sanitize and validate input
        $username = sanitize_input($_POST['username'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        $full_name = sanitize_input($_POST['full_name'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $role = $_POST['role'] ?? 'user';  // Default to regular user role

        // Validate required fields
        if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($full_name)) {
            throw new Exception('Please fill out all required fields (username, email, password, and full name)');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Please enter a valid email address');
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
            throw new Exception('Username or email already exists. Please try another one.');
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Create user account - REMOVED is_active field that doesn't exist in the schema
        $hashedPassword = hash_password($password);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashedPassword, 'user']);
        $userId = $pdo->lastInsertId();

        // Create student profile
        $stmt = $pdo->prepare("INSERT INTO student_profiles (user_id, full_name, address, phone) VALUES (?, ?, ?, ?)");
        $stmt->execute([$userId, $full_name, $address, $phone]);

        // Generate unique registration token
        $token = bin2hex(random_bytes(32));
        $stmt = $pdo->prepare("UPDATE users SET verification_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);

        $pdo->commit();

        // Send verification email
        $verificationUrl = "http://{$_SERVER['HTTP_HOST']}/mcq-exam/verify.php?token=" . $token;
        $to = $email;
        $subject = "Verify your MCQ Exam System account";
        $message = "Dear $full_name,\n\n";
        $message .= "Thank you for registering with the MCQ Exam System. Please click the link below to verify your account:\n\n";
        $message .= $verificationUrl . "\n\n";
        $message .= "If you did not request this registration, please ignore this email.\n\n";
        $message .= "Best regards,\nMCQ Exam System Team";
        $headers = "From: noreply@mcqexam.com";

        mail($to, $subject, $message, $headers);

        $_SESSION['success'] = 'Registration successful! Please check your email to verify your account.';
        header('Location: ../login.php');
        exit();

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header('Location: ../register.php');
        exit();
    }
} else {
    header('Location: ../register.php');
    exit();
}
?>