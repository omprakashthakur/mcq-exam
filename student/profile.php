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

// Pagination settings for recent activity
$items_per_page = 5;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

// Get total count of activities
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total
    FROM exam_attempts ea
    JOIN exam_sets es ON ea.exam_set_id = es.id
    WHERE ea.user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$total_items = $stmt->fetch()['total'];
$total_pages = ceil($total_items / $items_per_page);

// Get paginated recent activity
$offset = ($current_page - 1) * $items_per_page;
$stmt = $pdo->prepare("
    SELECT ea.*, es.title, es.pass_percentage
    FROM exam_attempts ea
    JOIN exam_sets es ON ea.exam_set_id = es.id
    WHERE ea.user_id = ?
    ORDER BY ea.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$_SESSION['user_id'], $items_per_page, $offset]);
$recent_activity = $stmt->fetchAll();

$pageTitle = 'My Profile';
include '../includes/header.php';
?>

<div class="container-fluid px-0">
    <div class="row g-4">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body text-center py-4">
                    <div class="mb-4">
                        <i class="fas fa-user-circle" style="font-size: 6rem; color: #0d6efd;"></i>
                    </div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($user['username']); ?></h4>
                    <p class="text-muted mb-0"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>
            </div>

            <div class="card">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Statistics</h5>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div class="text-muted">Total Exams</div>
                        <div class="h5 mb-0"><?php echo $stats['total_exams']; ?></div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div class="text-muted">Average Score</div>
                        <div class="h5 mb-0"><?php echo ($stats['average_score'] !== null) ? number_format($stats['average_score'], 1) : '0.0'; ?>%</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div class="text-muted">Highest Score</div>
                        <div class="h5 mb-0"><?php echo ($stats['highest_score'] !== null) ? number_format($stats['highest_score'], 1) : '0.0'; ?>%</div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center py-2">
                        <div class="text-muted">Exams Passed</div>
                        <div class="h5 mb-0"><?php echo $stats['exams_passed']; ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Update Profile</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Username</label>
                                <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                                <div class="form-text">Username cannot be changed</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>

                            <div class="col-12">
                                <label class="form-label">New Password (leave blank to keep current)</label>
                                <input type="password" class="form-control" name="new_password" minlength="8">
                                <div class="form-text">Minimum 8 characters, 1 uppercase, 1 lowercase, and 1 number</div>
                            </div>

                            <div class="col-12">
                                <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card card-table">
                <div class="card-header py-3">
                    <h5 class="card-title mb-0">Recent Activity</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recent_activity)): ?>
                        <div class="p-4 text-center">
                            <p class="text-muted mb-0">No exam attempts yet</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr class="text-center">
                                        <th style="width: 40%">Exam</th>
                                        <th style="width: 20%">Date</th>
                                        <th style="width: 20%">Score</th>
                                        <th style="width: 20%">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <tr>
                                            <td>
                                                <div class="text-wrap"><?php echo htmlspecialchars($activity['title']); ?></div>
                                            </td>
                                            <td class="text-center">
                                                <?php echo date('M j, Y', strtotime($activity['start_time'])); ?>
                                            </td>
                                            <td class="text-center">
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
                                            <td class="text-center">
                                                <?php if ($activity['status'] === 'completed'): ?>
                                                    <a href="view_result.php?attempt_id=<?php echo $activity['id']; ?>" 
                                                       class="btn btn-sm btn-primary">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                <?php else: ?>
                                                    <a href="continue_exam.php?attempt_id=<?php echo $activity['id']; ?>" 
                                                       class="btn btn-sm btn-warning">
                                                        <i class="fas fa-play"></i> Continue
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer bg-transparent">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div class="pagination-info">
                                        Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> 
                                        of <?php echo $total_items; ?> entries
                                    </div>
                                    <nav aria-label="Activity pagination">
                                        <ul class="pagination mb-0">
                                            <?php if ($current_page > 1): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($current_page - 1); ?>" aria-label="Previous">
                                                        <span aria-hidden="true">&laquo;</span>
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            
                                            <?php
                                            $start_page = max(1, $current_page - 2);
                                            $end_page = min($total_pages, $current_page + 2);
                                            
                                            if ($start_page > 1) {
                                                echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                                if ($start_page > 2) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                            }
                                            
                                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                                <li class="page-item <?php echo ($i === $current_page ? 'active' : ''); ?>">
                                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                                </li>
                                            <?php endfor;
                                            
                                            if ($end_page < $total_pages) {
                                                if ($end_page < $total_pages - 1) {
                                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                                }
                                                echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                            }
                                            ?>
                                            
                                            <?php if ($current_page < $total_pages): ?>
                                                <li class="page-item">
                                                    <a class="page-link" href="?page=<?php echo ($current_page + 1); ?>" aria-label="Next">
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

<?php include '../includes/footer.php'; ?>