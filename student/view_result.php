<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

$attempt_id = $_GET['attempt_id'] ?? 0;

// Get attempt details with exam info and calculated time taken - using optimized query
$stmt = $pdo->prepare("
    SELECT DISTINCT
        ea.*,
        e.title as exam_title,
        e.pass_percentage,
        e.duration_minutes,
        COUNT(DISTINCT q.id) as total_questions,
        COUNT(DISTINCT CASE WHEN ua.is_correct = 1 THEN q.id END) as correct_answers,
        COUNT(DISTINCT CASE WHEN ua.id IS NOT NULL THEN q.id END) as answered_questions
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    LEFT JOIN questions q ON q.exam_set_id = ea.exam_set_id
    LEFT JOIN user_answers ua ON ua.question_id = q.id AND ua.exam_attempt_id = ?
    WHERE ea.id = ? AND ea.user_id = ?
    GROUP BY ea.id
");
$stmt->execute([$attempt_id, $attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = 'Exam attempt not found';
    header('Location: dashboard.php');
    exit();
}

// Get questions with answers and correct options - using optimized query
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        ua.selected_options,
        ua.is_correct as answer_is_correct,
        GROUP_CONCAT(DISTINCT ca.correct_option ORDER BY ca.correct_option) as correct_answers,
        COUNT(DISTINCT ca.correct_option) as required_answers,
        IF(ua.id IS NULL, 0, 1) as is_answered
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.exam_attempt_id = ?
    LEFT JOIN correct_answers ca ON q.id = ca.question_id
    WHERE q.exam_set_id = (
        SELECT exam_set_id FROM exam_attempts WHERE id = ?
    )
    GROUP BY q.id
    ORDER BY q.id
");

$stmt->execute([$attempt_id, $attempt_id]);
$questions = $stmt->fetchAll();

// Calculate statistics
$total_questions = count($questions);
$correct_answers = 0;
$incorrect_answers = 0;
$unanswered_questions = 0;

foreach ($questions as $question) {
    if (!$question['is_answered']) {
        $unanswered_questions++;
    } else if ($question['answer_is_correct']) {
        $correct_answers++;
    } else {
        $incorrect_answers++;
    }
}

$score = $total_questions > 0 ? round(($correct_answers / $total_questions) * 100, 1) : 0;

// Update the statistics in the database
$stmt = $pdo->prepare("
    UPDATE exam_attempts 
    SET 
        total_questions = ?,
        correct_answers = ?,
        incorrect_answers = ?,
        unanswered_questions = ?,
        score = ?
    WHERE id = ?
");

$stmt->execute([
    $total_questions,
    $correct_answers,
    $incorrect_answers,
    $unanswered_questions,
    $score,
    $attempt_id
]);

$pageTitle = "Exam Result: " . htmlspecialchars($attempt['exam_title']);
include '../includes/header.php';
?>

<style>
.question-block {
    background-color: #fff;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    margin-bottom: 2rem;
    border: 1px solid #dee2e6;
}

.question-text {
    font-size: 1.1rem;
    line-height: 1.6;
    padding: 1rem;
    background-color: #f8f9fa;
    border-radius: 4px;
}

/* Option styling improvements */
.options .option-container {
    margin-bottom: 0.75rem;
}

.option-row {
    padding: 0.75rem;
    border: 2px solid transparent;
    border-radius: 6px;
    margin-bottom: 0.5rem;
    transition: all 0.2s ease;
}

/* Selected correct answer */
.option-correct {
    background-color: #d4edda !important;
    border-color: #28a745 !important;
    color: #155724;
}

/* Selected incorrect answer */
.option-incorrect {
    background-color: #f8d7da !important;
    border-color: #dc3545 !important;
    color: #721c24;
}

/* Correct answer that wasn't selected */
.option-was-correct {
    background-color: rgba(40, 167, 69, 0.1) !important;
    border-color: rgba(40, 167, 69, 0.4) !important;
    color: #155724;
}

.option-letter {
    font-weight: bold;
    font-size: 1.1rem;
    margin-right: 1rem;
    min-width: 25px;
    display: inline-block;
}

.option-text {
    flex-grow: 1;
}

.option-indicator {
    margin-left: 1rem;
    font-size: 1.2rem;
}

.explanation {
    margin-top: 1.5rem;
    padding: 1rem;
    border-radius: 4px;
}

.explanation-correct {
    background-color: rgba(40, 167, 69, 0.1);
    border: 1px solid rgba(40, 167, 69, 0.2);
}

.explanation-incorrect {
    background-color: rgba(220, 53, 69, 0.1);
    border: 1px solid rgba(220, 53, 69, 0.2);
}

/* Status badges */
.status-badge {
    font-size: 0.9rem;
    padding: 0.5rem 1rem;
    border-radius: 50px;
}

.badge.float-end {
    margin-top: 3px;
}

.question-image {
    padding: 1rem;
    background-color: #fff;
    border-radius: 4px;
    margin: 1rem 0;
}

.question-image img {
    border-radius: 4px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    max-width: 100%;
    height: auto;
}

/* Mobile responsive improvements */
@media (max-width: 768px) {
    .question-block {
        padding: 0.75rem;
    }
    
    .option-row {
        padding: 0.5rem;
        flex-wrap: wrap;
    }
    
    .option-letter {
        margin-right: 0.5rem;
        min-width: 20px;
    }
    
    .option-text {
        width: calc(100% - 60px);
        font-size: 0.95rem;
        line-height: 1.4;
    }
    
    .option-indicator {
        margin-left: auto;
    }
    
    .explanation {
        padding: 0.75rem;
        margin-top: 1rem;
    }
    
    .question-text {
        font-size: 1rem;
        padding: 0.75rem;
    }
}

/* High contrast mode for better visibility */
@media (prefers-contrast: high) {
    .option-correct {
        background-color: #b7e1c1 !important;
        border-color: #2a9147 !important;
    }
    
    .option-incorrect {
        background-color: #f5c2c7 !important;
        border-color: #e35d6a !important;
    }
    
    .option-was-correct {
        background-color: #d1e7dd !important;
        border-color: #75b798 !important;
    }
}

/* Animation and hover effects */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}

.option-container {
    animation: fadeIn 0.3s ease-out;
}

.option-row {
    position: relative;
    overflow: hidden;
}

.option-row:hover {
    transform: translateX(5px);
}

.option-row::before {
    content: '';
    position: absolute;
    left: -100%;
    top: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(
        90deg,
        transparent,
        rgba(255,255,255,0.2),
        transparent
    );
    transition: left 0.5s ease;
}

.option-row:hover::before {
    left: 100%;
}

/* Improve indicator visibility */
.option-indicator i {
    filter: drop-shadow(0 1px 1px rgba(0,0,0,0.1));
    font-size: 1.4rem;
}

/* Active state indicators */
.option-correct.active {
    box-shadow: 0 0 0 2px #28a745;
}

.option-incorrect.active {
    box-shadow: 0 0 0 2px #dc3545;
}

.option-was-correct.active {
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.4);
}
</style>

<div class="container py-4">
    <!-- Result Overview -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <h4 class="card-title mb-0">Exam Result</h4>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h5><?php echo htmlspecialchars($attempt['exam_title']); ?></h5>
                    <div class="mb-2">
                        <strong>Started:</strong> <?php echo date('M j, Y H:i', strtotime($attempt['start_time'])); ?>
                    </div>
                    <div class="mb-2">
                        <strong>Completed:</strong> <?php echo date('M j, Y H:i', strtotime($attempt['end_time'])); ?>
                    </div>
                    <div class="mb-3">
                        <strong>Time Taken:</strong> 
                        <?php 
                        $time_taken = $attempt['duration_taken'] ?? $attempt['time_taken'] ?? 0;
                        echo $time_taken . ' minutes';
                        ?>
                    </div>
                </div>
                <div class="col-md-6 text-md-end">
                    <h3 class="mb-3">
                        Score: 
                        <span class="<?php echo $score >= $attempt['pass_percentage'] ? 'text-success' : 'text-danger'; ?>">
                            <?php echo number_format($score, 1); ?>%
                        </span>
                    </h3>
                    <div class="mb-2">
                        <strong>Status:</strong>
                        <?php if ($score >= $attempt['pass_percentage']): ?>
                            <span class="badge bg-success">PASSED</span>
                        <?php else: ?>
                            <span class="badge bg-danger">FAILED</span>
                        <?php endif; ?>
                    </div>
                    <div class="mb-2">
                        <strong>Required to Pass:</strong> <?php echo $attempt['pass_percentage']; ?>%
                    </div>
                    <div>
                        <span class="badge bg-success">Correct: <?php echo $correct_answers; ?></span>
                        <span class="badge bg-danger">Incorrect: <?php echo $incorrect_answers; ?></span>
                        <?php if ($unanswered_questions > 0): ?>
                            <span class="badge bg-warning">Unanswered: <?php echo $unanswered_questions; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions Review -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Questions Review</h5>
        </div>
        <div class="card-body">
            <?php if (empty($questions)): ?>
                <div class="alert alert-info">No questions found for this exam.</div>
            <?php else: ?>
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-block mb-4 p-3 border rounded">
                        <h5 class="mb-3">
                            Question <?php echo $index + 1; ?>
                            <?php if ($question['is_multiple_answer']): ?>
                                <span class="badge bg-info">Multiple Answers (Select <?php echo $question['max_correct_answers']; ?>)</span>
                            <?php endif; ?>
                            <?php if ($question['is_answered']): ?>
                                <span class="badge <?php echo $question['answer_is_correct'] ? 'bg-success' : 'bg-danger'; ?> float-end">
                                    <?php echo $question['answer_is_correct'] ? 'Correct' : 'Incorrect'; ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning float-end">Not Answered</span>
                            <?php endif; ?>
                        </h5>
                        
                        <!-- Question Text -->
                        <div class="question-text mb-3">
                            <?php echo $question['question_text']; ?>
                        </div>

                        <?php if ($question['question_image']): ?>
                            <div class="question-image mb-3 text-center">
                                <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                                     alt="Question Image" class="img-fluid" style="max-height: 200px;">
                            </div>
                        <?php endif; ?>

                        <!-- Options -->
                        <div class="options">
                            <?php
                            $selected_options = explode(',', $question['selected_options'] ?? '');
                            $correct_options = explode(',', $question['correct_answers']);
                            $options = array_filter([
                                'A' => $question['option_a'],
                                'B' => $question['option_b'],
                                'C' => $question['option_c'],
                                'D' => $question['option_d'],
                                'E' => $question['option_e'],
                                'F' => $question['option_f']
                            ]);

                            foreach ($options as $key => $value):
                                $isSelected = in_array($key, $selected_options);
                                $isCorrect = in_array($key, $correct_options);
                                
                                // Determine option styling
                                $optionClass = 'option-row d-flex align-items-center ';
                                if ($isSelected && $isCorrect) {
                                    $optionClass .= 'option-correct';
                                } elseif ($isSelected && !$isCorrect) {
                                    $optionClass .= 'option-incorrect';
                                } elseif (!$isSelected && $isCorrect) {
                                    $optionClass .= 'option-was-correct';
                                }
                            ?>
                                <div class="option-container">
                                    <div class="<?php echo $optionClass; ?>">
                                        <span class="option-letter"><?php echo $key; ?></span>
                                        <span class="option-text"><?php echo $value; ?></span>
                                        <span class="option-indicator">
                                            <?php if ($isSelected && $isCorrect): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php elseif ($isSelected && !$isCorrect): ?>
                                                <i class="fas fa-times-circle text-danger"></i>
                                            <?php elseif (!$isSelected && $isCorrect): ?>
                                                <i class="fas fa-check-circle text-success opacity-50"></i>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Explanation -->
                        <?php if ($question['is_answered']): ?>
                            <?php if ($question['answer_is_correct'] && $question['correct_explanation']): ?>
                                <div class="explanation explanation-correct">
                                    <h6 class="text-success mb-3">
                                        <i class="fas fa-check-circle"></i> Correct Answer Explanation:
                                    </h6>
                                    <div class="explanation-content">
                                        <?php echo $question['correct_explanation']; ?>
                                    </div>
                                </div>
                            <?php elseif (!$question['answer_is_correct'] && $question['incorrect_explanation']): ?>
                                <div class="explanation explanation-incorrect">
                                    <h6 class="text-danger mb-3">
                                        <i class="fas fa-times-circle"></i> Explanation for Incorrect Answer:
                                    </h6>
                                    <div class="explanation-content">
                                        <?php echo $question['incorrect_explanation']; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-warning mt-3">
                                <i class="fas fa-exclamation-triangle"></i> This question was not answered.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="mt-4">
        <a href="dashboard.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        <a href="start_exam.php?exam_id=<?php echo $attempt['exam_set_id']; ?>" class="btn btn-primary">
            <i class="fas fa-redo"></i> Retake Exam
        </a>
    </div>
</div>

<?php include '../includes/footer.php'; ?>