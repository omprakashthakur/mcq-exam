<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// Set cache control headers to prevent caching
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Type: application/json; charset=utf-8');

// Require student authentication
require_auth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate inputs using filter
        $attempt_id = filter_input(INPUT_POST, 'attempt_id', FILTER_VALIDATE_INT);
        $question_id = filter_input(INPUT_POST, 'question_id', FILTER_VALIDATE_INT);
        
        if (!$attempt_id || !$question_id) {
            throw new Exception('Invalid input parameters');
        }
        
        // Decode JSON selected options with error handling
        $selected_options = [];
        if (isset($_POST['selected_options'])) {
            $selected_options = json_decode($_POST['selected_options'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid answer format');
            }
        }

        // Validate attempt exists and belongs to user with optimized query
        $stmt = $pdo->prepare("
            SELECT 
                ea.id as attempt_id,
                q.id as question_id,
                q.max_correct_answers,
                q.is_multiple_answer,
                q.exam_set_id,
                GROUP_CONCAT(ca.correct_option ORDER BY ca.correct_option) as correct_answers,
                COUNT(DISTINCT ca.correct_option) as correct_count
            FROM exam_attempts ea
            JOIN questions q ON q.id = ? AND q.exam_set_id = ea.exam_set_id
            LEFT JOIN correct_answers ca ON ca.question_id = q.id
            WHERE ea.id = ? AND ea.user_id = ? AND ea.status = 'in_progress'
            GROUP BY ea.id, q.id
        ");
        $stmt->execute([$question_id, $attempt_id, $_SESSION['user_id']]);
        $attempt = $stmt->fetch();

        if (!$attempt) {
            throw new Exception('Invalid attempt or question');
        }

        // Validate selected options
        if (!is_array($selected_options)) {
            throw new Exception('Invalid answer format');
        }

        // For single answer questions, only allow one answer
        if (!$attempt['is_multiple_answer'] && count($selected_options) > 1) {
            throw new Exception('Only one answer allowed for this question');
        }

        // For multiple answer questions, check max answers
        if ($attempt['is_multiple_answer'] && count($selected_options) > $attempt['max_correct_answers']) {
            throw new Exception('Too many options selected');
        }

        // Sort and validate selected options
        sort($selected_options);
        $selected_answer = implode(',', $selected_options);
        
        // Determine if answer is correct
        if ($attempt['is_multiple_answer']) {
            $is_correct = (count($selected_options) === $attempt['correct_count'] && 
                          $selected_answer === $attempt['correct_answers']) ? 1 : 0;
        } else {
            $correct_answers = explode(',', $attempt['correct_answers']);
            $is_correct = !empty($selected_options) && in_array($selected_options[0], $correct_answers) ? 1 : 0;
        }

        $pdo->beginTransaction();

        // Use REPLACE INTO to handle duplicate answers
        $stmt = $pdo->prepare("
            REPLACE INTO user_answers (
                exam_attempt_id, 
                question_id, 
                selected_options,
                is_correct
            ) VALUES (?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $attempt_id,
            $question_id,
            $selected_answer,
            $is_correct
        ]);

        $pdo->commit();

        echo json_encode([
            'status' => 'success',
            'message' => 'Answer saved successfully',
            'data' => [
                'is_correct' => $is_correct,
                'selected_options' => $selected_options
            ]
        ]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit();
}

http_response_code(405);
echo json_encode([
    'status' => 'error',
    'message' => 'Method not allowed'
]);
exit();
?>