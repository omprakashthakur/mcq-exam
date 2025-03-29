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

// Pagination settings
$items_per_page = 5; // For other tables
$results_per_page = 3; // Specifically for Recent Results

// Available exams pagination
$available_page = isset($_GET['available_page']) ? (int)$_GET['available_page'] : 1;
$available_offset = ($available_page - 1) * $items_per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total FROM exam_sets e
    WHERE e.is_active = 1
");
$stmt->execute();
$total_available = $stmt->fetch()['total'];
$total_available_pages = ceil($total_available / $items_per_page);

// Modify available exams query with pagination
$stmt = $pdo->prepare("
    SELECT 
        e.*,
        (SELECT COUNT(*) > 0 FROM exam_requests er 
         WHERE er.exam_set_id = e.id AND er.user_id = ? AND er.status = 'pending') as has_pending_request,
        (SELECT COUNT(*) > 0 FROM exam_access ea 
         WHERE ea.exam_set_id = e.id AND ea.user_id = ? 
         AND ea.expiry_date > NOW() AND ea.is_used = 0) as has_access,
        (SELECT COUNT(*) FROM exam_attempts ea 
         WHERE ea.exam_set_id = e.id AND ea.user_id = ? AND ea.status = 'completed') as attempt_count,
        COUNT(q.id) as question_count
    FROM exam_sets e
    LEFT JOIN questions q ON e.id = q.exam_set_id
    WHERE e.is_active = 1 
    GROUP BY e.id
    ORDER BY e.title
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $user_id, $user_id, $items_per_page, $available_offset]);
$available_exams = $stmt->fetchAll();

// Completed exams pagination
$completed_page = isset($_GET['completed_page']) ? (int)$_GET['completed_page'] : 1;
$completed_offset = ($completed_page - 1) * $items_per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.id) as total
    FROM exam_sets e
    JOIN exam_attempts ea ON e.id = ea.exam_set_id
    WHERE ea.user_id = ? AND ea.status = 'completed'
");
$stmt->execute([$user_id]);
$total_completed = $stmt->fetch()['total'];
$total_completed_pages = ceil($total_completed / $items_per_page);

// Modify completed exams query with pagination
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        e.*,
        MAX(ea.score) as best_score,
        COUNT(DISTINCT ea.id) as total_attempts,
        (SELECT COUNT(*) > 0 FROM exam_retake_requests err 
         WHERE err.exam_set_id = e.id AND err.user_id = ? AND err.status = 'pending') as has_pending_retake
    FROM exam_sets e
    JOIN exam_attempts ea ON e.id = ea.exam_set_id
    WHERE ea.user_id = ? AND ea.status = 'completed'
    GROUP BY e.id
    ORDER BY ea.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $user_id, $items_per_page, $completed_offset]);
$completed_exams = $stmt->fetchAll();

// Recent results pagination
$results_page = isset($_GET['results_page']) ? (int)$_GET['results_page'] : 1;
$results_offset = ($results_page - 1) * $results_per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM exam_attempts ea
    WHERE ea.user_id = ?
");
$stmt->execute([$user_id]);
$total_results = $stmt->fetch()['total'];
$total_results_pages = ceil($total_results / $results_per_page);

// Modify exam attempts query with pagination
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
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $results_per_page, $results_offset]);
$exam_attempts = $stmt->fetchAll();

// Pending requests pagination
$pending_page = isset($_GET['pending_page']) ? (int)$_GET['pending_page'] : 1;
$pending_offset = ($pending_page - 1) * $items_per_page;

$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM exam_requests er
    WHERE er.user_id = ? AND er.status = 'pending'
");
$stmt->execute([$user_id]);
$total_pending = $stmt->fetch()['total'];
$total_pending_pages = ceil($total_pending / $items_per_page);

// Modify pending requests query with pagination
$stmt = $pdo->prepare("
    SELECT er.*, e.title as exam_title
    FROM exam_requests er
    JOIN exam_sets e ON er.exam_set_id = e.id
    WHERE er.user_id = ? AND er.status = 'pending'
    ORDER BY er.request_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$user_id, $items_per_page, $pending_offset]);
$pending_requests = $stmt->fetchAll();

$pageTitle = 'Student Dashboard';
include '../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="row g-4">
        <!-- Available Exams and Request Section -->
        <div class="col-lg-8">
            <!-- Request Exam Card -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">Available Exams</h5>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestExamModal">
                            <i class="fas fa-plus-circle"></i> Request New Exam
                        </button>
                        <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#retakeExamModal">
                            <i class="fas fa-redo"></i> Request Retake
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($available_exams)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No exams available at the moment.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Set Code</th>
                                        <th>Questions</th>
                                        <th>Duration</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($available_exams as $exam): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td><code><?php echo sprintf('EX%04d', $exam['id']); ?></code></td>
                                            <td><?php echo $exam['question_count']; ?></td>
                                            <td><?php echo $exam['duration_minutes']; ?> mins</td>
                                            <td>
                                                <?php if ($exam['has_access']): ?>
                                                    <span class="badge bg-success">Access Granted</span>
                                                <?php elseif ($exam['has_pending_request']): ?>
                                                    <span class="badge bg-warning">Request Pending</span>
                                                <?php else: ?>
                                                    <span class="badge bg-info">Available</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if (!$exam['has_access'] && !$exam['has_pending_request']): ?>
                                                    <button type="button" class="btn btn-primary btn-sm" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#requestExamModal"
                                                            data-exam-id="<?php echo $exam['id']; ?>"
                                                            data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>">
                                                        Request Access
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Available Exams Pagination -->
                        <?php if ($total_available_pages > 1): ?>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pagination-info">
                                        Showing <?php echo $available_offset + 1; ?> to <?php echo min($available_offset + $items_per_page, $total_available); ?> 
                                        of <?php echo $total_available; ?> entries
                                    </div>
                                    <nav aria-label="Available exams pagination">
                                        <ul class="pagination mb-0">
                                            <?php if ($available_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?available_page=<?php echo ($available_page - 1); ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $total_available_pages; $i++): ?>
                                                <li class="page-item <?php echo ($i === $available_page ? 'active' : ''); ?>">
                                                    <a class="page-link" href="?available_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($available_page < $total_available_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?available_page=<?php echo ($available_page + 1); ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Exams Section -->
            <div class="card mb-4">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Completed Exams</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($completed_exams)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">You haven't completed any exams yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Exam Title</th>
                                        <th>Set Code</th>
                                        <th>Best Score</th>
                                        <th>Attempts</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($completed_exams as $exam): ?>
                                        <tr>
                                            <td class="text-break"><?php echo htmlspecialchars($exam['title']); ?></td>
                                            <td class="text-nowrap"><code><?php echo sprintf('EX%04d', $exam['id']); ?></code></td>
                                            <td class="text-center"><?php echo number_format($exam['best_score'], 1); ?>%</td>
                                            <td class="text-center"><?php echo $exam['total_attempts']; ?></td>
                                            <td class="text-center">
                                                <?php if (!$exam['has_pending_retake']): ?>
                                                    <button type="button" class="btn btn-primary btn-sm"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#retakeExamModal"
                                                            data-exam-id="<?php echo $exam['id']; ?>"
                                                            data-exam-title="<?php echo htmlspecialchars($exam['title']); ?>">
                                                        Request Retake
                                                    </button>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">Retake Pending</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Completed Exams Pagination -->
                        <?php if ($total_completed_pages > 1): ?>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pagination-info">
                                        Showing <?php echo $completed_offset + 1; ?> to <?php echo min($completed_offset + $items_per_page, $total_completed); ?> 
                                        of <?php echo $total_completed; ?> entries
                                    </div>
                                    <nav aria-label="Completed exams pagination">
                                        <ul class="pagination mb-0">
                                            <?php if ($completed_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?completed_page=<?php echo ($completed_page - 1); ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $total_completed_pages; $i++): ?>
                                                <li class="page-item <?php echo ($i === $completed_page ? 'active' : ''); ?>">
                                                    <a class="page-link" href="?completed_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($completed_page < $total_completed_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?completed_page=<?php echo ($completed_page + 1); ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Pending Requests -->
            <div class="card mb-4">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Pending Requests</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($pending_requests)): ?>
                        <div class="text-center">
                            <p class="text-muted mb-0">No pending exam requests.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($pending_requests as $request): ?>
                                <div class="list-group-item px-0">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($request['exam_title']); ?></h6>
                                    <p class="mb-1 small text-muted">
                                        Requested: <?php echo date('Y-m-d', strtotime($request['request_date'])); ?><br>
                                        Preferred Date: <?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?>
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Pending Requests Pagination -->
                        <?php if ($total_pending_pages > 1): ?>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pagination-info small">
                                        Showing <?php echo $pending_offset + 1; ?> to <?php echo min($pending_offset + $items_per_page, $total_pending); ?> 
                                        of <?php echo $total_pending; ?> entries
                                    </div>
                                    <nav aria-label="Pending requests pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php if ($pending_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?pending_page=<?php echo ($pending_page - 1); ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $total_pending_pages; $i++): ?>
                                                <li class="page-item <?php echo ($i === $pending_page ? 'active' : ''); ?>">
                                                    <a class="page-link" href="?pending_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($pending_page < $total_pending_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?pending_page=<?php echo ($pending_page + 1); ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Results -->
            <div class="card">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Recent Results</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($exam_attempts)): ?>
                        <div class="text-center">
                            <p class="text-muted mb-0">You haven't taken any exams yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($exam_attempts as $attempt): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($attempt['exam_title']); ?></h6>
                                            <p class="mb-1">
                                                Score: <?php echo $attempt['score'] ?? 'In Progress'; ?>%<br>
                                                Questions: <?php echo $attempt['correct_answers']; ?>/<?php echo $attempt['total_questions']; ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y H:i', strtotime($attempt['start_time'])); ?>
                                            </small>
                                        </div>
                                        <?php if ($attempt['status'] === 'completed'): ?>
                                            <a href="view_result.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-primary align-self-center">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        <?php else: ?>
                                            <a href="continue_exam.php?attempt_id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-warning align-self-center" 
                                               style="font-size: 0.51rem; padding: 0.60rem 0.5rem;">
                                                <i class="fas fa-play"></i> Continue
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Recent Results Pagination -->
                        <?php if ($total_results_pages > 1): ?>
                            <div class="mt-3">
                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                    <div class="pagination-info small">
                                        Showing <?php echo $results_offset + 1; ?> to <?php echo min($results_offset + $results_per_page, $total_results); ?> 
                                        of <?php echo $total_results; ?> entries
                                    </div>
                                    <nav aria-label="Recent results pagination" class="d-flex justify-content-center flex-grow-1">
                                        <ul class="pagination pagination-sm mb-0">
                                            <?php if ($results_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?results_page=<?php echo ($results_page - 1); ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $start_page = max(1, $results_page - 2);
                                            $end_page = min($total_results_pages, $results_page + 2);
                                            
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?results_page=1">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?php echo ($i === $results_page ? 'active' : ''); ?>">
                                                    <a class="page-link" href="?results_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor;
                                            
                                            if ($end_page < $total_results_pages) {
                                                if ($end_page < $total_results_pages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?results_page=' . $total_results_pages . '">' . $total_results_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <?php if ($results_page < $total_results_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?results_page=<?php echo ($results_page + 1); ?>" aria-label="Next">
                                                        <span aria-hidden="true">&raquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<?php include '../includes/modals/request_exam_modal.php'; ?>
<?php include '../includes/modals/retake_exam_modal.php'; ?>

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

    // Function to get URL parameters
    function getUrlParams() {
        const params = new URLSearchParams(window.location.search);
        return {
            available_page: parseInt(params.get('available_page')) || 1,
            completed_page: parseInt(params.get('completed_page')) || 1,
            pending_page: parseInt(params.get('pending_page')) || 1,
            results_page: parseInt(params.get('results_page')) || 1
        };
    }

    // Function to update pagination links
    function updatePaginationLinks() {
        const currentParams = getUrlParams();
        
        document.querySelectorAll('.pagination .page-link').forEach(link => {
            const href = new URL(link.href);
            const newParams = new URLSearchParams(href.search);
            
            // Preserve other pagination parameters when clicking a specific pagination link
            for (const [key, value] of Object.entries(currentParams)) {
                if (!newParams.has(key)) {
                    newParams.set(key, value);
                }
            }
            
            href.search = newParams.toString();
            link.href = href.toString();
        });
    }

    // Update pagination links on page load
    updatePaginationLinks();
});
</script>

<?php include '../includes/footer.php'; ?>