<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        $remarks = filter_input(INPUT_POST, 'remarks', FILTER_UNSAFE_RAW);
        
        if (!$request_id || !in_array($action, ['approve', 'reject'])) {
            throw new Exception('Invalid request parameters');
        }

        $pdo->beginTransaction();

        // Get request details
        $stmt = $pdo->prepare("
            SELECT r.*, e.title as exam_title, u.email 
            FROM exam_requests r
            JOIN exam_sets e ON r.exam_set_id = e.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ? AND r.status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception('Request not found or already processed');
        }

        // Check for existing active exam access
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM exam_access 
            WHERE user_id = ? 
            AND exam_set_id = ? 
            AND expiry_date > NOW() 
            AND is_used = 0
        ");
        $stmt->execute([$request['user_id'], $request['exam_set_id']]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Student already has active access to this exam');
        }

        // Update request status
        $stmt = $pdo->prepare("
            UPDATE exam_requests 
            SET status = ?, 
                remarks = ?,
                reviewed_by = ?, 
                response_date = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([
            $action === 'approve' ? 'approved' : 'rejected',
            $remarks,
            $_SESSION['user_id'],
            $request_id
        ]);

        if ($action === 'approve') {
            // Generate access code and URL
            $accessCode = strtoupper(bin2hex(random_bytes(4)));
            $accessUrl = bin2hex(random_bytes(16));
            
            // Create exam access
            $stmt = $pdo->prepare("
                INSERT INTO exam_access (
                    exam_set_id,
                    user_id,
                    access_code,
                    access_url,
                    expiry_date,
                    created_by,
                    created_at
                ) VALUES (?, ?, ?, ?, 
                    DATE_ADD(NOW(), INTERVAL 7 DAY),
                    ?, NOW())
            ");
            $stmt->execute([
                $request['exam_set_id'],
                $request['user_id'],
                $accessCode,
                $accessUrl,
                $_SESSION['user_id']
            ]);
        }

        // Create notification for student
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                message,
                related_id
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $request['user_id'],
            $action === 'approve' ? 'exam_approved' : 'exam_rejected',
            $action === 'approve' 
                ? "Your exam request for {$request['exam_title']} has been approved" 
                : "Your exam request for {$request['exam_title']} has been rejected: $remarks",
            $request['exam_set_id']
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully';
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: manage_requests.php');
    exit();
}

// Get all pending requests with details
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        e.title as exam_title,
        e.duration_minutes,
        COALESCE(sp.full_name, u.username) as student_name,
        u.email as student_email,
        COUNT(DISTINCT q.id) as question_count,
        (
            SELECT COUNT(*) 
            FROM exam_attempts ea 
            WHERE ea.user_id = r.user_id 
            AND ea.exam_set_id = r.exam_set_id
        ) as previous_attempts
    FROM exam_requests r
    JOIN users u ON r.user_id = u.id
    JOIN exam_sets e ON r.exam_set_id = e.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN questions q ON e.id = q.exam_set_id
    WHERE r.status = 'pending'
    GROUP BY r.id
    ORDER BY r.request_date ASC
");
$stmt->execute();
$pending_requests = $stmt->fetchAll();

$pageTitle = "Manage Exam Requests";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Pending Exam Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($pending_requests)): ?>
                <div class="alert alert-info">No pending requests found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Set Code</th>
                                <th>Questions</th>
                                <th>Request Date</th>
                                <th>Preferred Date</th>
                                <th>Previous Attempts</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_requests as $request): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($request['student_name']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo $request['student_email']; ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($request['exam_title']); ?></td>
                                    <td><code>EX<?php echo sprintf('%04d', $request['exam_set_id']); ?></code></td>
                                    <td><?php echo $request['question_count']; ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($request['request_date'])); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($request['preferred_date'])); ?></td>
                                    <td><?php echo $request['previous_attempts']; ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-success"
                                                    onclick="showActionModal('approve', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['student_name']); ?>', '<?php echo htmlspecialchars($request['exam_title']); ?>')">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger"
                                                    onclick="showActionModal('reject', <?php echo $request['id']; ?>, '<?php echo htmlspecialchars($request['student_name']); ?>', '<?php echo htmlspecialchars($request['exam_title']); ?>')">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        </div>
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

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="actionForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Process Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="request_id">
                    <input type="hidden" name="action" id="action">
                    
                    <p id="confirmationMessage"></p>
                    
                    <div class="mb-3">
                        <label for="remarks" class="form-label">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showActionModal(action, requestId, studentName, examTitle) {
    document.getElementById('request_id').value = requestId;
    document.getElementById('action').value = action;
    
    const message = action === 'approve'
        ? `Are you sure you want to approve the exam request for ${examTitle} from ${studentName}?`
        : `Are you sure you want to reject the exam request for ${examTitle} from ${studentName}?`;
    
    document.getElementById('confirmationMessage').textContent = message;
    document.getElementById('remarks').placeholder = action === 'approve'
        ? 'Add any instructions or notes for the student...'
        : 'Please provide a reason for rejection...';
    
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>