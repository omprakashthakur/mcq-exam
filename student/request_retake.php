<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: dashboard.php');
    exit();
}

try {
    $exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
    $retake_reason = filter_input(INPUT_POST, 'retake_reason', FILTER_UNSAFE_RAW);
    
    if (!$exam_id || !$retake_reason) {
        throw new Exception('Please provide both exam and reason for retake');
    }

    $pdo->beginTransaction();

    // Check previous attempts
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as attempt_count,
               MAX(score) as best_score
        FROM exam_attempts 
        WHERE user_id = ? AND exam_set_id = ? AND status = 'completed'
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_id]);
    $attempt_data = $stmt->fetch();

    if ($attempt_data['attempt_count'] == 0) {
        throw new Exception('Cannot request retake - no previous attempts found');
    }

    // Check for existing pending retake request
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM exam_retake_requests 
        WHERE user_id = ? AND exam_set_id = ? AND status = 'pending'
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_id]);
    if ($stmt->fetchColumn() > 0) {
        throw new Exception('You already have a pending retake request for this exam');
    }

    // Insert retake request
    $stmt = $pdo->prepare("
        INSERT INTO exam_retake_requests (
            user_id,
            exam_set_id,
            previous_attempt_id,
            request_date,
            status,
            retake_count,
            admin_remarks
        ) VALUES (
            ?,
            ?,
            (SELECT id FROM exam_attempts 
             WHERE user_id = ? AND exam_set_id = ? 
             ORDER BY start_time DESC LIMIT 1),
            NOW(),
            'pending',
            ?,
            ?
        )
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $exam_id,
        $_SESSION['user_id'],
        $exam_id,
        $attempt_data['attempt_count'] + 1,
        $retake_reason
    ]);

    // Create notification for admin
    $stmt = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            message,
            related_id
        ) SELECT 
            u.id,
            'retake_request',
            CONCAT('New retake request from ', s.username),
            ?
        FROM users u
        CROSS JOIN users s
        WHERE u.role = 'admin' AND s.id = ?
    ");
    $stmt->execute([$exam_id, $_SESSION['user_id']]);

    $pdo->commit();
    $_SESSION['success'] = 'Your retake request has been submitted successfully';

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to dashboard
header('Location: dashboard.php');
exit();