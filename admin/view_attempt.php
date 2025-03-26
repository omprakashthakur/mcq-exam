<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$attempt_id = $_GET['id'] ?? 0;

// Get attempt details with exam info
$stmt = $pdo->prepare("
    SELECT 
        ea.*,
        e.title as exam_title,
        e.pass_percentage,
        u.username,
        COALESCE(sp.full_name, u.username) as student_name,
        u.email
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    JOIN users u ON ea.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE ea.id = ?
");
$stmt->execute([$attempt_id]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = 'Attempt not found';
    header('Location: view_results.php');
    exit();
}

// Get answers with question details
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        ua.selected_options,
        ua.is_correct,
        GROUP_CONCAT(ca.correct_option ORDER BY ca.correct_option) as correct_answers
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.exam_attempt_id = ?
    LEFT JOIN correct_answers ca ON q.id = ca.question_id
    WHERE q.exam_set_id = ?
    GROUP BY q.id
    ORDER BY q.id
");
$stmt->execute([$attempt_id, $attempt['exam_set_id']]);
$questions = $stmt->fetchAll();

$pageTitle = "View Attempt Details";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <!-- Attempt Overview Card -->
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title mb-0">Attempt Overview</h3>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h4><?php echo htmlspecialchars($attempt['exam_title']); ?></h4>
                    <p class="mb-1">
                        <strong>Student:</strong> <?php echo htmlspecialchars($attempt['student_name']); ?>
                        (<?php echo htmlspecialchars($attempt['email']); ?>)
                    </p>
                    <p class="mb-1">
                        <strong>Started:</strong> <?php echo date('M j, Y H:i', strtotime($attempt['start_time'])); ?>
                    </p>
                    <?php if ($attempt['end_time']): ?>
                        <p class="mb-1">
                            <strong>Completed:</strong> <?php echo date('M j, Y H:i', strtotime($attempt['end_time'])); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="col-md-6 text-md-end">
                    <h4 class="mb-3">
                        Score: 
                        <span class="<?php echo $attempt['score'] >= $attempt['pass_percentage'] ? 'text-success' : 'text-danger'; ?>">
                            <?php echo $attempt['score']; ?>%
                        </span>
                    </h4>
                    <p class="mb-1">
                        <strong>Status:</strong>
                        <span class="badge bg-<?php echo $attempt['status'] === 'completed' ? 'success' : 'warning'; ?>">
                            <?php echo ucfirst($attempt['status']); ?>
                        </span>
                    </p>
                    <p class="mb-1">
                        <strong>Pass Percentage:</strong> <?php echo $attempt['pass_percentage']; ?>%
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Questions Review -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title mb-0">Questions Review</h3>
        </div>
        <div class="card-body">
            <?php foreach ($questions as $index => $question): ?>
                <div class="question-block mb-4 pb-3 border-bottom">
                    <h5 class="mb-3">
                        Question <?php echo $index + 1; ?>
                        <?php if ($question['is_multiple_answer']): ?>
                            <span class="badge bg-info">Multiple Answers</span>
                        <?php endif; ?>
                    </h5>
                    
                    <!-- Question Text -->
                    <div class="question-text mb-3">
                        <?php echo $question['question_text']; ?>
                    </div>

                    <?php if ($question['question_image']): ?>
                        <div class="question-image mb-3">
                            <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                                 alt="Question Image" class="img-fluid" style="max-height: 200px;">
                        </div>
                    <?php endif; ?>

                    <!-- Options -->
                    <div class="options">
                        <?php
                        $selected_options = explode(',', $question['selected_options'] ?? '');
                        $correct_options = explode(',', $question['correct_answers']);
                        $options = [
                            'A' => $question['option_a'],
                            'B' => $question['option_b'],
                            'C' => $question['option_c'],
                            'D' => $question['option_d']
                        ];

                        foreach ($options as $key => $value):
                            $isSelected = in_array($key, $selected_options);
                            $isCorrect = in_array($key, $correct_options);
                            
                            // Determine option styling
                            $optionClass = '';
                            if ($isSelected && $isCorrect) {
                                $optionClass = 'border-success bg-success bg-opacity-10';
                            } elseif ($isSelected && !$isCorrect) {
                                $optionClass = 'border-danger bg-danger bg-opacity-10';
                            } elseif (!$isSelected && $isCorrect) {
                                $optionClass = 'border-success bg-success bg-opacity-10';
                            }
                        ?>
                            <div class="option mb-2">
                                <div class="border rounded p-2 <?php echo $optionClass; ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="me-2">
                                            <strong><?php echo $key; ?>)</strong>
                                        </div>
                                        <div class="flex-grow-1">
                                            <?php echo $value; ?>
                                        </div>
                                        <div class="ms-2">
                                            <?php if ($isSelected): ?>
                                                <i class="fas fa-check-circle <?php echo $isCorrect ? 'text-success' : 'text-danger'; ?>"></i>
                                            <?php elseif ($isCorrect): ?>
                                                <i class="fas fa-check-circle text-success"></i>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- After options display, update explanation section -->
                    <?php if ($question['is_correct']): ?>
                        <?php if ($question['correct_explanation']): ?>
                            <div class="explanation mt-3">
                                <h6 class="text-success">
                                    <i class="fas fa-check-circle"></i> Explanation:
                                </h6>
                                <div class="border rounded p-2 bg-success bg-opacity-10">
                                    <?php echo $question['correct_explanation']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if ($question['incorrect_explanation']): ?>
                            <div class="explanation mt-3">
                                <h6 class="text-danger">
                                    <i class="fas fa-times-circle"></i> Explanation:
                                </h6>
                                <div class="border rounded p-2 bg-danger bg-opacity-10">
                                    <?php echo $question['incorrect_explanation']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="mt-4">
        <a href="view_results.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Results
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>