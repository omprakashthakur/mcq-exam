<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = 'Please login as a student to take the exam.';
    header('Location: login.php');
    exit();
}

// Get access URL parameter
$access_url = $_GET['access'] ?? '';

if (empty($access_url)) {
    $_SESSION['error'] = 'Invalid access URL.';
    header('Location: student/dashboard.php');
    exit();
}

try {
    // Get exam access details
    $stmt = $pdo->prepare("
        SELECT ea.*, e.title, e.duration_minutes, e.description
        FROM exam_access ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        WHERE ea.access_url = ? AND ea.user_id = ? AND ea.is_used = 0
        AND ea.expiry_date > NOW()
    ");
    $stmt->execute([$access_url, $_SESSION['user_id']]);
    $exam_access = $stmt->fetch();

    if (!$exam_access) {
        throw new Exception('Invalid or expired exam access.');
    }

    // Check if there's already an attempt in progress
    $stmt = $pdo->prepare("
        SELECT id, status 
        FROM exam_attempts 
        WHERE user_id = ? AND exam_set_id = ? 
        AND status = 'in_progress'
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_access['exam_set_id']]);
    $existing_attempt = $stmt->fetch();

    if ($existing_attempt) {
        // Redirect to continue exam
        header("Location: student/continue_exam.php?attempt_id=" . $existing_attempt['id']);
        exit();
    }

    // Create new exam attempt
    $pdo->beginTransaction();

    // Mark access as used
    $stmt = $pdo->prepare("UPDATE exam_access SET is_used = 1 WHERE id = ?");
    $stmt->execute([$exam_access['id']]);

    // Create attempt record
    $stmt = $pdo->prepare("
        INSERT INTO exam_attempts (user_id, exam_set_id, start_time, status)
        VALUES (?, ?, NOW(), 'in_progress')
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_access['exam_set_id']]);
    $attempt_id = $pdo->lastInsertId();

    $pdo->commit();

    // Redirect to exam page
    header("Location: student/continue_exam.php?attempt_id=" . $attempt_id);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
    header('Location: student/dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Start Exam - MCQ Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .exam-access-container {
            max-width: 500px;
            margin: 100px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="exam-access-container">
            <h2 class="text-center mb-4">Access Exam</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="access_code" class="form-label">Access Code</label>
                    <input type="text" class="form-control" id="access_code" name="access_code" 
                           placeholder="Enter your access code" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Start Exam</button>
            </form>

            <div class="text-center mt-3">
                <p>Already have an account? <a href="login.php">Login here</a></p>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>