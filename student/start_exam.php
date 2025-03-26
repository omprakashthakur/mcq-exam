<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

$access_code = $_GET['code'] ?? '';
$exam_id = $_GET['exam_id'] ?? 0;

try {
    $pdo->beginTransaction();
    
    if ($exam_id) {
        // Direct retake - verify exam eligibility and create new access
        $stmt = $pdo->prepare("
            SELECT 
                e.*,
                ea.status,
                ea.score,
                e.pass_percentage
            FROM exam_sets e
            JOIN exam_attempts ea ON e.id = ea.exam_set_id 
            WHERE e.id = ? 
            AND ea.user_id = ?
            AND ea.status = 'completed'
            ORDER BY ea.end_time DESC
            LIMIT 1
        ");
        $stmt->execute([$exam_id, $_SESSION['user_id']]);
        $exam = $stmt->fetch();

        if (!$exam) {
            throw new Exception('Invalid exam or no previous attempts found');
        }

        // Generate unique access code
        $access_code = substr(md5(uniqid(rand(), true)), 0, 8);
        
        // Create new exam access entry
        $stmt = $pdo->prepare("
            INSERT INTO exam_access (
                user_id,
                exam_set_id,
                access_code,
                expiry_date,
                created_by,
                is_retake
            ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 DAY), ?, 1)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $exam_id,
            $access_code,
            $_SESSION['user_id']
        ]);
    }

    // Get exam access details
    $stmt = $pdo->prepare("
        SELECT ea.*, e.title, e.duration_minutes 
        FROM exam_access ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        WHERE ea.access_code = ? 
        AND ea.user_id = ? 
        AND ea.expiry_date > NOW()
        AND ea.is_used = 0
    ");
    $stmt->execute([$access_code, $_SESSION['user_id']]);
    $exam_access = $stmt->fetch();

    if (!$exam_access) {
        throw new Exception('Invalid or expired exam access.');
    }

    // Clean up abandoned attempts
    $stmt = $pdo->prepare("
        UPDATE exam_attempts ea
        JOIN exam_sets e ON ea.exam_set_id = e.id
        SET 
            ea.status = 'completed',
            ea.end_time = NOW(),
            ea.score = 0,
            ea.incorrect_answers = ea.total_questions,
            ea.unanswered_questions = ea.total_questions
        WHERE ea.user_id = ? 
        AND ea.exam_set_id = ? 
        AND ea.status = 'in_progress'
        AND TIMESTAMPDIFF(MINUTE, ea.start_time, NOW()) > e.duration_minutes + 5
    ");
    $stmt->execute([$_SESSION['user_id'], $exam_access['exam_set_id']]);

    // Get and randomize questions
    $stmt = $pdo->prepare("
        SELECT q.* FROM questions q
        WHERE q.exam_set_id = ?
        ORDER BY RAND()
    ");
    $stmt->execute([$exam_access['exam_set_id']]);
    $questions = $stmt->fetchAll();

    // Create new attempt
    $stmt = $pdo->prepare("
        INSERT INTO exam_attempts (
            user_id,
            exam_set_id,
            start_time,
            status,
            total_questions
        ) VALUES (?, ?, NOW(), 'in_progress', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $exam_access['exam_set_id'],
        count($questions)
    ]);
    
    $attempt_id = $pdo->lastInsertId();

    // Mark access as used
    $stmt = $pdo->prepare("UPDATE exam_access SET is_used = 1 WHERE access_code = ?");
    $stmt->execute([$access_code]);

    $pdo->commit();

    // Redirect to continue exam
    header("Location: continue_exam.php?attempt_id=" . $attempt_id);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($exam['title']); ?> - MCQ Exam</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .timer {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: rgba(255,255,255,0.9);
            padding: 10px 20px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .question-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 20px;
        }
        .question-nav button {
            width: 40px;
            height: 40px;
        }
        .option-label {
            display: block;
            padding: 10px;
            margin: 5px 0;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .option-label:hover {
            background-color: #f8f9fa;
        }
        input[type="radio"]:checked + .option-label {
            background-color: #e7f3ff;
            border-color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light">
    <div class="timer" id="timer">
        Time Remaining: <span id="time"></span>
    </div>

    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                    </div>
                    <div class="card-body">
                        <div class="question-nav">
                            <?php for ($i = 0; $i < count($questions); $i++): ?>
                                <button type="button" class="btn btn-outline-primary question-btn" data-question="<?php echo $i; ?>">
                                    <?php echo $i + 1; ?>
                                </button>
                            <?php endfor; ?>
                        </div>

                        <form id="examForm" method="POST" action="submit_exam.php">
                            <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                            <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                            
                            <?php foreach ($questions as $index => $question): ?>
                                <div class="question-container" id="question-<?php echo $index; ?>" style="display: <?php echo $index === 0 ? 'block' : 'none'; ?>">
                                    <h5 class="mb-3">Question <?php echo $index + 1; ?> of <?php echo count($questions); ?></h5>
                                    <p class="question-text"><?php echo htmlspecialchars($question['question_text']); ?></p>
                                    
                                    <div class="options">
                                        <?php
                                        $options = [
                                            'A' => $question['option_a'],
                                            'B' => $question['option_b'],
                                            'C' => $question['option_c'],
                                            'D' => $question['option_d']
                                        ];
                                        foreach ($options as $key => $value):
                                        ?>
                                            <div class="option">
                                                <input type="radio" 
                                                       id="q<?php echo $question['id']; ?>_<?php echo $key; ?>" 
                                                       name="answers[<?php echo $question['id']; ?>]" 
                                                       value="<?php echo $key; ?>"
                                                       class="d-none">
                                                <label class="option-label" for="q<?php echo $question['id']; ?>_<?php echo $key; ?>">
                                                    <?php echo $key; ?>) <?php echo htmlspecialchars($value); ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>

                                    <div class="mt-4 d-flex justify-content-between">
                                        <?php if ($index > 0): ?>
                                            <button type="button" class="btn btn-secondary prev-btn" data-question="<?php echo $index - 1; ?>">Previous</button>
                                        <?php else: ?>
                                            <div></div>
                                        <?php endif; ?>
                                        
                                        <?php if ($index < count($questions) - 1): ?>
                                            <button type="button" class="btn btn-primary next-btn" data-question="<?php echo $index + 1; ?>">Next</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-success">Submit Exam</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navigation functionality only
            const showQuestion = (questionIndex) => {
                document.querySelectorAll('.question-container').forEach(container => {
                    container.style.display = 'none';
                });
                document.getElementById(`question-${questionIndex}`).style.display = 'block';
                
                // Update question navigation buttons
                document.querySelectorAll('.question-btn').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
                document.querySelector(`.question-btn[data-question="${questionIndex}"]`).classList.remove('btn-outline-primary');
                document.querySelector(`.question-btn[data-question="${questionIndex}"]`).classList.add('btn-primary');
            };

            // Event listeners for navigation
            document.querySelectorAll('.question-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    showQuestion(parseInt(e.target.dataset.question));
                });
            });

            document.querySelectorAll('.next-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    showQuestion(parseInt(e.target.dataset.question));
                });
            });

            document.querySelectorAll('.prev-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    showQuestion(parseInt(e.target.dataset.question));
                });
            });

            // Mark answered questions
            document.querySelectorAll('input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', (e) => {
                    const questionId = e.target.name.match(/\d+/)[0];
                    document.querySelectorAll('.question-btn').forEach(btn => {
                        if (parseInt(btn.dataset.question) === parseInt(questionId)) {
                            btn.classList.add('btn-success');
                            btn.classList.remove('btn-outline-primary', 'btn-primary');
                        }
                    });
                });
            });

            // Confirm before leaving page
            window.addEventListener('beforeunload', (e) => {
                e.preventDefault();
                e.returnValue = '';
            });
        });
    </script>
</body>
</html>