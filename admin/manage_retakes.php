<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
        $action = filter_input(INPUT_POST, 'action', FILTER_UNSAFE_RAW);
        $remarks = filter_input(INPUT_POST, 'remarks', FILTER_UNSAFE_RAW);
        
        if (!$request_id || !in_array($action, ['approve', 'reject'])) {
            throw new Exception('Invalid request parameters');
        }

        $pdo->beginTransaction();

        // Get request details first
        $stmt = $pdo->prepare("
            SELECT r.*, e.title as exam_title, u.email 
            FROM exam_retake_requests r
            JOIN exam_sets e ON r.exam_set_id = e.id
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ? AND r.status = 'pending'
        ");
        $stmt->execute([$request_id]);
        $request = $stmt->fetch();

        if (!$request) {
            throw new Exception('Retake request not found or already processed');
        }

        // Update request status
        $stmt = $pdo->prepare("
            UPDATE exam_retake_requests 
            SET 
                status = ?,
                admin_remarks = ?,
                reviewed_by = ?,
                reviewed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $action === 'approve' ? 'approved' : 'rejected',
            $remarks,
            $_SESSION['user_id'],
            $request_id
        ]);

        if ($action === 'approve') {
            // Create new exam access for approved retake
            $stmt = $pdo->prepare("
                INSERT INTO exam_access (
                    user_id,
                    exam_set_id,
                    access_code,
                    expiry_date,
                    created_by,
                    is_retake
                ) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY), ?, 1)
            ");
            $stmt->execute([
                $request['user_id'],
                $request['exam_set_id'],
                strtoupper(bin2hex(random_bytes(4))),
                $_SESSION['user_id']
            ]);
        }

        // Send notification to student
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
            $action === 'approve' ? 'retake_approved' : 'retake_rejected',
            $action === 'approve' 
                ? "Your retake request for {$request['exam_title']} has been approved" 
                : "Your retake request for {$request['exam_title']} has been rejected: $remarks",
            $request['exam_set_id']
        ]);

        $pdo->commit();
        $_SESSION['success'] = 'Retake request ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully';
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: manage_retakes.php');
    exit();
}

// Get all retake requests with details
$stmt = $pdo->prepare("
    SELECT 
        r.*,
        e.title as exam_title,
        COALESCE(sp.full_name, u.username) as student_name,
        u.email as student_email,
        (SELECT MAX(score) FROM exam_attempts 
         WHERE user_id = r.user_id AND exam_set_id = r.exam_set_id) as best_score,
        a.username as reviewer_name,
        COUNT(DISTINCT q.id) as question_count
    FROM exam_retake_requests r
    JOIN users u ON r.user_id = u.id
    JOIN exam_sets e ON r.exam_set_id = e.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN users a ON r.reviewed_by = a.id
    LEFT JOIN questions q ON e.id = q.exam_set_id
    GROUP BY r.id
    ORDER BY r.request_date DESC
");
$stmt->execute();
$requests = $stmt->fetchAll();

$pageTitle = "Manage Exam Retake Requests";
include 'includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Exam Retake Requests</h3>
        </div>
        <div class="card-body">
            <?php if (empty($requests)): ?>
                <div class="alert alert-info">No retake requests found.</div>
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
                                <th>Attempt #</th>
                                <th>Best Score</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($requests as $request): ?>
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
                                    <td><?php echo $request['retake_count']; ?></td>
                                    <td><?php echo $request['best_score'] ? $request['best_score'] . '%' : 'N/A'; ?></td>
                                    <td>
                                        <?php
                                        $statusClass = [
                                            'pending' => 'warning',
                                            'approved' => 'success',
                                            'rejected' => 'danger'
                                        ][$request['status']];
                                        ?>
                                        <span class="badge bg-<?php echo $statusClass; ?>">
                                            <?php echo ucfirst($request['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($request['status'] === 'pending'): ?>
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
                                        <?php else: ?>
                                            <?php if ($request['admin_remarks']): ?>
                                                <button type="button" class="btn btn-sm btn-info" 
                                                        data-bs-toggle="tooltip" 
                                                        title="<?php echo htmlspecialchars($request['admin_remarks']); ?>">
                                                    View Remarks
                                                </button>
                                            <?php endif; ?>
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

<!-- Action Modal -->
<div class="modal fade" id="actionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="actionForm" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Process Retake Request</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="request_id" id="request_id">
                    <input type="hidden" name="action" id="action">
                    
                    <div id="confirmationMessage" class="alert alert-info mb-3"></div>
                    
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
        ? `Are you sure you want to approve the retake request for ${examTitle} from ${studentName}?`
        : `Are you sure you want to reject the retake request for ${examTitle} from ${studentName}?`;
    
    document.getElementById('confirmationMessage').textContent = message;
    document.getElementById('remarks').placeholder = action === 'approve'
        ? 'Add any instructions or notes for the student...'
        : 'Please provide a reason for rejection...';
    
    const modal = new bootstrap.Modal(document.getElementById('actionModal'));
    modal.show();
}
</script>

<?php include 'includes/footer.php'; ?>