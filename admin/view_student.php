<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$student_id = $_GET['id'] ?? 0;
$items_per_page = 4;

// Get current page numbers for each section
$current_page_access = isset($_GET['page_access']) ? (int)$_GET['page_access'] : 1;
$current_page_requests = isset($_GET['page_requests']) ? (int)$_GET['page_requests'] : 1;
$current_page_history = isset($_GET['page_history']) ? (int)$_GET['page_history'] : 1;

// Calculate offsets
$offset_access = ($current_page_access - 1) * $items_per_page;
$offset_requests = ($current_page_requests - 1) * $items_per_page;
$offset_history = ($current_page_history - 1) * $items_per_page;

// Get student details
$stmt = $pdo->prepare("
    SELECT u.*, sp.full_name, sp.phone, sp.address
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.id = ? AND u.role = 'user'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found';
    header('Location: manage_students.php');
    exit();
}

// Get total counts for pagination
$stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_access WHERE user_id = ?");
$stmt->execute([$student_id]);
$total_access = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_requests WHERE user_id = ?");
$stmt->execute([$student_id]);
$total_requests = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_attempts WHERE user_id = ?");
$stmt->execute([$student_id]);
$total_attempts = $stmt->fetchColumn();

// Calculate total pages
$total_pages_access = ceil($total_access / $items_per_page);
$total_pages_requests = ceil($total_requests / $items_per_page);
$total_pages_history = ceil($total_attempts / $items_per_page);

// Get exam access history with pagination
$stmt = $pdo->prepare("
    SELECT ea.*, e.title as exam_title, e.duration_minutes
    FROM exam_access ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    WHERE ea.user_id = ?
    ORDER BY ea.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$student_id, $items_per_page, $offset_access]);
$exam_access = $stmt->fetchAll();

// Get exam requests with pagination
$stmt = $pdo->prepare("
    SELECT er.*, e.title as exam_title, u.username as reviewed_by_name
    FROM exam_requests er
    JOIN exam_sets e ON er.exam_set_id = e.id
    LEFT JOIN users u ON er.reviewed_by = u.id
    WHERE er.user_id = ?
    ORDER BY er.request_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$student_id, $items_per_page, $offset_requests]);
$exam_requests = $stmt->fetchAll();

// Get exam attempts with pagination
$stmt = $pdo->prepare("
    SELECT ea.*, e.title as exam_title,
           COUNT(DISTINCT q.id) as total_questions,
           SUM(ua.is_correct) as correct_answers
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    LEFT JOIN questions q ON e.id = q.exam_set_id
    LEFT JOIN user_answers ua ON ea.id = ua.exam_attempt_id AND q.id = ua.question_id
    WHERE ea.user_id = ?
    GROUP BY ea.id
    ORDER BY ea.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$student_id, $items_per_page, $offset_history]);
$exam_attempts = $stmt->fetchAll();

// Get available exams for assignment
$stmt = $pdo->prepare("
    SELECT e.*
    FROM exam_sets e
    WHERE NOT EXISTS (
        SELECT 1 FROM exam_access ea 
        WHERE ea.exam_set_id = e.id 
        AND ea.user_id = ? 
        AND ea.expiry_date > NOW()
        AND ea.is_used = 0
    )
    ORDER BY e.title
");
$stmt->execute([$student_id]);
$available_exams = $stmt->fetchAll();

$pageTitle = "Student Profile: " . htmlspecialchars($student['username'] ?? '');
include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Student Profile</h2>
        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
    </div>

    <!-- Student Information -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Student Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name'] ?? $student['username'] ?? ''); ?></p>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($student['username'] ?? ''); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email'] ?? ''); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($student['phone'] ?? 'Not provided'); ?></p>
                    <p><strong>Address:</strong> <?php echo nl2br(htmlspecialchars($student['address'] ?? 'Not provided')); ?></p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">Assign New Exam</h5>
                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#assignExamModal">
                        <i class='bx bx-plus'></i> Assign Exam
                    </button>
                </div>
                <div class="card-body">
                    <h6>Recent Exam Access</h6>
                    <?php if (empty($exam_access)): ?>
                        <p class="text-muted">No exam access records found.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Status</th>
                                        <th>Expires</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($exam_access as $access): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($access['exam_title']); ?></td>
                                            <td>
                                                <?php if ($access['is_used']): ?>
                                                    <span class="badge bg-secondary">Used</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($access['expiry_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ($total_pages_access > 1): ?>
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div class="text-muted small">
                                    Showing <?php echo $offset_access + 1; ?>-<?php echo min($offset_access + $items_per_page, $total_access); ?> of <?php echo $total_access; ?>
                                </div>
                                <nav aria-label="Exam access pagination">
                                    <ul class="pagination pagination-sm mb-0">
                                        <?php for ($i = 1; $i <= $total_pages_access; $i++): ?>
                                            <li class="page-item <?php echo ($i == $current_page_access) ? 'active' : ''; ?>">
                                                <a class="page-link" href="?id=<?php echo $student_id; ?>&page_access=<?php echo $i; ?>&page_requests=<?php echo $current_page_requests; ?>&page_history=<?php echo $current_page_history; ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                    </ul>
                                </nav>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam Requests -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Exam Requests</h5>
        </div>
        <div class="card-body">
            <?php if (empty($exam_requests)): ?>
                <p class="text-muted">No exam requests found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Request Date</th>
                                <th>Preferred Date</th>
                                <th>Status</th>
                                <th>Response</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_requests as $request): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($request['exam_title']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($request['request_date'])); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?></td>
                                    <td>
                                        <?php
                                        $statusBadge = [
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger'
                                        ][$request['status']];
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['response_date']): ?>
                                            <?php echo date('Y-m-d', strtotime($request['response_date'])); ?>
                                            <br>
                                            <small class="text-muted">by <?php echo htmlspecialchars($request['reviewed_by_name']); ?></small>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#approveRequestModal"
                                                    data-request-id="<?php echo $request['id']; ?>"
                                                    data-exam-title="<?php echo htmlspecialchars($request['exam_title']); ?>">
                                                Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#rejectRequestModal"
                                                    data-request-id="<?php echo $request['id']; ?>"
                                                    data-exam-title="<?php echo htmlspecialchars($request['exam_title']); ?>">
                                                Reject
                                            </button>
                                        <?php else: ?>
                                            <?php if ($request['remarks']): ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="tooltip" 
                                                        title="<?php echo htmlspecialchars($request['remarks']); ?>">
                                                    View Remarks
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total_pages_requests > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <?php echo $offset_requests + 1; ?>-<?php echo min($offset_requests + $items_per_page, $total_requests); ?> of <?php echo $total_requests; ?>
                        </div>
                        <nav aria-label="Exam requests pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($i = 1; $i <= $total_pages_requests; $i++): ?>
                                    <li class="page-item <?php echo ($i == $current_page_requests) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $student_id; ?>&page_access=<?php echo $current_page_access; ?>&page_requests=<?php echo $i; ?>&page_history=<?php echo $current_page_history; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Exam History -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Exam History</h5>
        </div>
        <div class="card-body">
            <?php if (empty($exam_attempts)): ?>
                <p class="text-muted">No exam attempts found.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam</th>
                                <th>Date</th>
                                <th>Score</th>
                                <th>Result</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exam_attempts as $attempt): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($attempt['start_time'])); ?></td>
                                    <td>
                                        <?php if ($attempt['status'] === 'completed'): ?>
                                            <?php echo $attempt['score']; ?>%
                                            (<?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>)
                                        <?php else: ?>
                                            In Progress
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attempt['status'] === 'completed'): ?>
                                            <span class="badge bg-<?php echo $attempt['score'] >= 70 ? 'success' : 'danger'; ?>">
                                                <?php echo $attempt['score'] >= 70 ? 'Pass' : 'Fail'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Incomplete</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view_attempt.php?id=<?php echo $attempt['id']; ?>" 
                                           class="btn btn-sm btn-info">View Details</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total_pages_history > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <div class="text-muted small">
                            Showing <?php echo $offset_history + 1; ?>-<?php echo min($offset_history + $items_per_page, $total_attempts); ?> of <?php echo $total_attempts; ?>
                        </div>
                        <nav aria-label="Exam history pagination">
                            <ul class="pagination pagination-sm mb-0">
                                <?php for ($i = 1; $i <= $total_pages_history; $i++): ?>
                                    <li class="page-item <?php echo ($i == $current_page_history) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $student_id; ?>&page_access=<?php echo $current_page_access; ?>&page_requests=<?php echo $current_page_requests; ?>&page_history=<?php echo $i; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Assign Exam Modal -->
<div class="modal fade" id="assignExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="assign_exam.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Assign New Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                    <input type="hidden" name="return_to" value="view_student">
                    
                    <div class="mb-3">
                        <label for="exam_id" class="form-label">Select Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id" required>
                            <option value="">Choose exam...</option>
                            <?php foreach ($available_exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>">
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="expiry_days" class="form-label">Access Duration (days)</label>
                        <input type="number" class="form-control" id="expiry_days" name="expiry_days" 
                               value="7" min="1" max="30" required>
                        <div class="form-text">Number of days the student will have access to this exam.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Exam</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Approve Request Modal -->
<div class="modal fade" id="approveRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="manage_requests.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Approve Exam Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="approveRequestId">
                    <input type="hidden" name="action" value="approve">
                    <div id="approveRequestInfo" class="alert alert-info mb-3"></div>
                    <div class="mb-3">
                        <label for="approve_remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="approve_remarks" name="remarks" rows="3" 
                                  placeholder="Enter any additional instructions for the student"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Reject Request Modal -->
<div class="modal fade" id="rejectRequestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="manage_requests.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Reject Exam Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="rejectRequestId">
                    <input type="hidden" name="action" value="reject">
                    <div id="rejectRequestInfo" class="alert alert-warning mb-3"></div>
                    <div class="mb-3">
                        <label for="reject_remarks" class="form-label">Rejection Reason</label>
                        <textarea class="form-control" id="reject_remarks" name="remarks" rows="3" required
                                  placeholder="Please provide a reason for rejecting this request"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    });

    // Handle approve request modal
    var approveRequestModal = document.getElementById('approveRequestModal')
    if (approveRequestModal) {
        approveRequestModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var requestId = button.getAttribute('data-request-id')
            var examTitle = button.getAttribute('data-exam-title')
            
            document.getElementById('approveRequestId').value = requestId
            document.getElementById('approveRequestInfo').innerHTML = 
                `Are you sure you want to approve the exam request for: <strong>${examTitle}</strong>?`
        })
    }

    // Handle reject request modal
    var rejectRequestModal = document.getElementById('rejectRequestModal')
    if (rejectRequestModal) {
        rejectRequestModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var requestId = button.getAttribute('data-request-id')
            var examTitle = button.getAttribute('data-exam-title')
            
            document.getElementById('rejectRequestId').value = requestId
            document.getElementById('rejectRequestInfo').innerHTML = 
                `Are you sure you want to reject the exam request for: <strong>${examTitle}</strong>?`
        })
    }
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>