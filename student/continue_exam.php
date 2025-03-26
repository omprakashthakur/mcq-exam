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
    // Auto-submit the exam
    header('Location: submit_exam.php?attempt_id=' . $attempt_id);
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

<div class="container py-4">
    <!-- Timer Display -->
    <div class="d-flex align-items-center mb-3">
        <div class="progress flex-grow-1 me-3" style="height: 20px;">
            <div class="progress-bar" role="progressbar" 
                 style="width: <?php echo ($current_num / $total_questions) * 100; ?>%">
                Question <?php echo $current_num; ?> of <?php echo $total_questions; ?>
            </div>
        </div>
        <div id="timer" class="badge bg-primary p-2" 
             data-remaining="<?php echo $remaining_seconds; ?>">
            Time Remaining: <span id="timeDisplay">Calculating...</span>
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
                $options = [
                    'A' => $current_question['option_a'],
                    'B' => $current_question['option_b'],
                    'C' => $current_question['option_c'],
                    'D' => $current_question['option_d'],
                    'E' => $current_question['option_e'],
                    'F' => $current_question['option_f']
                ];
                
                $selected_options = explode(',', $current_question['selected_options'] ?? '');
                
                foreach ($options as $key => $value):
                    if ($value === null) continue;
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
               class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Previous
            </a>
        <?php else: ?>
            <div></div>
        <?php endif; ?>

        <div class="btn-group">
            <?php if ($current_num < $total_questions): ?>
                <a href="?attempt_id=<?php echo $attempt_id; ?>&q=<?php echo $current_num + 1; ?>" 
                   class="btn btn-primary" id="nextBtn">
                    Next <i class="fas fa-arrow-right"></i>
                </a>
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
<div class="modal fade" id="submitConfirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to submit your exam?</p>
                <div id="unansweredWarning" class="alert alert-warning d-none">
                    You have unanswered questions. Are you sure you want to proceed?
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="submit_exam.php" method="POST">
                    <input type="hidden" name="attempt_id" value="<?php echo $attempt_id; ?>">
                    <button type="submit" class="btn btn-success">Yes, Submit Exam</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const answerForm = document.getElementById('answerForm');
    const maxAnswers = <?php echo $current_question['max_correct_answers']; ?>;
    const submitBtn = document.getElementById('submitExamBtn');
    const submitModal = document.getElementById('submitConfirmModal');
    const warningElement = document.getElementById('unansweredWarning');
    let saveTimeout;
    let isSaving = false;

    // Debounce function to prevent multiple rapid saves
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Save answer function
    async function saveAnswer(formData) {
        if (isSaving) return;
        
        try {
            isSaving = true;
            const response = await fetch('save_answer.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'Cache-Control': 'no-cache'
                }
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            
            if (data.status === 'success') {
                const questionBtn = document.querySelector(`a[href$="q=${<?php echo $current_num; ?>}"]`);
                if (questionBtn) {
                    questionBtn.classList.remove('btn-outline-secondary');
                    questionBtn.classList.add('btn-success');
                }
                updateSubmitButton();
            } else {
                throw new Error(data.message || 'Failed to save answer');
            }
        } catch (error) {
            console.error('Error saving answer:', error);
            alert(error.message || 'Error saving your answer. Please try again.');
            // Revert selection if save failed
            if (maxAnswers === 1) {
                document.querySelectorAll('.answer-option:checked').forEach(opt => opt.checked = false);
            }
        } finally {
            isSaving = false;
        }
    }

    // Update submit button state
    function updateSubmitButton() {
        const unanswered = document.querySelectorAll('.btn-outline-secondary').length;
        if (submitBtn) {
            submitBtn.disabled = isSaving;
            submitBtn.title = isSaving ? 'Please wait while saving...' : '';
        }
        return unanswered;
    }

    // Handle answer selection with debounce
    const debouncedSave = debounce((formData) => {
        saveAnswer(formData);
    }, 500);

    answerForm.addEventListener('change', function(e) {
        if (!e.target.classList.contains('answer-option')) return;

        // For multiple answers, check if max selections exceeded
        if (maxAnswers > 1) {
            const checked = document.querySelectorAll('.answer-option:checked');
            if (checked.length > maxAnswers) {
                e.target.checked = false;
                alert(`You can only select up to ${maxAnswers} answers`);
                return;
            }
        } else {
            // For single answer, uncheck others
            document.querySelectorAll('.answer-option:checked').forEach(input => {
                if (input !== e.target) {
                    input.checked = false;
                }
            });
        }

        // Prepare and save form data
        const formData = new FormData();
        formData.append('attempt_id', answerForm.querySelector('[name="attempt_id"]').value);
        formData.append('question_id', answerForm.querySelector('[name="question_id"]').value);
        const selectedOptions = Array.from(document.querySelectorAll('.answer-option:checked')).map(opt => opt.value);
        formData.append('selected_options', JSON.stringify(selectedOptions));

        debouncedSave(formData);
    });

    // Timer functionality optimization
    const timerElement = document.getElementById('timer');
    const timeDisplay = document.getElementById('timeDisplay');
    let remainingSeconds = parseInt(timerElement.dataset.remaining);
    let timerInterval;
    
    function updateTimer() {
        if (remainingSeconds <= 0) {
            clearInterval(timerInterval);
            submitExam();
            return;
        }

        const minutes = Math.floor(remainingSeconds / 60);
        const seconds = remainingSeconds % 60;
        timeDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
        
        if (remainingSeconds <= 300 && !timerElement.classList.contains('bg-danger')) {
            timerElement.classList.remove('bg-primary');
            timerElement.classList.add('bg-danger');
            timerElement.style.animation = 'blink 1s infinite';
        }

        remainingSeconds--;
    }

    // Start timer
    updateTimer();
    timerInterval = setInterval(updateTimer, 1000);

    // Clean up timer on page unload
    window.addEventListener('beforeunload', function() {
        if (timerInterval) {
            clearInterval(timerInterval);
        }
    });

    // Optimized submit exam functionality
    function submitExam() {
        if (isSaving) {
            alert('Please wait while saving your last answer...');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'submit_exam.php';
        
        const attemptInput = document.createElement('input');
        attemptInput.type = 'hidden';
        attemptInput.name = 'attempt_id';
        attemptInput.value = answerForm.querySelector('[name="attempt_id"]').value;
        
        form.appendChild(attemptInput);
        document.body.appendChild(form);
        
        // Disable submit button to prevent double submission
        const submitButtons = document.querySelectorAll('button[type="submit"]');
        submitButtons.forEach(btn => {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Submitting...';
        });

        form.submit();
    }

    // Handle submit modal cleanup
    let bsModal;
    if (submitModal) {
        bsModal = new bootstrap.Modal(submitModal);
        submitModal.addEventListener('hidden.bs.modal', function () {
            const submitButtons = document.querySelectorAll('button[type="submit"]');
            submitButtons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = 'Yes, Submit Exam';
            });
        });
    }

    // Submit exam confirmation with retry logic
    if (submitBtn) {
        submitBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (isSaving) {
                const confirmRetry = confirm('There is an answer being saved. Wait for it to complete or retry submission?');
                if (confirmRetry) {
                    isSaving = false;
                }
                return;
            }
            
            const unanswered = updateSubmitButton();
            if (unanswered > 0) {
                warningElement.textContent = `You have ${unanswered} unanswered question(s). Are you sure you want to proceed?`;
                warningElement.classList.remove('d-none');
            } else {
                warningElement.classList.add('d-none');
            }
            
            if (bsModal) {
                bsModal.show();
            }
        });
    }

    // Handle final submit button in modal with retry mechanism
    const finalSubmitForm = document.querySelector('#submitConfirmModal form');
    if (finalSubmitForm) {
        finalSubmitForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isSaving) {
                const confirmRetry = confirm('Still saving your last answer. Proceed anyway?');
                if (!confirmRetry) {
                    return;
                }
            }
            submitExam();
        });
    }

    // Add keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        // Prevent shortcuts while typing in form fields
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;

        if (e.key === 'ArrowRight' && !e.ctrlKey && !e.altKey) {
            // Next question
            const nextBtn = document.querySelector('#nextBtn');
            if (nextBtn) nextBtn.click();
        } else if (e.key === 'ArrowLeft' && !e.ctrlKey && !e.altKey) {
            // Previous question
            const prevBtn = document.querySelector('.btn-secondary');
            if (prevBtn && prevBtn.tagName === 'A') prevBtn.click();
        }
    });
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
</style>

<?php include '../includes/footer.php'; ?>