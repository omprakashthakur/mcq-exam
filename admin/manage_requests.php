<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/email_notifications.php';

// Require admin authentication
require_admin();

// Process request approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $requestId = $_POST['request_id'] ?? '';
        $action = $_POST['action'] ?? '';
        $remarks = $_POST['remarks'] ?? '';

        if (empty($requestId) || empty($action)) {
            throw new Exception('Invalid request');
        }

        // Get request details
        $stmt = $pdo->prepare("
            SELECT er.*, e.id as exam_id, u.id as user_id
            FROM exam_requests er
            JOIN exam_sets e ON er.exam_set_id = e.id
            JOIN users u ON er.user_id = u.id
            WHERE er.id = ?
        ");
        $stmt->execute([$requestId]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception('Request not found');
        }

        $pdo->beginTransaction();

        if ($action === 'approve') {
            // Generate access code and URL
            $access_code = strtoupper(bin2hex(random_bytes(5)));
            $access_url = bin2hex(random_bytes(16));
            $expiry_date = date('Y-m-d H:i:s', strtotime('+7 days')); // Default 7 days

            // Create exam access
            $stmt = $pdo->prepare("
                INSERT INTO exam_access (exam_set_id, user_id, access_code, access_url, expiry_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $request['exam_id'],
                $request['user_id'],
                $access_code,
                $access_url,
                $expiry_date
            ]);

            // Update request status
            $stmt = $pdo->prepare("
                UPDATE exam_requests 
                SET status = 'approved',
                    response_date = NOW(),
                    reviewed_by = ?,
                    remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $remarks, $requestId]);

            // Get student email and exam details for notification
            $stmt = $pdo->prepare("
                SELECT u.email, e.title, e.duration_minutes 
                FROM users u 
                JOIN exam_requests er ON u.id = er.user_id
                JOIN exam_sets e ON er.exam_set_id = e.id
                WHERE er.id = ?
            ");
            $stmt->execute([$requestId]);
            $emailData = $stmt->fetch();

            // Send approval email
            send_exam_approval_email(
                $emailData['email'],
                $emailData['title'],
                $access_code,
                $access_url,
                $expiry_date,
                $emailData['duration_minutes'],
                $remarks
            );

        } else {
            // Reject request
            $stmt = $pdo->prepare("
                UPDATE exam_requests 
                SET status = 'rejected',
                    response_date = NOW(),
                    reviewed_by = ?,
                    remarks = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $remarks, $requestId]);

            // Get student email and exam title for notification
            $stmt = $pdo->prepare("
                SELECT u.email, e.title 
                FROM users u 
                JOIN exam_requests er ON u.id = er.user_id
                JOIN exam_sets e ON er.exam_set_id = e.id
                WHERE er.id = ?
            ");
            $stmt->execute([$requestId]);
            $emailData = $stmt->fetch();

            // Send rejection email
            send_exam_rejection_email(
                $emailData['email'],
                $emailData['title'],
                $remarks
            );
        }

        $pdo->commit();
        $_SESSION['success'] = "Request has been " . ($action === 'approve' ? 'approved' : 'rejected') . " successfully.";

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: manage_requests.php');
    exit();
}

// Get all exam requests with detailed information
$stmt = $pdo->prepare("
    SELECT er.*, 
           e.title as exam_title,
           u.email,
           COALESCE(sp.full_name, u.username) as student_name,
           sp.phone,
           rev.username as reviewed_by_name
    FROM exam_requests er
    JOIN exam_sets e ON er.exam_set_id = e.id
    JOIN users u ON er.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN users rev ON er.reviewed_by = rev.id
    ORDER BY 
        CASE 
            WHEN er.status = 'pending' THEN 1
            WHEN er.status = 'approved' THEN 2
            ELSE 3
        END,
        er.request_date DESC
");
$stmt->execute();
$requests = $stmt->fetchAll();

$pageTitle = 'Manage Exam Requests';
include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Exam Requests</h2>
    </div>

    <?php if (empty($requests)): ?>
        <div class="alert alert-info">No exam requests found.</div>
    <?php else: ?>
        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Request Info</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($request['student_name'] ?? $request['username']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($request['email'] ?? ''); ?></small>
                                        <?php if ($request['phone']): ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($request['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['exam_title'] ?? ''); ?></td>
                                    <td>
                                        <strong>Requested:</strong> <?php echo date('Y-m-d', strtotime($request['request_date'])); ?><br>
                                        <strong>Preferred Date:</strong> <?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?>
                                        <?php if ($request['request_reason']): ?>
                                            <br><small class="text-muted">Reason: <?php echo htmlspecialchars($request['request_reason']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $statusBadge = [
                                            'pending' => 'bg-warning',
                                            'approved' => 'bg-success',
                                            'rejected' => 'bg-danger'
                                        ][$request['status']];
                                        ?>
                                        <span class="badge <?php echo $statusBadge; ?> status-badge">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                        <?php if ($request['status'] !== 'pending'): ?>
                                            <br><small class="text-muted">
                                                by <?php echo htmlspecialchars($request['reviewed_by_name'] ?? ''); ?>
                                                <br>on <?php echo date('Y-m-d', strtotime($request['response_date'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button type="button" class="btn btn-sm btn-success mb-1"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#actionModal"
                                                    data-request-id="<?php echo $request['id']; ?>"
                                                    data-action="approve"
                                                    data-student-name="<?php echo htmlspecialchars($request['student_name'] ?? $request['username']); ?>"
                                                    data-exam-title="<?php echo htmlspecialchars($request['exam_title'] ?? ''); ?>">
                                                <i class='bx bx-check'></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#actionModal"
                                                    data-request-id="<?php echo $request['id']; ?>"
                                                    data-action="reject"
                                                    data-student-name="<?php echo htmlspecialchars($request['student_name'] ?? $request['username']); ?>"
                                                    data-exam-title="<?php echo htmlspecialchars($request['exam_title'] ?? ''); ?>">
                                                <i class='bx bx-x'></i> Reject
                                            </button>
                                        <?php else: ?>
                                            <?php if ($request['remarks']): ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="tooltip" 
                                                        title="<?php echo htmlspecialchars($request['remarks']); ?>">
                                                    View Remarks
                                                </button>
                                            <?php endif; ?>
                                            <a href="view_student.php?id=<?php echo $request['user_id']; ?>" 
                                               class="btn btn-sm btn-primary">
                                                <i class='bx bx-user'></i> View Student
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Process Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="requestId">
                    <input type="hidden" name="action" id="actionType">
                    <div id="confirmationMessage" class="alert alert-info mb-3"></div>
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                                  placeholder="Enter any additional information or instructions"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="submitButton">Process</button>
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

    // Handle action modal
    var actionModal = document.getElementById('actionModal')
    if (actionModal) {
        actionModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget
            var requestId = button.getAttribute('data-request-id')
            var action = button.getAttribute('data-action')
            var studentName = button.getAttribute('data-student-name')
            var examTitle = button.getAttribute('data-exam-title')
            
            document.getElementById('requestId').value = requestId
            document.getElementById('actionType').value = action
            document.getElementById('confirmationMessage').innerHTML = 
                `Are you sure you want to <strong>${action}</strong> the exam request for:<br>` +
                `<strong>${studentName}</strong><br>` +
                `Exam: ${examTitle}?`
            
            var submitButton = document.getElementById('submitButton')
            submitButton.className = `btn btn-${action === 'approve' ? 'success' : 'danger'}`
            submitButton.innerHTML = `<i class='bx bx-${action === 'approve' ? 'check' : 'x'}'></i> ` +
                                   `${action === 'approve' ? 'Approve' : 'Reject'} Request`
            
            // Make remarks required for rejections
            var remarksField = document.getElementById('remarks')
            remarksField.required = (action === 'reject')
            remarksField.placeholder = action === 'approve' 
                ? 'Enter any additional instructions for the student'
                : 'Please provide a reason for rejecting this request'
        })
    }
});
</script>

<?php include 'includes/footer.php'; ?>