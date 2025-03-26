<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

require_auth();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    try {
        verify_csrf_token($_POST['csrf_token']);
        
        $email = sanitize_input($_POST['email']);
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (!validate_email($email)) {
            throw new Exception('Invalid email format');
        }
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!verify_password($current_password, $user['password'])) {
            throw new Exception('Current password is incorrect');
        }
        
        // Update profile
        if (!empty($new_password)) {
            if (!validate_password($new_password)) {
                throw new Exception('Password must be at least 8 characters with 1 uppercase, 1 lowercase, and 1 number');
            }
            $password_hash = hash_password($new_password);
            $stmt = $pdo->prepare("UPDATE users SET email = ?, password = ? WHERE id = ?");
            $stmt->execute([$email, $password_hash, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
        }
        
        $_SESSION['success'] = 'Profile updated successfully';
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
    
    header('Location: profile.php');
    exit();
}

// Fetch user data
$stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Fetch exam statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_exams,
        AVG(score) as average_score,
        MAX(score) as highest_score,
        COUNT(CASE WHEN score >= es.pass_percentage THEN 1 END) as exams_passed
    FROM exam_attempts ea
    JOIN exam_sets es ON ea.exam_set_id = es.id
    WHERE ea.user_id = ? AND ea.status = 'completed'
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT ea.*, es.title, es.pass_percentage
    FROM exam_attempts ea
    JOIN exam_sets es ON ea.exam_set_id = es.id
    WHERE ea.user_id = ?
    ORDER BY ea.start_time DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recent_activity = $stmt->fetchAll();

$page_title = 'My Profile';
include '../includes/header.php';
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <i class='bx bxs-user-circle' style="font-size: 6rem; color: #0d6efd;"></i>
                    </div>
                    <h4><?php echo html_escape($user['username']); ?></h4>
                    <p class="text-muted"><?php echo html_escape($user['email']); ?></p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div>Total Exams</div>
                        <div><strong><?php echo $stats['total_exams']; ?></strong></div>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <div>Average Score</div>
                        <div><strong><?php echo ($stats['average_score'] !== null) ? number_format($stats['average_score'], 1) : '0.0'; ?>%</strong></div>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <div>Highest Score</div>
                        <div><strong><?php echo ($stats['highest_score'] !== null) ? number_format($stats['highest_score'], 1) : '0.0'; ?>%</strong></div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <div>Exams Passed</div>
                        <div><strong><?php echo $stats['exams_passed']; ?></strong></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo html_escape($user['username']); ?>" disabled>
                            <div class="form-text">Username cannot be changed</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" value="<?php echo html_escape($user['email']); ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" name="new_password" minlength="8">
                            <div class="form-text">Minimum 8 characters, 1 uppercase, 1 lowercase, and 1 number</div>
                        </div>

                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_activity)): ?>
                        <p class="text-muted">No exam attempts yet</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Exam</th>
                                        <th>Date</th>
                                        <th>Score</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td><?php echo html_escape($activity['title']); ?></td>
                                            <td><?php echo date('M j, Y', strtotime($activity['start_time'])); ?></td>
                                            <td>
                                                <?php if ($activity['status'] === 'completed'): ?>
                                                    <?php 
                                                    $score = number_format($activity['score'], 1);
                                                    $badge_class = $activity['score'] >= $activity['pass_percentage'] ? 'bg-success' : 'bg-danger';
                                                    ?>
                                                    <span class="badge <?php echo $badge_class; ?>"><?php echo $score; ?>%</span>
                                                <?php else: ?>
                                                    <span class="badge bg-warning">In Progress</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($activity['status'] === 'completed'): ?>
                                                    <a href="view_result.php?attempt_id=<?php echo $activity['id']; ?>" 
                                                       class="btn btn-sm btn-primary">View Result</a>
                                                <?php else: ?>
                                                    <a href="continue_exam.php?attempt_id=<?php echo $activity['id']; ?>" 
                                                       class="btn btn-sm btn-warning">Continue</a>
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
    </div>
</div>

<?php include '../includes/footer.php'; ?>