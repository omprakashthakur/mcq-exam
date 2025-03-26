<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $examId = $_POST['exam_id'] ?? '';
        $studentId = $_POST['student_id'] ?? '';
        $studentEmails = isset($_POST['student_emails']) ? array_map('trim', explode(',', $_POST['student_emails'])) : [];
        $expiryDays = intval($_POST['expiry_days'] ?? 7);

        if (empty($examId) || (empty($studentId) && empty($studentEmails))) {
            throw new Exception('Please provide all required information');
        }

        // Begin transaction
        $pdo->beginTransaction();

        // If student ID is provided, get their email
        if ($studentId) {
            $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ? AND role = 'user'");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch();
            if ($student) {
                $studentEmails = [$student['email']];
            } else {
                throw new Exception('Invalid student ID');
            }
        }

        foreach ($studentEmails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format: $email");
            }

            // Check if user exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                // Create temporary user account
                $tempUsername = 'student_' . bin2hex(random_bytes(5));
                $tempPassword = bin2hex(random_bytes(8));
                $hashedPassword = hash_password($tempPassword);

                $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, is_verified) VALUES (?, ?, ?, 'user', 1)");
                $stmt->execute([$tempUsername, $email, $hashedPassword]);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $user['id'];
            }

            // Generate unique access code and URL
            $accessCode = strtoupper(bin2hex(random_bytes(5)));
            $accessUrl = bin2hex(random_bytes(16));
            $expiryDate = date('Y-m-d H:i:s', strtotime("+$expiryDays days"));

            // Create exam access record
            $stmt = $pdo->prepare("
                INSERT INTO exam_access (exam_set_id, user_id, access_code, access_url, expiry_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$examId, $userId, $accessCode, $accessUrl, $expiryDate]);

            // Log admin activity
            $stmt = $pdo->prepare("
                INSERT INTO admin_activity_log (admin_id, action_type, entity_type, entity_id, details)
                VALUES (?, 'share', 'exam_access', ?, ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $examId,
                "Shared exam access with user: $email"
            ]);

            // Send email notification
            $examUrl = "http://{$_SERVER['HTTP_HOST']}/mcq-exam/start_exam.php?access=" . $accessUrl;
            $subject = "MCQ Exam Access Information";
            $message = "Hello,\n\n";
            $message .= "You have been granted access to take an exam in the MCQ Exam System.\n\n";
            $message .= "Access Code: $accessCode\n";
            $message .= "Exam URL: $examUrl\n";
            if (isset($tempPassword)) {
                $message .= "\nYour temporary login credentials:\n";
                $message .= "Username: $tempUsername\n";
                $message .= "Password: $tempPassword\n";
            }
            $message .= "\nThis link will expire on: " . date('Y-m-d H:i:s', strtotime($expiryDate)) . "\n\n";
            $message .= "Best regards,\nMCQ Exam System Team";
            
            mail($email, $subject, $message, "From: noreply@mcqexam.com");
        }

        $pdo->commit();
        $success = 'Exam access has been shared successfully.';

        // Redirect back to student view if coming from there
        if ($studentId) {
            header("Location: view_student.php?id=$studentId&success=" . urlencode($success));
            exit();
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

// Get available exams
$stmt = $pdo->query("SELECT id, title FROM exam_sets ORDER BY created_at DESC");
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Share Exam - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container my-4">
        <h2>Share Exam</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">Select Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id" required>
                            <option value="">Choose exam...</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>">
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="student_id" class="form-label">Student ID (optional)</label>
                        <input type="text" class="form-control" id="student_id" name="student_id" placeholder="Enter student ID">
                    </div>

                    <div class="mb-3">
                        <label for="student_emails" class="form-label">Student Emails</label>
                        <textarea class="form-control" id="student_emails" name="student_emails" rows="4" 
                                placeholder="Enter student email addresses, separated by commas"></textarea>
                        <div class="form-text">Enter multiple email addresses separated by commas</div>
                    </div>

                    <div class="mb-3">
                        <label for="expiry_days" class="form-label">Access Duration (days)</label>
                        <input type="number" class="form-control" id="expiry_days" name="expiry_days" 
                               value="7" min="1" max="30" required>
                    </div>

                    <button type="submit" class="btn btn-primary">Share Exam</button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>