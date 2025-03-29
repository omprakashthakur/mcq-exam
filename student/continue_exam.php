<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require authentication
require_auth();

$attempt_id = $_GET['attempt_id'] ?? 0;

// Get attempt details with strict validation and calculate remaining time
$stmt = $pdo->prepare("
    SELECT 
        ea.*,
        e.title,
        e.duration_minutes,
        TIMESTAMPDIFF(SECOND, ea.start_time, NOW()) as elapsed_seconds
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    WHERE ea.id = ? AND ea.user_id = ? AND ea.status = 'in_progress'
");
$stmt->execute([$attempt_id, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    $_SESSION['error'] = 'Invalid exam attempt or exam already completed';
    header('Location: dashboard.php');
    exit();
}

// Calculate remaining time in seconds
$total_seconds = $attempt['duration_minutes'] * 60;
$elapsed_seconds = $attempt['elapsed_seconds'];
$remaining_seconds = max(0, $total_seconds - $elapsed_seconds);

// Check if exam time has expired
if ($remaining_seconds <= 0) {
    // Create a form to auto-submit
    echo '<!DOCTYPE html>
    <html>
    <head>
        <title>Submitting Exam...</title>
    </head>
    <body>
        <form id="submitForm" action="submit_exam.php" method="POST">
            <input type="hidden" name="attempt_id" value="'.$attempt_id.'">
        </form>
        <script>
            document.getElementById("submitForm").submit();
        </script>
    </body>
    </html>';
    exit();
}

// Get current question number from URL or default to 1
$current_num = max(1, intval($_GET['q'] ?? 1));

// Get total questions and validate current question number
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM questions 
    WHERE exam_set_id = ?
");
$stmt->execute([$attempt['exam_set_id']]);
$total_questions = $stmt->fetch()['total'];

if ($current_num > $total_questions) {
    header("Location: ?attempt_id=$attempt_id&q=1");
    exit();
}

// Get current question with all necessary details
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        GROUP_CONCAT(ca.correct_option) as correct_answers,
        COUNT(DISTINCT ca.correct_option) as required_answers,
        ua.selected_options,
        ua.is_correct
    FROM questions q
    LEFT JOIN correct_answers ca ON q.id = ca.question_id
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.exam_attempt_id = ?
    WHERE q.exam_set_id = ?
    GROUP BY q.id
    ORDER BY q.id
    LIMIT ?, 1
");
$stmt->execute([$attempt_id, $attempt['exam_set_id'], $current_num - 1]);
$current_question = $stmt->fetch();

// Get navigation info (answered/unanswered questions)
$stmt = $pdo->prepare("
    SELECT 
        q.id,
        CASE WHEN ua.id IS NOT NULL THEN 1 ELSE 0 END as is_answered
    FROM questions q
    LEFT JOIN user_answers ua ON q.id = ua.question_id AND ua.exam_attempt_id = ?
    WHERE q.exam_set_id = ?
    ORDER BY q.id
");
$stmt->execute([$attempt_id, $attempt['exam_set_id']]);
$questions_nav = $stmt->fetchAll();

$pageTitle = $attempt['title'] . " - Question " . $current_num;
include '../includes/header.php';
?>

<div class="container py-1">
    <!-- Timer Display -->
    <div class="d-flex align-items-start mb-1">
        <div class="exam-progress flex-grow-1">
            <h3 class="mb-1">Progress</h3>
            <div class="progress">
                <div class="progress-bar bg-primary" role="progressbar" 
                     style="width: <?php echo ($current_num / $total_questions) * 100; ?>%">
                    Question <?php echo $current_num; ?> of <?php echo $total_questions; ?>
                </div>
            </div>
        </div>
        <div class="exam-timer ms-3">
            <h5 class="mb-2 text-center">Time Remaining</h5>
            <div id="timer" class="badge bg-warning d-flex align-items-center justify-content-center" data-remaining="<?php echo $remaining_seconds; ?>" style="height: 50px; width: 250px;">
            <i class="fas fa-clock"></i>
            <span id="timeDisplay" style="color: #000;"></span>
            </div>
        </div>
    </div>

    <!-- Question Display -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Question <?php echo $current_num; ?></h5>
                <?php if ($current_question['max_correct_answers'] > 1): ?>
                    <span class="badge bg-info">
                        Select <?php echo $current_question['max_correct_answers']; ?> correct answers
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <!-- Question Text -->
            <div class="question-text mb-4">
                <?php echo $current_question['question_text']; ?>
            </div>

            <?php if ($current_question['question_image']): ?>
                <div class="question-image mb-4 text-center">
                    <img src="../uploads/questions/<?php echo htmlspecialchars($current_question['question_image']); ?>" 
                         alt="Question Image" class="img-fluid" style="max-height: 300px;">
                </div>
            <?php endif; ?>

            <!-- Answer Options -->
            <form id="answerForm" class="options-container">
                <?php
                $options = [];
                $max_options = 4; // Default for single answer questions
                
                // Determine number of options based on max_correct_answers
                if ($current_question['max_correct_answers'] == 2) {
                    $max_options = 5; // For two correct answers
                } elseif ($current_question['max_correct_answers'] == 3) {
                    $max_options = 6; // For three correct answers
                }
                
                // Only include options up to the max_options limit and if they have content
                $option_letters = ['A', 'B', 'C', 'D', 'E', 'F'];
                for ($i = 0; $i < $max_options; $i++) {
                    $letter = $option_letters[$i];
                    $option_value = $current_question['option_' . strtolower($letter)];
                    if ($option_value !== null && trim($option_value) !== '') {
                        $options[$letter] = $option_value;
                    }
                }
                
                $selected_options = explode(',', $current_question['selected_options'] ?? '');
                ?>
                <div id="answerWarning" class="alert alert-warning d-none mb-3">
                    Please select an answer before proceeding.
                </div>
                <?php 
                foreach ($options as $key => $value):
                    $is_selected = in_array($key, $selected_options);
                ?>
                    <div class="form-check option-container mb-3">
                        <input class="form-check-input answer-option" 
                               type="<?php echo $current_question['max_correct_answers'] > 1 ? 'checkbox' : 'radio'; ?>"
                               name="answer<?php echo $current_question['max_correct_answers'] > 1 ? '[]' : ''; ?>"
                               value="<?php echo $key; ?>"
                               id="option<?php echo $key; ?>"
                               <?php echo $is_selected ? 'checked' : ''; ?>>
                        <label class="form-check-label option-label w-100" for="option<?php echo $key; ?>">
                            <span class="option-letter"><?php echo $key; ?>)</span>
                            <span class="option-text"><?php echo $value; ?></span>
                        </label>
                    </div>
                <?php endforeach; ?>
                
                <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                <input type="hidden" name="question_id" value="<?php echo $current_question['id']; ?>">
            </form>
        </div>
    </div>

    <!-- Navigation Buttons -->
    <div class="d-flex justify-content-between mt-4">
        <?php if ($current_num > 1): ?>
            <a href="?attempt_id=<?php echo $attempt_id; ?>&q=<?php echo $current_num - 1; ?>" 
               class="btn btn-secondary" id="prevBtn">
                <i class="fas fa-arrow-left"></i> Previous
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>

        <div class="btn-group">
            <?php if ($current_num < $total_questions): ?>
                <button type="button" class="btn btn-primary" id="nextBtn">
                    Next <i class="fas fa-arrow-right"></i>
                </button>
            <?php endif; ?>
            
            <?php if ($current_num === $total_questions): ?>
                <button type="button" class="btn btn-success" id="submitExamBtn">
                    <i class="fas fa-check-circle"></i> Submit Exam
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Question Navigation -->
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0">Question Navigation</h6>
        </div>
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2">
                <?php foreach ($questions_nav as $index => $q): ?>
                    <a href="?attempt_id=<?php echo $attempt_id; ?>&q=<?php echo $index + 1; ?>" 
                       class="btn btn-sm <?php echo $q['is_answered'] ? 'btn-success' : 'btn-outline-secondary'; ?>">
                        <?php echo $index + 1; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Submit Confirmation Modal -->
<div class="modal fade" id="submitConfirmModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title">
                    <i class="fas fa-exclamation-triangle"></i> Submit Exam Confirmation
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <strong>Warning:</strong> Are you sure you want to submit your exam?
                    <br>You cannot change your answers after submission.
                </div>
                <div id="unansweredWarning" class="alert alert-danger d-none">
                    <i class="fas fa-exclamation-circle"></i>
                    You have unanswered questions. Are you sure you want to proceed?
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> No, Cancel
                </button>
                <form id="submitExamForm" action="submit_exam.php" method="POST" class="d-inline">
                    <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                    <button type="submit" class="btn btn-success px-4">
                        <i class="fas fa-check"></i> Yes, Submit Exam
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Single declarations of all elements
    const elements = {
        timer: document.getElementById('timer'),
        timeDisplay: document.getElementById('timeDisplay'),
        answerForm: document.getElementById('answerForm'),
        nextBtn: document.getElementById('nextBtn'),
        submitBtn: document.getElementById('submitExamBtn'),
        answerWarning: document.getElementById('answerWarning'),
        submitForm: document.getElementById('submitExamForm'),
        modal: new bootstrap.Modal(document.getElementById('submitConfirmModal'))
    };

    // Timer initialization with correct data attribute
    let remainingSeconds = parseInt(elements.timer.dataset.remaining);
    
    function updateTimer() {
        if (remainingSeconds <= 0) {
            // Create and submit form when time expires
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'submit_exam.php';
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'attempt_id';
            input.value = '<?php echo $attempt_id; ?>';
            form.appendChild(input);
            document.body.appendChild(form);
            form.submit();
            return;
        }

        const hours = Math.floor(remainingSeconds / 3600);
        const minutes = Math.floor((remainingSeconds % 3600) / 60);
        const seconds = remainingSeconds % 60;

        elements.timeDisplay.textContent = 
            `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;

        // Warning states for timer
        if (remainingSeconds <= 300) { // Last 5 minutes
            elements.timer.classList.remove('bg-primary', 'bg-warning');
            elements.timer.classList.add('bg-danger', 'timer-blink');
        } else if (remainingSeconds <= 600) { // Last 10 minutes
            elements.timer.classList.remove('bg-primary', 'bg-danger', 'timer-blink');
            elements.timer.classList.add('bg-warning');
            elements.timeDisplay.style.color = '#000';
        }

        remainingSeconds--;
    }

    // Start timer
    if (elements.timer && elements.timeDisplay) {
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Clean up timer on page unload
        window.addEventListener('beforeunload', () => {
            clearInterval(timerInterval);
        });
    }

    // Rest of the existing code...
    const maxAnswers = <?php echo $current_question['max_correct_answers']; ?>;
    let saveTimeout;
    let isSaving = false;

    // Answer saving function
    async function saveAnswer(formData) {
        if (isSaving) return;
        try {
            isSaving = true;
            const response = await fetch('save_answer.php', {
                method: 'POST',
                body: formData
            });
            const data = await response.json();
            if (data.status === 'success') {
                updateQuestionStatus();
                elements.answerWarning.classList.add('d-none');
            }
        } catch (error) {
            console.error('Error:', error);
        } finally {
            isSaving = false;
        }
    }

    // Update question navigation status
    function updateQuestionStatus() {
        const questionBtn = document.querySelector(`a[href$="q=${<?php echo $current_num; ?>}"]`);
        if (questionBtn) {
            questionBtn.classList.remove('btn-outline-secondary');
            questionBtn.classList.add('btn-success');
        }
    }

    // Answer validation
    async function validateCurrentAnswer() {
        const selectedOptions = document.querySelectorAll('.answer-option:checked');
        if (selectedOptions.length === 0) {
            elements.answerWarning.textContent = 'Question not completed. Please select an answer.';
            elements.answerWarning.classList.remove('d-none');
            elements.answerWarning.scrollIntoView({ behavior: 'smooth' });
            return false;
        }
        return true;
    }

    // Event listeners
    if (elements.answerForm) {
        elements.answerForm.addEventListener('change', async function(e) {
            if (!e.target.classList.contains('answer-option')) return;
            
            clearTimeout(saveTimeout);
            
            if (maxAnswers > 1) {
                const checked = document.querySelectorAll('.answer-option:checked');
                if (checked.length > maxAnswers) {
                    e.target.checked = false;
                    alert(`You can only select up to ${maxAnswers} answers`);
                    return;
                }
            }

            const formData = new FormData(elements.answerForm);
            const selectedOptions = Array.from(document.querySelectorAll('.answer-option:checked')).map(opt => opt.value);
            formData.append('selected_options', JSON.stringify(selectedOptions));
            
            saveTimeout = setTimeout(() => saveAnswer(formData), 300);
        });
    }

    if (elements.nextBtn) {
        elements.nextBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            if (await validateCurrentAnswer()) {
                window.location.href = `?attempt_id=${<?php echo $attempt_id; ?>}&q=${<?php echo $current_num + 1; ?>}`;
            }
        });
    }

    if (elements.submitBtn) {
        elements.submitBtn.addEventListener('click', async function(e) {
            e.preventDefault();
            if (await validateCurrentAnswer()) {
                const unansweredCount = document.querySelectorAll('.btn-outline-secondary').length;
                if (unansweredCount > 0) {
                    elements.unansweredWarning.innerHTML = `
                        <i class="fas fa-exclamation-circle"></i>
                        Warning: You have ${unansweredCount} unanswered question${unansweredCount > 1 ? 's' : ''}. 
                        Are you sure you want to proceed?
                    `;
                    elements.unansweredWarning.classList.remove('d-none');
                }
                elements.modal.show();
            }
        });
    }

    if (elements.submitForm) {
        elements.submitForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (await validateCurrentAnswer()) {
                this.classList.add('submitted');
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
                }
                this.submit();
            }
        });
    }

    // Modal handling
    const modalElement = document.getElementById('submitConfirmModal');
    if (modalElement) {
        modalElement.addEventListener('hidden.bs.modal', function() {
            document.querySelectorAll('button[type="submit"], #submitExamBtn').forEach(btn => {
                btn.disabled = false;
                if (btn.classList.contains('btn-success')) {
                    btn.innerHTML = '<i class="fas fa-check-circle"></i> Submit Exam';
                }
            });
            elements.unansweredWarning.classList.add('d-none');
        });

        document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                elements.modal.hide();
            });
        });
    }
});
</script>

<style>
@keyframes blink {
    0% { opacity: 1; }
    50% { opacity: 0.5; }
    100% { opacity: 1; }
}

.answer-option:checked + .form-check-label {
    background-color: #e9ecef;
    border-radius: 0.25rem;
}

.form-check {
    transition: all 0.2s ease-in-out;
}

.form-check:hover {
    background-color: #f8f9fa;
    border-radius: 0.25rem;
}

.exam-timer {
    min-width: 250px;
}

.exam-timer .badge {
    font-size: 1.5rem !important;
    padding: 1.1rem !important;
    min-width: 250px;
    display: inline-block;
    box-shadow: 0 1px 2px rgba(0,0,0,0.1);
}

.exam-timer .badge i {
    font-size: 1.5rem;
    margin-right: 1rem;
    vertical-align: middle;
}

.exam-timer .badge #timeDisplay {
    font-family: monospace;
    font-weight: 400;
}

.timer-blink {
    animation: timerBlink 1s infinite;
}

@keyframes timerBlink {
    0% { opacity: 1; }
    50% { opacity: 0.6; }
    100% { opacity: 1; }
}

.progress {
    height: 2.2rem !important;
    border-radius: 1rem;
    background-color: #f8f9fa;
    box-shadow: inset 0 1px 3px rgba(0,0,0,0.1);
}

.progress-bar {
    font-size: 1.0rem;
    font-weight: 400;
    transition: width 0.3s ease;
}
</style>

<?php include '../includes/footer.php'; ?>