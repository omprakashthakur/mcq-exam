<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// Require admin authentication
require_admin();

// Get filter parameters
$exam_id = $_GET['exam_id'] ?? '';
$status = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query conditions
$conditions = ['1=1'];
$params = [];

if ($exam_id) {
    $conditions[] = 'ea.exam_set_id = ?';
    $params[] = $exam_id;
}

if ($status) {
    $conditions[] = 'ea.status = ?';
    $params[] = $status;
}

if ($date_from) {
    $conditions[] = 'DATE(ea.start_time) >= ?';
    $params[] = $date_from;
}

if ($date_to) {
    $conditions[] = 'DATE(ea.start_time) <= ?';
    $params[] = $date_to;
}

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_attempts,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_attempts,
        AVG(CASE WHEN status = 'completed' THEN score END) as avg_score,
        (COUNT(CASE WHEN status = 'completed' AND score >= 60 THEN 1 END) * 100.0 / 
         NULLIF(COUNT(CASE WHEN status = 'completed' THEN 1 END), 0)) as pass_rate
    FROM exam_attempts
");
$stats = $stmt->fetch();

// Get all exam attempts with filters
$where = implode(' AND ', $conditions);
$stmt = $pdo->prepare("
    SELECT 
        ea.*,
        e.title as exam_title,
        e.pass_percentage,
        u.username,
        u.email,
        sp.full_name
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    JOIN users u ON ea.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE {$where}
    ORDER BY ea.start_time DESC
");
$stmt->execute($params);
$attempts = $stmt->fetchAll();

// Get all exams for filter
$exams = $pdo->query("SELECT id, title FROM exam_sets ORDER BY title")->fetchAll();

$pageTitle = "Exam Results";
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Exam Results</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Exam Results</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Stats Cards -->
        <div class="row">
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-primary"><i class="fas fa-file-alt"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Total Attempts</span>
                        <span class="info-box-number"><?php echo $stats['total_attempts']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Completed Attempts</span>
                        <span class="info-box-number"><?php echo $stats['completed_attempts']; ?></span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-info"><i class="fas fa-chart-line"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Average Score</span>
                        <span class="info-box-number"><?php echo number_format($stats['avg_score'] ?? 0, 1); ?>%</span>
                    </div>
                </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-3">
                <div class="info-box">
                    <span class="info-box-icon bg-warning"><i class="fas fa-trophy"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">Pass Rate</span>
                        <span class="info-box-number"><?php echo number_format($stats['pass_rate'] ?? 0, 1); ?>%</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-header">
                <h3 class="card-title">Filters</h3>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="exam_id" class="form-label">Exam</label>
                        <select class="form-select" id="exam_id" name="exam_id">
                            <option value="">All Exams</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>" <?php echo ($exam_id == $exam['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">All Status</option>
                            <option value="completed" <?php echo ($status == 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="in_progress" <?php echo ($status == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">Date From</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">Date To</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter"></i> Apply Filters
                        </button>
                        <a href="view_results.php" class="btn btn-secondary">
                            <i class="fas fa-sync"></i> Reset
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Table -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Exam Results</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Exam</th>
                                <th>Start Time</th>
                                <th>End Time</th>
                                <th>Score</th>
                                <th>Status</th>
                                <th>Result</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($attempts)): ?>
                                <tr>
                                    <td colspan="8" class="text-center">No exam attempts found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($attempt['full_name'] ?: $attempt['username']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($attempt['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($attempt['start_time'])); ?></td>
                                        <td>
                                            <?php echo $attempt['end_time'] ? date('Y-m-d H:i', strtotime($attempt['end_time'])) : '<span class="badge bg-warning">In Progress</span>'; ?>
                                        </td>
                                        <td>
                                            <?php echo $attempt['status'] === 'completed' ? number_format($attempt['score'], 1) . '%' : '-'; ?>
                                        </td>
                                        <td>
                                            <?php if ($attempt['status'] === 'completed'): ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">In Progress</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($attempt['status'] === 'completed'): ?>
                                                <?php if ($attempt['score'] >= $attempt['pass_percentage']): ?>
                                                    <span class="badge bg-success">Pass</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Fail</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_attempt.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
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

<style>
.info-box {
    min-height: 100px;
    background: #ffffff;
    width: 100%;
    box-shadow: 0 0 1px rgba(0,0,0,.125), 0 1px 3px rgba(0,0,0,.2);
    border-radius: 0.25rem;
    margin-bottom: 1rem;
    display: flex;
}

.info-box-icon {
    width: 70px;
    font-size: 1.875rem;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #ffffff;
}

.info-box-content {
    padding: 15px 10px;
    flex: 1;
}

.info-box-text {
    display: block;
    font-size: 1rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #666;
}

.info-box-number {
    display: block;
    font-size: 1.5rem;
    font-weight: 700;
    color: #333;
}
</style>

<?php include 'includes/footer.php'; ?>
</body>
</html>