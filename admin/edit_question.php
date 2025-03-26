<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$question_id = $_GET['id'] ?? 0;

// Get question details with correct answers
$stmt = $pdo->prepare("
    SELECT q.*, GROUP_CONCAT(ca.correct_option) as correct_answers 
    FROM questions q
    LEFT JOIN correct_answers ca ON q.id = ca.question_id
    WHERE q.id = ?
    GROUP BY q.id
");
$stmt->execute([$question_id]);
$question = $stmt->fetch();

if (!$question) {
    $_SESSION['error'] = 'Question not found';
    header('Location: manage_exams.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Update question
        $stmt = $pdo->prepare("
            UPDATE questions SET 
                question_text = ?,
                option_a = ?,
                option_b = ?,
                option_c = ?,
                option_d = ?,
                explanation = ?,
                is_multiple_answer = ?,
                correct_explanation = ?,
                incorrect_explanation = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $_POST['question_text'],
            $_POST['option_a'],
            $_POST['option_b'],
            $_POST['option_c'],
            $_POST['option_d'],
            $_POST['explanation'],
            isset($_POST['is_multiple_answer']) ? 1 : 0,
            $_POST['correct_explanation'],
            $_POST['incorrect_explanation'],
            $question_id
        ]);

        // Handle image upload if provided
        if (!empty($_FILES['question_image']['name'])) {
            $image = $_FILES['question_image'];
            $ext = pathinfo($image['name'], PATHINFO_EXTENSION);
            $new_name = 'question_' . uniqid() . '.' . $ext;
            $target_path = "../uploads/questions/" . $new_name;

            if (move_uploaded_file($image['tmp_name'], $target_path)) {
                // Update image path in database
                $stmt = $pdo->prepare("UPDATE questions SET question_image = ? WHERE id = ?");
                $stmt->execute([$new_name, $question_id]);
            }
        }

        // Delete existing correct answers
        $stmt = $pdo->prepare("DELETE FROM correct_answers WHERE question_id = ?");
        $stmt->execute([$question_id]);

        // Insert new correct answers
        $correct_options = $_POST['correct_options'] ?? [];
        if (!empty($correct_options)) {
            $stmt = $pdo->prepare("
                INSERT INTO correct_answers (question_id, correct_option) 
                VALUES (?, ?)
            ");
            foreach ($correct_options as $option) {
                $stmt->execute([$question_id, $option]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = 'Question updated successfully';
        header('Location: manage_questions.php?exam_id=' . $question['exam_set_id']);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}

$pageTitle = "Edit Question";
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
    margin-bottom: 10px;
}
.option-letter {
    font-weight: bold;
    width: 30px;
}
.option-input {
    flex: 1;
}
.correct-option-checkbox {
    width: 20px;
    height: 20px;
}
</style>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Question</h3>
                </div>
                <form method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_multiple_answer" 
                                       name="is_multiple_answer" <?php echo $question['is_multiple_answer'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_multiple_answer">
                                    Multiple correct answers allowed
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="question_text" class="form-label">Question Text</label>
                            <textarea class="form-control editor" id="question_text" name="question_text" required>
                                <?php echo htmlspecialchars($question['question_text']); ?>
                            </textarea>
                        </div>

                        <div class="mb-3">
                            <label for="question_image" class="form-label">Question Image</label>
                            <?php if ($question['question_image']): ?>
                                <div class="mb-2">
                                    <img src="../uploads/questions/<?php echo htmlspecialchars($question['question_image']); ?>" 
                                         alt="Current Image" class="img-fluid" style="max-height: 200px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="question_image" name="question_image" accept="image/*">
                            <div class="form-text">Leave empty to keep current image. Max size: 2MB</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Options</label>
                            <div class="options-container">
                                <?php 
                                $correct_answers = explode(',', $question['correct_answers']);
                                $options = [
                                    'A' => $question['option_a'],
                                    'B' => $question['option_b'],
                                    'C' => $question['option_c'],
                                    'D' => $question['option_d']
                                ];
                                foreach ($options as $key => $value): 
                                ?>
                                    <div class="option-row">
                                        <div class="option-letter"><?php echo $key; ?>)</div>
                                        <div class="option-input">
                                            <textarea class="form-control editor" 
                                                      name="option_<?php echo strtolower($key); ?>" required><?php 
                                                echo htmlspecialchars($value); 
                                            ?></textarea>
                                        </div>
                                        <div class="correct-option">
                                            <input type="<?php echo $question['is_multiple_answer'] ? 'checkbox' : 'radio'; ?>" 
                                                   class="correct-option-checkbox" 
                                                   name="correct_options[]" 
                                                   value="<?php echo $key; ?>"
                                                   <?php echo in_array($key, $correct_answers) ? 'checked' : ''; ?>>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="explanation" class="form-label">Explanation (Optional)</label>
                            <textarea class="form-control editor" id="explanation" name="explanation">
                                <?php echo htmlspecialchars($question['explanation']); ?>
                            </textarea>
                        </div>

                        <div class="mb-3">
                            <label for="correct_explanation" class="form-label text-success">
                                <i class="fas fa-check-circle"></i> 
                                Explanation for Correct Answer
                            </label>
                            <textarea class="form-control editor" id="correct_explanation" name="correct_explanation">
                                <?php echo htmlspecialchars($question['correct_explanation']); ?>
                            </textarea>
                            <div class="form-text">Explain why the correct answer(s) are right</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="incorrect_explanation" class="form-label text-danger">
                                <i class="fas fa-times-circle"></i> 
                                Explanation for Incorrect Answer
                            </label>
                            <textarea class="form-control editor" id="incorrect_explanation" name="incorrect_explanation">
                                <?php echo htmlspecialchars($question['incorrect_explanation']); ?>
                            </textarea>
                            <div class="form-text">Explain why the incorrect answers are wrong</div>
                        </div>
                        
                    </div>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between">
                            <a href="manage_questions.php?exam_id=<?php echo $question['exam_set_id']; ?>" 
                               class="btn btn-secondary">Cancel</a>
                            <button type="submit" class="btn btn-primary">Update Question</button>
                        </div>
                    </div>
                </form>
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

    // Handle multiple answer toggle
    document.getElementById('is_multiple_answer').addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.correct-option-checkbox');
        checkboxes.forEach(cb => {
            cb.type = this.checked ? 'checkbox' : 'radio';
            if (!this.checked) {
                cb.name = 'correct_options[]';
                // If switching to single answer, uncheck all except first checked
                const checked = document.querySelector('.correct-option-checkbox:checked');
                if (checked && cb !== checked) {
                    cb.checked = false;
                }
            }
        });
    });

    // Form validation
    const form = document.querySelector('form');
    form.addEventListener('submit', function(event) {
        if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
        }

        // Validate at least one correct answer is selected
        const correctOptions = form.querySelectorAll('.correct-option-checkbox:checked');
        if (correctOptions.length === 0) {
            event.preventDefault();
            alert('Please select at least one correct answer.');
            return;
        }

        form.classList.add('was-validated');
    });

    // Image validation
    document.getElementById('question_image')?.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file && file.size > 2 * 1024 * 1024) {
            alert('File size must be less than 2MB');
            this.value = '';
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>