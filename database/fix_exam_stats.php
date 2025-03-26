<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Get all completed exam attempts and recalculate their statistics
    $stmt = $pdo->prepare("
        SELECT 
            ea.id as attempt_id,
            COUNT(DISTINCT q.id) as total_questions,
            COUNT(DISTINCT CASE WHEN ua.id IS NOT NULL THEN q.id END) as answered_questions,
            SUM(DISTINCT CASE WHEN ua.is_correct = 1 THEN 1 ELSE 0 END) as correct_answers
        FROM exam_attempts ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        LEFT JOIN questions q ON q.exam_set_id = e.id
        LEFT JOIN user_answers ua ON ua.question_id = q.id AND ua.exam_attempt_id = ea.id
        GROUP BY ea.id
    ");
    $stmt->execute();
    $attempts = $stmt->fetchAll();

    $updated = 0;
    foreach ($attempts as $attempt) {
        $incorrect_answers = $attempt['answered_questions'] - $attempt['correct_answers'];
        $unanswered = $attempt['total_questions'] - $attempt['answered_questions'];
        $score = $attempt['total_questions'] > 0 ? 
                 round(($attempt['correct_answers'] / $attempt['total_questions']) * 100, 2) : 0;

        $updateStmt = $pdo->prepare("
            UPDATE exam_attempts 
            SET 
                total_questions = ?,
                correct_answers = ?,
                incorrect_answers = ?,
                unanswered_questions = ?,
                score = ?
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $attempt['total_questions'],
            $attempt['correct_answers'],
            $incorrect_answers,
            $unanswered,
            $score,
            $attempt['attempt_id']
        ]);
        
        $updated += $updateStmt->rowCount();
    }

    $pdo->commit();
    echo "Successfully updated statistics for {$updated} exam attempts";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>