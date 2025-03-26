<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$exam_id = $_GET['id'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
        $pass_percentage = floatval($_POST['pass_percentage'] ?? 70);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_public = isset($_POST['is_public']) ? 1 : 0;

        if (empty($title)) {
            throw new Exception('Exam title is required.');
        }

        $pdo->beginTransaction();

        // Update exam details
        $stmt = $pdo->prepare("
            UPDATE exam_sets 
            SET title = ?, 
                description = ?, 
                duration_minutes = ?,
                pass_percentage = ?,
                is_active = ?,
                is_public = ?,
                updated_by = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $title,
            $description,
            $duration_minutes,
            $pass_percentage,
            $is_active,
            $is_public,
            $_SESSION['user_id'],
            $exam_id
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Exam updated successfully.';
        header("Location: view_exam.php?id=" . $exam_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get exam details
$stmt = $pdo->prepare("
    SELECT e.*, u.username as created_by_name, u2.username as updated_by_name
    FROM exam_sets e
    LEFT JOIN users u ON e.created_by = u.id
    LEFT JOIN users u2 ON e.updated_by = u2.id
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    $_SESSION['error'] = 'Exam not found';
    header('Location: manage_exams.php');
    exit();
}

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Edit Exam</h2>
        <a href="view_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-secondary">Back to Exam</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <div class="mb-3">
                    <label for="title" class="form-label">Exam Title</label>
                    <input type="text" class="form-control" id="title" name="title" 
                           value="<?php echo htmlspecialchars($exam['title']); ?>" required>
                    <div class="invalid-feedback">Please provide an exam title.</div>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" 
                              rows="3"><?php echo htmlspecialchars($exam['description']); ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="duration_minutes" class="form-label">Duration (minutes)</label>
                            <input type="number" class="form-control" id="duration_minutes" 
                                   name="duration_minutes" min="1" max="480"
                                   value="<?php echo $exam['duration_minutes']; ?>" required>
                            <div class="invalid-feedback">Please provide a valid duration (1-480 minutes).</div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="pass_percentage" class="form-label">Pass Percentage</label>
                            <input type="number" class="form-control" id="pass_percentage" 
                                   name="pass_percentage" min="0" max="100" step="0.1"
                                   value="<?php echo $exam['pass_percentage']; ?>" required>
                            <div class="invalid-feedback">Please provide a valid pass percentage (0-100).</div>
                        </div>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_active" 
                                   name="is_active" <?php echo $exam['is_active'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_active">Active</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="is_public" 
                                   name="is_public" <?php echo $exam['is_public'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="is_public">Public</label>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <small class="text-muted">
                        Created by <?php echo htmlspecialchars($exam['created_by_name']); ?> 
                        on <?php echo date('Y-m-d H:i', strtotime($exam['created_at'])); ?>
                        <?php if ($exam['updated_by']): ?>
                            <br>
                            Last updated by <?php echo htmlspecialchars($exam['updated_by_name']); ?>
                            on <?php echo date('Y-m-d H:i', strtotime($exam['updated_at'])); ?>
                        <?php endif; ?>
                    </small>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Update Exam</button>
                    <a href="manage_questions.php?exam_id=<?php echo $exam_id; ?>" 
                       class="btn btn-info">Manage Questions</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Form validation
(function() {
    'use strict';
    var forms = document.querySelectorAll('.needs-validation');
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();
</script>

<?php include 'includes/footer.php'; ?>