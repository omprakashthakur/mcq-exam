<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Get all completed exam attempts
    $stmt = $pdo->prepare("
        SELECT 
            ea.id,
            ea.exam_set_id,
            ea.start_time,
            ea.end_time,
            COUNT(DISTINCT q.id) as total_questions,
            COUNT(DISTINCT CASE WHEN ua.is_correct = 1 THEN ua.id END) as correct_answers,
            COUNT(DISTINCT CASE WHEN ua.is_correct = 0 THEN ua.id END) as incorrect_answers,
            COUNT(DISTINCT CASE WHEN ua.id IS NULL THEN q.id END) as unanswered_questions
        FROM exam_attempts ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        JOIN questions q ON e.id = q.exam_set_id
        LEFT JOIN user_answers ua ON ea.id = ua.exam_attempt_id AND q.id = ua.question_id
        WHERE ea.status = 'completed'
        GROUP BY ea.id
    ");
    $stmt->execute();
    $attempts = $stmt->fetchAll();

    foreach ($attempts as $attempt) {
        // Calculate time taken
        $start_time = new DateTime($attempt['start_time']);
        $end_time = new DateTime($attempt['end_time']);
        $interval = $start_time->diff($end_time);
        $time_taken = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;

        // Calculate score
        $score = $attempt['total_questions'] > 0 
            ? round(($attempt['correct_answers'] / $attempt['total_questions']) * 100, 2) 
            : 0;

        // Update attempt with recalculated values
        $stmt = $pdo->prepare("
            UPDATE exam_attempts 
            SET 
                score = ?,
                total_questions = ?,
                correct_answers = ?,
                incorrect_answers = ?,
                unanswered_questions = ?,
                time_taken = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $score,
            $attempt['total_questions'],
            $attempt['correct_answers'],
            $attempt['incorrect_answers'],
            $attempt['unanswered_questions'],
            $time_taken,
            $attempt['id']
        ]);
    }

    $pdo->commit();
    echo "Successfully fixed " . count($attempts) . " exam results.\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error fixing results: " . $e->getMessage() . "\n";
}