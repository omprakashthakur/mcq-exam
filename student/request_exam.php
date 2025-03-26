<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

$exam_id = $_GET['exam_id'] ?? 0;

try {
    // Check if exam exists and is active
    $stmt = $pdo->prepare("
        SELECT e.*, 
            (SELECT COUNT(*) FROM exam_requests 
             WHERE user_id = ? AND exam_set_id = ? AND status = 'pending') as pending_requests,
            COUNT(DISTINCT q.id) as question_count
        FROM exam_sets e
        LEFT JOIN questions q ON e.id = q.exam_set_id
        WHERE e.id = ? AND e.is_active = 1
        GROUP BY e.id
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_id, $exam_id]);
    $exam = $stmt->fetch();

    if (!$exam) {
        throw new Exception('Exam not found or not available');
    }

    if ($exam['pending_requests'] > 0) {
        throw new Exception('You already have a pending request for this exam');
    }

    if ($exam['question_count'] == 0) {
        throw new Exception('This exam is not yet ready for requests');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $reason = filter_input(INPUT_POST, 'request_reason', FILTER_UNSAFE_RAW);
        $reason = strip_tags(trim($reason));
        
        if (empty($reason)) {
            throw new Exception('Please provide a reason for your exam request');
        }

        // Insert exam request
        $stmt = $pdo->prepare("
            INSERT INTO exam_requests (
                user_id,
                exam_set_id,
                request_reason,
                preferred_date,
                request_date
            ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $exam_id,
            $reason
        ]);

        $_SESSION['success'] = 'Exam request submitted successfully. Please wait for admin approval.';
        header('Location: dashboard.php');
        exit();
    }

    $pageTitle = "Request Exam Access";
    include '../includes/header.php';
    ?>

    <div class="container py-4">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Request Exam Access</h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Exam:</strong> <?php echo htmlspecialchars($exam['title']); ?><br>
                    <strong>Duration:</strong> <?php echo $exam['duration_minutes']; ?> minutes<br>
                    <strong>Questions:</strong> <?php echo $exam['question_count']; ?>
                </div>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="request_reason" class="form-label">Request Reason</label>
                        <textarea class="form-control" id="request_reason" name="request_reason" rows="4" required
                                placeholder="Please explain why you want to take this exam..."></textarea>
                    </div>
                    
                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                        <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php';

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?>