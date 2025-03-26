<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Verify user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    $_SESSION['error'] = 'Please login as a student to continue.';
    header('Location: ../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Get available exams that the student can request
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        (SELECT COUNT(*) > 0 FROM exam_requests er 
         WHERE er.exam_set_id = e.id AND er.user_id = ? AND er.status = 'pending') as has_pending_request,
        (SELECT COUNT(*) > 0 FROM exam_access ea 
         WHERE ea.exam_set_id = e.id AND ea.user_id = ? 
         AND ea.expiry_date > NOW() AND ea.is_used = 0) as has_access,
        COUNT(q.id) as question_count
    FROM exam_sets e
    LEFT JOIN questions q ON e.id = q.exam_set_id
    WHERE e.is_active = 1 AND e.is_public = 1
    GROUP BY e.id
    ORDER BY e.title
");
$stmt->execute([$user_id, $user_id]);
$available_exams = $stmt->fetchAll();

// Get exam access history
$stmt = $pdo->prepare("
    SELECT ea.*, e.title as exam_title, e.duration_minutes
    FROM exam_access ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    WHERE ea.user_id = ?
    ORDER BY ea.created_at DESC
");
$stmt->execute([$user_id]);
$exam_access = $stmt->fetchAll();

// Get exam attempts
$stmt = $pdo->prepare("
    SELECT ea.*, e.title as exam_title,
           COUNT(DISTINCT q.id) as total_questions,
           SUM(ua.is_correct) as correct_answers
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    LEFT JOIN questions q ON e.id = q.exam_set_id
    LEFT JOIN user_answers ua ON ea.id = ua.exam_attempt_id AND q.id = ua.question_id
    WHERE ea.user_id = ?
    GROUP BY ea.id, e.title
    ORDER BY ea.start_time DESC
");
$stmt->execute([$user_id]);
$exam_attempts = $stmt->fetchAll();

// Get pending requests
$stmt = $pdo->prepare("
    SELECT er.*, e.title as exam_title
    FROM exam_requests er
    JOIN exam_sets e ON er.exam_set_id = e.id
    WHERE er.user_id = ? AND er.status = 'pending'
    ORDER BY er.request_date DESC
");
$stmt->execute([$user_id]);
$pending_requests = $stmt->fetchAll();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="row">
        <!-- Available Exams and Request Section -->
        <div class="col-md-8">
            <!-- Request Exam Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Available Exams</h5>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestExamModal">
                        <i class="bi bi-plus-circle"></i> Request New Exam
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($available_exams)): ?>
                        <p class="text-muted">No exams available at the moment.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($available_exams as $exam): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($exam['title']); ?></h6>
                                            <p class="mb-1 small text-muted">
                                                Duration: <?php echo $exam['duration_minutes']; ?> minutes
                                            </p>
                                        </div>
                                        <div>
                                            <?php if ($exam['has_access']): ?>
                                                <span class="badge bg-success">Access Granted</span>
                                            <?php elseif ($exam['has_pending_request']): ?>
                                                <span class="badge bg-warning">Request Pending</span>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-primary btn-sm"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#requestExamModal"
                                                        data-exam-id="<?php echo $exam['id']; ?>"
                                                        data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>">
                                                    Request Access
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Current Access -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Your Exam Access</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($exam_access)): ?>
                        <p class="text-muted">You haven't been granted access to any exams yet.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Access Code</th>
                                        <th>Status</th>
                                        <th>Expires</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam_access as $access): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($access['exam_title']); ?></td>
                                            <td><?php echo htmlspecialchars($access['access_code']); ?></td>
                                            <td>
                                                <?php if ($access['is_used']): ?>
                                                    <span class="badge bg-secondary">Used</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($access['expiry_date'])); ?></td>
                                            <td>
                                                <?php if (!$access['is_used'] && strtotime($access['expiry_date']) > time()): ?>
                                                    <a href="../start_exam.php?access=<?php echo $access['access_url']; ?>" 
                                                       class="btn btn-primary btn-sm">Start Exam</a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Pending Requests -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Pending Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <p class="text-muted">No pending exam requests.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($pending_requests as $request): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($request['exam_title']); ?></h6>
                                    <p class="mb-1 small text-muted">
                                        Requested: <?php echo date('Y-m-d', strtotime($request['request_date'])); ?><br>
                                        Preferred Date: <?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Results</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($exam_attempts)): ?>
                        <p class="text-muted">You haven't taken any exams yet.</p>
                    <?php else: ?>
                        <div class="list-group">
                            <?php foreach ($exam_attempts as $attempt): ?>
                                <div class="list-group-item">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($attempt['exam_title']); ?></h6>
                                    <p class="mb-1">
                                        Score: <?php echo $attempt['score'] ?? 'In Progress'; ?>%<br>
                                        Questions: <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo date('Y-m-d H:i', strtotime($attempt['start_time'])); ?>
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Exam Modal -->
<div class="modal fade" id="requestExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="request_exam.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Request Exam Access</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">Select Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id" required>
                            <option value="">Choose exam...</option>
                            <?php foreach ($available_exams as $exam): ?>
                                <?php if (!$exam['has_pending_request'] && !$exam['has_access']): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['title']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="preferred_date" class="form-label">Preferred Exam Date</label>
                        <input type="date" class="form-control" id="preferred_date" name="preferred_date" 
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                               max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" 
                               required>
                        <div class="form-text">Select a date between tomorrow and 30 days from now.</div>
                    </div>
                    <div class="mb-3">
                        <label for="request_reason" class="form-label">Request Reason</label>
                        <textarea class="form-control" id="request_reason" name="request_reason" 
                                  rows="3" required placeholder="Please explain why you want to take this exam..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle pre-selected exam in modal
    var requestExamModal = document.getElementById('requestExamModal');
    if (requestExamModal) {
        requestExamModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var examId = button.getAttribute('data-exam-id');
            var examTitle = button.getAttribute('data-exam-title');
            
            if (examId) {
                document.getElementById('exam_id').value = examId;
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>