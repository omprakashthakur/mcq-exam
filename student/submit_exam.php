<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

// Accept both POST and GET for handling auto-submission of expired exams
if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'])) {
    $_SESSION['error'] = 'Invalid request method';
    header('Location: dashboard.php');
    exit();
}

$attempt_id = $_POST['attempt_id'] ?? $_GET['attempt_id'] ?? 0;
if (!$attempt_id) {
    $_SESSION['error'] = 'Invalid attempt ID';
    header('Location: dashboard.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Lock the exam attempt and get details with a single optimized query
    $stmt = $pdo->prepare("
        SELECT 
            ea.*,
            e.title as exam_title,
            e.pass_percentage,
            e.duration_minutes,
            u.username,
            COUNT(DISTINCT q.id) as total_questions,
            COUNT(DISTINCT CASE WHEN ua.is_correct = 1 THEN q.id END) as correct_answers,
            COUNT(DISTINCT CASE WHEN ua.id IS NOT NULL THEN q.id END) as answered_questions
        FROM exam_attempts ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        JOIN users u ON ea.user_id = u.id
        LEFT JOIN questions q ON q.exam_set_id = ea.exam_set_id
        LEFT JOIN user_answers ua ON ua.question_id = q.id AND ua.exam_attempt_id = ea.id
        WHERE ea.id = ? AND ea.user_id = ? AND ea.status = 'in_progress'
        GROUP BY ea.id
        FOR UPDATE
    ");
    $stmt->execute([$attempt_id, $_SESSION['user_id']]);
    $attempt = $stmt->fetch();

    if (!$attempt) {
        throw new Exception('Invalid exam attempt or already submitted');
    }

    // Calculate time taken and validate duration
    $start_time = new DateTime($attempt['start_time']);
    $end_time = new DateTime();
    $interval = $start_time->diff($end_time);
    $time_taken = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

    // Log if exam exceeded duration but still process submission
    if ($time_taken > ($attempt['duration_minutes'] + 1)) {
        $overtime = $time_taken - $attempt['duration_minutes'];
        error_log("Warning: Exam attempt {$attempt_id} exceeded duration by {$overtime} minutes");
    }

    // Calculate final statistics
    $total_questions = $attempt['total_questions'];
    $correct_answers = $attempt['correct_answers'];
    $incorrect_answers = $attempt['answered_questions'] - $correct_answers;
    $unanswered = $total_questions - $attempt['answered_questions'];
    $score = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 2) : 0;

    // Update attempt with final results
    $stmt = $pdo->prepare("
        UPDATE exam_attempts 
        SET 
            status = 'completed',
            score = ?,
            total_questions = ?,
            correct_answers = ?,
            incorrect_answers = ?,
            unanswered_questions = ?,
            time_taken = ?,
            end_time = NOW()
        WHERE id = ? AND status = 'in_progress'
    ");
    
    $stmt->execute([
        $score,
        $total_questions,
        $correct_answers,
        $incorrect_answers,
        $unanswered,
        $time_taken,
        $attempt_id
    ]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Exam was already submitted');
    }

    // Prepare notification message
    $student_message = sprintf(
        "You completed %s with score: %.1f%% (Correct: %d, Incorrect: %d, Unanswered: %d)",
        $attempt['exam_title'],
        $score,
        $correct_answers,
        $incorrect_answers,
        $unanswered
    );

    $admin_message = sprintf(
        "Student %s completed %s with score: %.1f%%",
        $attempt['username'],
        $attempt['exam_title'],
        $score
    );

    // Insert notifications
    $stmt = $pdo->prepare("
        INSERT INTO notifications (user_id, type, message, related_id)
        VALUES 
        (?, 'exam_completed', ?, ?),
        ((SELECT id FROM users WHERE role = 'admin' LIMIT 1), 'student_exam_completed', ?, ?)
    ");
    
    $stmt->execute([
        $_SESSION['user_id'],
        $student_message,
        $attempt_id,
        $admin_message,
        $attempt_id
    ]);

    $pdo->commit();
    
    // Set success message and cache control headers
    $_SESSION['success'] = 'Exam submitted successfully';
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header("Location: view_result.php?attempt_id=" . $attempt_id);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error submitting exam (attempt_id: {$attempt_id}): " . $e->getMessage());
    $_SESSION['error'] = 'Error submitting exam: ' . $e->getMessage();
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header("Location: continue_exam.php?attempt_id=" . $attempt_id);
    exit();
}
?>