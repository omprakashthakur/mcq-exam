<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin authentication
require_admin();

// Handle exam creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $duration_minutes = intval($_POST['duration'] ?? 60);
        $pass_percentage = floatval($_POST['pass_percentage'] ?? 60);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $is_public = isset($_POST['is_public']) ? 1 : 0;
        $exam_set_code = trim($_POST['exam_set_code'] ?? '');

        if (empty($title)) {
            throw new Exception('Exam title is required.');
        }

        // Validate exam set code format
        if (!empty($exam_set_code)) {
            if (!preg_match('/^[A-Za-z0-9-_]{1,20}$/', $exam_set_code)) {
                throw new Exception('Invalid exam set code format. Use only letters, numbers, hyphens and underscores.');
            }
            
            // Check if exam set code already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_sets WHERE exam_set_code = ?");
            $stmt->execute([$exam_set_code]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('This exam set code is already in use.');
            }
        }

        $pdo->beginTransaction();

        // Insert exam record
        $stmt = $pdo->prepare("
            INSERT INTO exam_sets (
                title, 
                description, 
                duration_minutes, 
                pass_percentage, 
                is_active,
                is_public,
                exam_set_code,
                created_by,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $title,
            $description,
            $duration_minutes,
            $pass_percentage,
            $is_active,
            $is_public,
            $exam_set_code,
            $_SESSION['user_id']
        ]);

        $exam_id = $pdo->lastInsertId();
        $pdo->commit();

        $_SESSION['success'] = 'Exam created successfully.';
        header("Location: view_exam.php?id=" . $exam_id);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Fetch existing exams with creator info
$stmt = $pdo->query("
    SELECT e.*, u.username as created_by_name,
           e.exam_set_code,
           (SELECT COUNT(*) FROM questions q WHERE q.exam_set_id = e.id) as question_count
    FROM exam_sets e
    LEFT JOIN users u ON e.created_by = u.id
    ORDER BY e.created_at DESC
");
$exams = $stmt->fetchAll();

$pageTitle = "Manage Exams";
include 'includes/header.php';
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Exams</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Exams</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3 class="card-title">All Exams</h3>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addExamModal">
                        <i class="fas fa-plus"></i> Add New Exam
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th style="width: 50px">ID</th>
                                <th>Title</th>
                                <th>Set Code</th>
                                <th>Questions</th>
                                <th>Duration</th>
                                <th>Pass %</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th style="width: 180px">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($exams)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">No exams found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($exams as $exam): ?>
                                <tr>
                                    <td><?php echo $exam['id']; ?></td>
                                    <td>
                                        <a href="view_exam.php?id=<?php echo $exam['id']; ?>">
                                            <?php echo htmlspecialchars($exam['title']); ?>
                                        </a>
                                        <?php if ($exam['is_public']): ?>
                                            <span class="badge bg-info">Public</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($exam['exam_set_code']): ?>
                                            <code><?php echo htmlspecialchars($exam['exam_set_code']); ?></code>
                                        <?php else: ?>
                                            <code>EX<?php echo sprintf('%04d', $exam['id']); ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo $exam['question_count']; ?></span>
                                    </td>
                                    <td><?php echo $exam['duration_minutes']; ?> mins</td>
                                    <td><?php echo $exam['pass_percentage']; ?>%</td>
                                    <td>
                                        <?php if ($exam['is_active']): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo date('Y-m-d', strtotime($exam['created_at'])); ?>
                                        <br>
                                        <small class="text-muted">by <?php echo htmlspecialchars($exam['created_by_name'] ?? 'Unknown'); ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="view_exam.php?id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-info" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit_exam.php?id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="manage_questions.php?exam_id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-primary" title="Questions">
                                                <i class="fas fa-question"></i>
                                            </a>
                                            <a href="share_exam.php?id=<?php echo $exam['id']; ?>" 
                                               class="btn btn-sm btn-success" title="Share">
                                                <i class="fas fa-share-alt"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" 
                                                    onclick="confirmDelete(<?php echo $exam['id']; ?>, '<?php echo htmlspecialchars(addslashes($exam['title'])); ?>')"
                                                    title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Add Exam Modal -->
<div class="modal fade" id="addExamModal" tabindex="-1" aria-labelledby="addExamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <div class="modal-header">
                    <h5 class="modal-title" id="addExamModalLabel">Add New Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="title" class="form-label">Exam Title</label>
                        <input type="text" class="form-control" id="title" name="title" required>
                        <div class="invalid-feedback">Please provide an exam title.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="exam_set_code" class="form-label">Exam Set Code</label>
                        <input type="text" class="form-control" id="exam_set_code" name="exam_set_code" 
                               pattern="[A-Za-z0-9-_]{1,20}" maxlength="20"
                               placeholder="e.g., MATH101 or PROG-2023">
                        <div class="form-text">Optional. Use letters, numbers, hyphens and underscores only.</div>
                        <div class="invalid-feedback">Invalid format. Use only letters, numbers, hyphens and underscores.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="duration" class="form-label">Duration (minutes)</label>
                                <input type="number" class="form-control" id="duration" name="duration" value="60" min="1" max="480" required>
                                <div class="invalid-feedback">Please provide a valid duration.</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="pass_percentage" class="form-label">Pass Percentage</label>
                                <input type="number" class="form-control" id="pass_percentage" name="pass_percentage" value="60" min="0" max="100" required>
                                <div class="invalid-feedback">Please provide a valid pass percentage.</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mt-3 mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">Exam Settings</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch d-flex align-items-center">
                                        <input class="form-check-input me-2" type="checkbox" id="is_active" name="is_active" checked>
                                        <div>
                                            <label class="form-check-label d-block" for="is_active">Active</label>
                                            <small class="text-muted">If checked, the exam can be assigned to students.</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check form-switch d-flex align-items-center">
                                        <input class="form-check-input me-2" type="checkbox" id="is_public" name="is_public">
                                        <div>
                                            <label class="form-check-label d-block" for="is_public">Public</label>
                                            <small class="text-muted">If checked, students can request to take this exam.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the exam: <strong id="examTitleToDelete"></strong>?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    This action cannot be undone and will delete all associated questions and student results!
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Permanently
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Form validation
(function() {
    'use strict';
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
})();

// Handle delete confirmation
function confirmDelete(examId, examTitle) {
    document.getElementById('examTitleToDelete').textContent = examTitle;
    document.getElementById('confirmDeleteBtn').href = `delete_exam.php?id=${examId}`;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>