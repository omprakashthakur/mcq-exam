<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$exam_id = $_GET['id'] ?? 0;

// Pagination settings
$attempts_per_page = 4;
$access_per_page = 4;
$attempts_page = isset($_GET['attempts_page']) ? (int)$_GET['attempts_page'] : 1;
$access_page = isset($_GET['access_page']) ? (int)$_GET['access_page'] : 1;
$attempts_offset = ($attempts_page - 1) * $attempts_per_page;
$access_offset = ($access_page - 1) * $access_per_page;

// Get exam details
$stmt = $pdo->prepare("
    SELECT e.*, u.username as created_by_name
    FROM exam_sets e
    LEFT JOIN users u ON e.created_by = u.id
    WHERE e.id = ?
");
$stmt->execute([$exam_id]);
$exam = $stmt->fetch();

if (!$exam) {
    $_SESSION['error'] = 'Exam not found';
    header('Location: manage_exams.php');
    exit();
}

// Get questions count and other stats
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_questions,
        SUM(CASE WHEN question_image IS NOT NULL THEN 1 ELSE 0 END) as questions_with_images
    FROM questions
    WHERE exam_set_id = ?
");
$stmt->execute([$exam_id]);
$question_stats = $stmt->fetch();

// Get exam attempts statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ea.id) as total_attempts,
        COUNT(DISTINCT CASE WHEN ea.status = 'completed' THEN ea.id END) as completed_attempts,
        AVG(CASE WHEN ea.status = 'completed' THEN ea.score END) as avg_score,
        MIN(CASE WHEN ea.status = 'completed' THEN ea.score END) as min_score,
        MAX(CASE WHEN ea.status = 'completed' THEN ea.score END) as max_score
    FROM exam_attempts ea
    WHERE ea.exam_set_id = ?
");
$stmt->execute([$exam_id]);
$attempt_stats = $stmt->fetch();

$pageTitle = "View Exam: " . htmlspecialchars($exam['title']);
include 'includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">View Exam</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_exams.php">Exams</a></li>
                    <li class="breadcrumb-item active">View Exam</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Exam Details Card -->
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-file-alt mr-2"></i>
                            Exam Details
                        </h3>
                    </div>
                    <div class="card-body">
                        <h2 class="text-center mb-4"><?php echo htmlspecialchars($exam['title']); ?></h2>
                        
                        <div class="text-center mb-4">
                            <?php if ($exam['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                            
                            <?php if ($exam['is_public']): ?>
                                <span class="badge bg-info">Public</span>
                            <?php endif; ?>
                        </div>

                        <dl class="row">
                            <dt class="col-sm-4">Duration</dt>
                            <dd class="col-sm-8"><?php echo $exam['duration_minutes']; ?> minutes</dd>

                            <dt class="col-sm-4">Pass Score</dt>
                            <dd class="col-sm-8"><?php echo $exam['pass_percentage']; ?>%</dd>

                            <dt class="col-sm-4">Questions</dt>
                            <dd class="col-sm-8"><?php echo $question_stats['total_questions']; ?> total</dd>

                            <dt class="col-sm-4">Created By</dt>
                            <dd class="col-sm-8"><?php echo htmlspecialchars($exam['created_by_name']); ?></dd>

                            <dt class="col-sm-4">Created On</dt>
                            <dd class="col-sm-8"><?php echo date('M j, Y', strtotime($exam['created_at'])); ?></dd>
                        </dl>

                        <div class="description mt-4">
                            <h5>Description</h5>
                            <p class="text-muted"><?php echo nl2br(htmlspecialchars($exam['description'] ?? 'No description provided.')); ?></p>
                        </div>

                        <div class="mt-4">
                            <div class="btn-group w-100">
                                <a href="edit_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-warning">
                                    <i class="fas fa-edit"></i> Edit Exam
                                </a>
                                <a href="manage_questions.php?exam_id=<?php echo $exam_id; ?>" class="btn btn-primary">
                                    <i class="fas fa-list"></i> Manage Questions
                                </a>
                                <a href="share_exam.php?id=<?php echo $exam_id; ?>" class="btn btn-info">
                                    <i class="fas fa-share"></i> Share
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Statistics Cards -->
                <div class="row">
                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo $attempt_stats['total_attempts'] ?? 0; ?></h3>
                                <p>Total Attempts</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-pencil-alt"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo number_format($attempt_stats['avg_score'] ?? 0, 1); ?>%</h3>
                                <p>Average Score</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?php echo $question_stats['total_questions'] ?? 0; ?></h3>
                                <p>Questions</p>
                            </div>
                            <div class="icon">
                                <i class="fas fa-question-circle"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Attempts -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Attempts</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <?php
                            // Get total attempts count
                            $stmt = $pdo->prepare("
                                SELECT COUNT(*) as total
                                FROM exam_attempts ea
                                WHERE ea.exam_set_id = ?
                            ");
                            $stmt->execute([$exam_id]);
                            $total_attempts = $stmt->fetch()['total'];
                            $total_attempts_pages = ceil($total_attempts / $attempts_per_page);

                            // Get paginated attempts
                            $stmt = $pdo->prepare("
                                SELECT 
                                    ea.*,
                                    u.username,
                                    COALESCE(sp.full_name, u.username) as student_name
                                FROM exam_attempts ea
                                JOIN users u ON ea.user_id = u.id
                                LEFT JOIN student_profiles sp ON u.id = sp.user_id
                                WHERE ea.exam_set_id = ?
                                ORDER BY ea.start_time DESC
                                LIMIT ? OFFSET ?
                            ");
                            $stmt->execute([$exam_id, $attempts_per_page, $attempts_offset]);
                            $recent_attempts = $stmt->fetchAll();
                            ?>

                            <?php if (empty($recent_attempts)): ?>
                                <p class="text-center py-3 text-muted">No attempts recorded yet.</p>
                            <?php else: ?>
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Score</th>
                                            <th>Status</th>
                                            <th>Started</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_attempts as $attempt): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($attempt['student_name']); ?></td>
                                                <td>
                                                    <?php if ($attempt['status'] === 'completed'): ?>
                                                        <?php
                                                        $score_class = $attempt['score'] >= $exam['pass_percentage'] ? 'text-success' : 'text-danger';
                                                        ?>
                                                        <span class="<?php echo $score_class; ?> fw-bold">
                                                            <?php echo $attempt['score']; ?>%
                                                        </span>
                                                    <?php else: ?>
                                                        -
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_badges = [
                                                        'in_progress' => 'warning',
                                                        'completed' => 'success'
                                                    ];
                                                    $badge = $status_badges[$attempt['status']] ?? 'secondary';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M j, Y H:i', strtotime($attempt['start_time'])); ?></td>
                                                <td>
                                                    <a href="view_attempt.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Attempts Pagination -->
                                <?php if ($total_attempts_pages > 1): ?>
                                <div class="card-footer clearfix">
                                    <ul class="pagination pagination-sm m-0 float-right">
                                        <?php if ($attempts_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $attempts_page - 1; ?>&access_page=<?php echo $access_page; ?>">&laquo;</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_attempts_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $attempts_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $i; ?>&access_page=<?php echo $access_page; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($attempts_page < $total_attempts_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $attempts_page + 1; ?>&access_page=<?php echo $access_page; ?>">&raquo;</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Recent Access Grants -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Access Grants</h3>
                    </div>
                    <div class="card-body p-0">
                        <?php
                        // Get total access grants count
                        $stmt = $pdo->prepare("
                            SELECT COUNT(*) as total
                            FROM exam_access ea
                            WHERE ea.exam_set_id = ?
                        ");
                        $stmt->execute([$exam_id]);
                        $total_access = $stmt->fetch()['total'];
                        $total_access_pages = ceil($total_access / $access_per_page);

                        // Get paginated access grants
                        $stmt = $pdo->prepare("
                            SELECT ea.*, u.username, COALESCE(sp.full_name, u.username) as student_name
                            FROM exam_access ea
                            JOIN users u ON ea.user_id = u.id
                            LEFT JOIN student_profiles sp ON u.id = sp.user_id
                            WHERE ea.exam_set_id = ?
                            ORDER BY ea.created_at DESC
                            LIMIT ? OFFSET ?
                        ");
                        $stmt->execute([$exam_id, $access_per_page, $access_offset]);
                        $access_grants = $stmt->fetchAll();
                        ?>

                        <?php if (empty($access_grants)): ?>
                            <p class="text-center py-3 text-muted">No access grants found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student</th>
                                            <th>Access Code</th>
                                            <th>Status</th>
                                            <th>Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($access_grants as $access): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($access['student_name']); ?></td>
                                                <td><code><?php echo $access['access_code']; ?></code></td>
                                                <td>
                                                    <?php if ($access['is_used']): ?>
                                                        <span class="badge bg-secondary">Used</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Available</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $expiry = new DateTime($access['expiry_date']);
                                                    $now = new DateTime();
                                                    if ($expiry < $now) {
                                                        echo '<span class="text-danger">Expired</span>';
                                                    } else {
                                                        echo date('M j, Y H:i', strtotime($access['expiry_date']));
                                                    }
                                                    ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <!-- Access Grants Pagination -->
                                <?php if ($total_access_pages > 1): ?>
                                <div class="card-footer clearfix">
                                    <ul class="pagination pagination-sm m-0 float-right">
                                        <?php if ($access_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $attempts_page; ?>&access_page=<?php echo $access_page - 1; ?>">&laquo;</a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = 1; $i <= $total_access_pages; $i++): ?>
                                            <li class="page-item <?php echo $i === $access_page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $attempts_page; ?>&access_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($access_page < $total_access_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?php echo $exam_id; ?>&attempts_page=<?php echo $attempts_page; ?>&access_page=<?php echo $access_page + 1; ?>">&raquo;</a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Exam Details</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p><strong>Title:</strong> <?php echo htmlspecialchars($exam['title']); ?></p>
                                <p><strong>Set Code:</strong> 
                                    <?php if ($exam['exam_set_code']): ?>
                                        <code><?php echo htmlspecialchars($exam['exam_set_code']); ?></code>
                                    <?php else: ?>
                                        <code>EX<?php echo sprintf('%04d', $exam['id']); ?></code>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add hover effect for small-box components
    document.querySelectorAll('.small-box').forEach(box => {
        box.addEventListener('mouseover', function() {
            this.querySelector('.icon').style.transform = 'scale(1.1)';
        });
        box.addEventListener('mouseout', function() {
            this.querySelector('.icon').style.transform = 'scale(1)';
        });
    });

    // Auto-hide alerts after 3 seconds
    document.querySelectorAll('.alert:not(.alert-important)').forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, 3000);
    });
});
</script>

<?php include 'includes/footer.php'; ?>