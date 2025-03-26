<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo->beginTransaction();

    // Create temporary table with the answers we want to keep (most recent per question)
    $pdo->exec("
        CREATE TEMPORARY TABLE temp_answers AS
        SELECT ua1.*
        FROM user_answers ua1
        INNER JOIN (
            SELECT exam_attempt_id, question_id, MAX(id) as max_id
            FROM user_answers
            GROUP BY exam_attempt_id, question_id
        ) ua2 ON ua1.id = ua2.max_id
    ");

    // Delete all answers
    $pdo->exec("DELETE FROM user_answers");

    // Reinsert only the unique answers
    $pdo->exec("INSERT INTO user_answers SELECT * FROM temp_answers");

    // Drop temporary table
    $pdo->exec("DROP TEMPORARY TABLE temp_answers");

    $pdo->commit();
    echo "Successfully cleaned up duplicate answers";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Error: " . $e->getMessage();
}
?>