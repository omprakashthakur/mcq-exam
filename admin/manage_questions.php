<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$exam_id = $_GET['exam_id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $question_text = $_POST['question_text'] ?? '';
        $option_a = $_POST['option_a'] ?? '';
        $option_b = $_POST['option_b'] ?? '';
        $option_c = $_POST['option_c'] ?? '';
        $option_d = $_POST['option_d'] ?? '';
        $option_e = $_POST['option_e'] ?? '';
        $option_f = $_POST['option_f'] ?? '';
        $correct_options = $_POST['correct_options'] ?? [];
        $correct_explanation = $_POST['correct_explanation'] ?? '';
        $incorrect_explanation = $_POST['incorrect_explanation'] ?? '';

        if (empty($question_text) || empty($option_a) || empty($option_b) || 
            empty($option_c) || empty($option_d) || empty($correct_options)) {
            throw new Exception('All fields are required except explanations.');
        }

        $pdo->beginTransaction();

        // Handle image upload
        $question_image = null;
        if (isset($_FILES['question_image']) && $_FILES['question_image']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $_FILES['question_image']['name'];
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            
            if (!in_array($ext, $allowed)) {
                throw new Exception('Invalid file type. Only JPG, PNG, and GIF are allowed.');
            }

            // Generate unique filename
            $new_filename = uniqid('question_') . '.' . $ext;
            $upload_path = '../uploads/questions/' . $new_filename;

            // Create directory if it doesn't exist
            if (!file_exists('../uploads/questions/')) {
                mkdir('../uploads/questions/', 0777, true);
            }

            if (move_uploaded_file($_FILES['question_image']['tmp_name'], $upload_path)) {
                $question_image = $new_filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }

        // Insert question
        $stmt = $pdo->prepare("
            INSERT INTO questions (
                exam_set_id, 
                question_text,
                question_image,
                option_a,
                option_b,
                option_c,
                option_d,
                option_e,
                option_f,
                correct_explanation,
                incorrect_explanation,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $exam_id,
            $question_text,
            $question_image,
            $option_a,
            $option_b,
            $option_c,
            $option_d,
            $option_e,
            $option_f,
            $correct_explanation,
            $incorrect_explanation
        ]);

        $question_id = $pdo->lastInsertId();

        // Insert correct answers
        $stmt = $pdo->prepare("INSERT INTO correct_answers (question_id, correct_option) VALUES (?, ?)");
        foreach ($correct_options as $correct_option) {
            $stmt->execute([$question_id, $correct_option]);
        }

        $pdo->commit();
        $_SESSION['success'] = 'Question added successfully.';
        header("Location: manage_questions.php?exam_id=" . $exam_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get exam details
$stmt = $pdo->prepare("SELECT title FROM exam_sets WHERE id = ?");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    $_SESSION['error'] = 'Exam not found';
    header('Location: manage_exams.php');
    exit();
}

// Get existing questions with all correct answers
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        GROUP_CONCAT(cq.correct_option) as correct_answers,
        COUNT(cq.correct_option) as correct_count
    FROM questions q
    LEFT JOIN correct_answers cq ON q.id = cq.question_id
    WHERE q.exam_set_id = ?
    GROUP BY q.id
    ORDER BY q.id DESC
");
$stmt->execute([$exam_id]);
$questions = $stmt->fetchAll();

$pageTitle = "Manage Questions: " . htmlspecialchars($exam['title']);
include 'includes/header.php';
?>

<!-- Include CKEditor -->
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<style>
.ck-editor__editable {
    min-height: 150px;
}
.option-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 15px;
    padding: 10px;
    border: 1px solid #dee2e6;
    border-radius: 5px;
    background-color: #fff;
}

.option-row:hover {
    background-color: #f8f9fa;
}

.option-letter {
    font-weight: bold;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    background-color: #e9ecef;
    border-radius: 50%;
}

.correct-option {
    margin-left: 10px;
}

.max-answers-warning {
    display: none;
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 5px;
}
</style>

<div class="container-fluid my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Questions: <?php echo htmlspecialchars($exam['title']); ?></h2>
        <div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addQuestionModal">
                <i class="fas fa-plus"></i> Add Question
            </button>
            <a href="view_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to Exam
            </a>
        </div>
    </div>

    <!-- Questions List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Questions</h3>
        </div>
        <div class="card-body">
            <?php if (empty($questions)): ?>
                <div class="alert alert-info">
                    No questions have been added to this exam yet.
                </div>
            <?php else: ?>
                <div class="accordion" id="questionsAccordion">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed" type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapse<?php echo $index; ?>">
                                    Question <?php echo $index + 1; ?>
                                    <?php if ($question['correct_count'] > 1): ?>
                                        <span class="badge bg-info ms-2">Multiple Answers (Select <?php echo $question['correct_count']; ?>)</span>
                                    <?php endif; ?>
                                </button>
                            </h2>
                            <div id="collapse<?php echo $index; ?>" 
                                 class="accordion-collapse collapse" 
                                 data-bs-parent="#questionsAccordion">
                                <div class="accordion-body">
                                    <div class="row">
                                        <div class="col-md-12">
                                            <div class="question-text mb-3">
                                                <strong>Question:</strong>
                                                <div class="border rounded p-3 bg-light">
                                                    <?php echo $question['question_text']; ?>
                                                </div>
                                            </div>

                                            <?php if ($question['question_image']): ?>
                                                <div class="question-image mb-3">
                                                    <strong>Question Image:</strong><br>
                                                    <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                                                         alt="Question Image" class="img-fluid mt-2" style="max-height: 200px;">
                                                </div>
                                            <?php endif; ?>

                                            <div class="options mb-3">
                                                <strong>Options:</strong>
                                                <div class="row mt-2">
                                                    <?php
                                                    $correct_answers = explode(',', $question['correct_answers']);
                                                    $options = [
                                                        'A' => $question['option_a'],
                                                        'B' => $question['option_b'],
                                                        'C' => $question['option_c'],
                                                        'D' => $question['option_d'],
                                                        'E' => $question['option_e'],
                                                        'F' => $question['option_f']
                                                    ];
                                                    foreach ($options as $key => $value):
                                                        if ($value === null) continue; // Skip empty options
                                                        $isCorrect = in_array($key, $correct_answers);
                                                    ?>
                                                        <div class="col-md-6 mb-2">
                                                            <div class="border rounded p-2 <?php echo $isCorrect ? 'border-success bg-success bg-opacity-10' : ''; ?>">
                                                                <strong><?php echo $key; ?>)</strong> <?php echo $value; ?>
                                                                <?php if ($isCorrect): ?>
                                                                    <span class="badge bg-success float-end">Correct</span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>

                                            <div class="explanations mt-3">
                                                <?php if ($question['correct_explanation']): ?>
                                                    <div class="correct-explanation mb-2">
                                                        <strong class="text-success">
                                                            <i class="fas fa-check-circle"></i> 
                                                            Explanation for Correct Answer:
                                                        </strong>
                                                        <div class="border rounded p-2 bg-success bg-opacity-10">
                                                            <?php echo $question['correct_explanation']; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if ($question['incorrect_explanation']): ?>
                                                    <div class="incorrect-explanation">
                                                        <strong class="text-danger">
                                                            <i class="fas fa-times-circle"></i> 
                                                            Explanation for Incorrect Answer:
                                                        </strong>
                                                        <div class="border rounded p-2 bg-danger bg-opacity-10">
                                                            <?php echo $question['incorrect_explanation']; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-3 text-end">
                                                <a href="edit_question.php?id=<?php echo $question['id']; ?>" class="btn btn-warning">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <button type="button" class="btn btn-danger" onclick="confirmDelete(<?php echo $question['id']; ?>)">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Question Modal -->
<div class="modal fade" id="addQuestionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Add New Question</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="exam_id" value="<?php echo $exam_id; ?>">
                    
                    <div class="mb-3">
                        <label class="form-label">Question Type</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_type" id="singleAnswer" value="1" checked>
                            <label class="form-check-label" for="singleAnswer">
                                Single Answer Question
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_type" id="twoAnswers" value="2">
                            <label class="form-check-label" for="twoAnswers">
                                Two Correct Answers
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="answer_type" id="threeAnswers" value="3">
                            <label class="form-check-label" for="threeAnswers">
                                Three Correct Answers
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="num_options" class="form-label">Number of Options</label>
                        <select class="form-select" id="num_options" name="num_options">
                            <option value="4">4 Options (A-D)</option>
                            <option value="5">5 Options (A-E)</option>
                            <option value="6">6 Options (A-F)</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_text" class="form-label">Question Text</label>
                        <textarea class="form-control editor" id="question_text" name="question_text" required></textarea>
                        <div class="invalid-feedback">Please enter the question text.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="question_image" class="form-label">Question Image (Optional)</label>
                        <input type="file" class="form-control" id="question_image" name="question_image" accept="image/*">
                        <div class="form-text">Supported formats: JPG, PNG, GIF. Max size: 2MB</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Options</label>
                        <div class="options-container">
                            <?php foreach (['A', 'B', 'C', 'D', 'E', 'F'] as $option): ?>
                                <div class="option-row" data-option="<?php echo $option; ?>" 
                                     <?php echo $option > 'D' ? 'style="display:none;"' : ''; ?>>
                                    <div class="option-letter">
                                        <?php echo $option; ?>
                                    </div>
                                    <div class="option-input flex-grow-1">
                                        <textarea class="form-control editor" 
                                                name="option_<?php echo strtolower($option); ?>" 
                                                <?php echo $option <= 'D' ? 'required' : ''; ?>></textarea>
                                    </div>
                                    <div class="correct-option">
                                        <input type="checkbox" class="form-check-input correct-option-checkbox" 
                                               name="correct_options[]" value="<?php echo $option; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="max-answers-warning" id="maxAnswersWarning">
                            You can only select up to <span id="maxAnswersCount">1</span> correct answer(s)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="correct_explanation" class="form-label text-success">
                            <i class="fas fa-check-circle"></i> 
                            Explanation for Correct Answer
                        </label>
                        <textarea class="form-control editor" id="correct_explanation" 
                                name="correct_explanation" 
                                placeholder="Explain why the correct answer(s) are right..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="incorrect_explanation" class="form-label text-danger">
                            <i class="fas fa-times-circle"></i> 
                            Explanation for Incorrect Answer
                        </label>
                        <textarea class="form-control editor" id="incorrect_explanation" 
                                name="incorrect_explanation" 
                                placeholder="Explain why the incorrect answers are wrong..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Question</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete this question? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CKEditor for all editor textareas
    document.querySelectorAll('.editor').forEach(function(element) {
        ClassicEditor
            .create(element, {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote'],
                placeholder: 'Type here...'
            })
            .catch(error => {
                console.error(error);
            });
    });

    // Handle number of options change
    document.getElementById('num_options').addEventListener('change', function() {
        const numOptions = parseInt(this.value);
        document.querySelectorAll('.option-row').forEach(row => {
            const option = row.dataset.option;
            const optionCode = option.charCodeAt(0) - 65; // Convert A-F to 0-5
            row.style.display = optionCode < numOptions ? '' : 'none';
            const input = row.querySelector('textarea');
            input.required = optionCode < numOptions;
        });
    });

    // Handle answer type change
    document.querySelectorAll('input[name="answer_type"]').forEach(function(radio) {
        radio.addEventListener('change', function() {
            const maxAnswers = parseInt(this.value);
            const warning = document.getElementById('maxAnswersWarning');
            const maxCount = document.getElementById('maxAnswersCount');
            
            // Update warning text
            maxCount.textContent = maxAnswers;
            
            // Update checkboxes
            const checkboxes = document.querySelectorAll('.correct-option-checkbox');
            let checkedCount = 0;
            checkboxes.forEach(cb => {
                if (cb.checked) checkedCount++;
            });
            
            // Uncheck excess selections
            if (checkedCount > maxAnswers) {
                checkboxes.forEach(cb => {
                    cb.checked = false;
                });
                warning.style.display = 'block';
            }
            
            // Update checkbox behavior
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function() {
                    const checked = document.querySelectorAll('.correct-option-checkbox:checked').length;
                    if (checked > maxAnswers) {
                        this.checked = false;
                        warning.style.display = 'block';
                    } else {
                        warning.style.display = 'none';
                    }
                });
            });
        });
    });

    // Form validation
    const form = document.querySelector('form.needs-validation');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Check if at least one correct answer is selected
        const correctOptions = form.querySelectorAll('.correct-option-checkbox:checked');
        if (correctOptions.length === 0) {
            event.preventDefault();
            alert('Please select at least one correct answer.');
        }

        form.classList.add('was-validated');
    });

    // Image preview
    document.getElementById('question_image')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            if (file.size > 2 * 1024 * 1024) {
                alert('File size must be less than 2MB');
                this.value = '';
            }
        }
    });
});

function confirmDelete(questionId) {
    document.getElementById('confirmDeleteBtn').href = 'delete_question.php?id=' + questionId;
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>

<?php include 'includes/footer.php'; ?>