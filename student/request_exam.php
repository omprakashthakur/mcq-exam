<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exam_id = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
        $request_type = filter_input(INPUT_POST, 'request_type', FILTER_SANITIZE_STRING);
        $reason = filter_input(INPUT_POST, 'request_reason', FILTER_UNSAFE_RAW);
        $preferred_date = filter_input(INPUT_POST, 'preferred_date', FILTER_SANITIZE_STRING);
        
        if (!$exam_id || !$reason || !$preferred_date) {
            throw new Exception('Please fill in all required fields');
        }

        // Validate preferred date
        $preferred = new DateTime($preferred_date);
        $now = new DateTime();
        if ($preferred <= $now) {
            throw new Exception('Preferred date must be in the future');
        }

        $pdo->beginTransaction();

        if ($request_type === 'retake') {
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

            // Insert retake request
            $stmt = $pdo->prepare("
                INSERT INTO exam_retake_requests (
                    user_id,
                    exam_set_id,
                    previous_attempt_id,
                    request_date,
                    status,
                    admin_remarks,
                    retake_count
                ) VALUES (
                    ?,
                    ?,
                    (SELECT id FROM exam_attempts 
                     WHERE user_id = ? AND exam_set_id = ? 
                     ORDER BY start_time DESC LIMIT 1),
                    NOW(),
                    'pending',
                    NULL,
                    ?
                )
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $exam_id,
                $_SESSION['user_id'],
                $exam_id,
                $attempt_data['attempt_count'] + 1
            ]);
        } else {
            // Check for existing requests
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM exam_requests 
                WHERE user_id = ? AND exam_set_id = ? AND status = 'pending'
            ");
            $stmt->execute([$_SESSION['user_id'], $exam_id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('You already have a pending request for this exam');
            }

            // Insert new exam request
            $stmt = $pdo->prepare("
                INSERT INTO exam_requests (
                    user_id,
                    exam_set_id,
                    request_reason,
                    preferred_date,
                    request_date,
                    status
                ) VALUES (?, ?, ?, ?, NOW(), 'pending')
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $exam_id,
                $reason,
                $preferred_date
            ]);
        }

        // Create notification for admin
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                message,
                related_id
            ) SELECT 
                u.id,
                ?,
                CONCAT('New ', ?, ' request from ', s.username),
                ?
            FROM users u
            CROSS JOIN users s
            WHERE u.role = 'admin' AND s.id = ?
        ");
        $stmt->execute([
            $request_type === 'retake' ? 'retake_request' : 'exam_request',
            $request_type === 'retake' ? 'exam retake' : 'exam',
            $exam_id,
            $_SESSION['user_id']
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Your ' . ($request_type === 'retake' ? 'retake' : 'exam') . ' request has been submitted successfully';
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: dashboard.php');
    exit();
}

// If not POST request, redirect back to dashboard
header('Location: dashboard.php');
exit();
?>