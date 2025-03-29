<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php'; // Adding missing include

// Require admin authentication
require_admin();

// Set page title
$pageTitle = 'Admin Dashboard';

// Add any extra styles specific to dashboard
$extraStyles = '
<style>
    .stat-card {
        height: 100%;
        background: linear-gradient(135deg, var(--bg-start) 0%, var(--bg-end) 100%);
        transition: transform 0.3s;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .bg-primary.stat-card {
        --bg-start: #4e73df;
        --bg-end: #224abe;
    }
    .bg-success.stat-card {
        --bg-start: #1cc88a;
        --bg-end: #13855c;
    }
    .bg-info.stat-card {
        --bg-start: #36b9cc;
        --bg-end: #258391;
    }
    .bg-warning.stat-card {
        --bg-start: #f6c23e;
        --bg-end: #dda20a;
    }
</style>';

// Pagination settings
$items_per_page = 4;
$requests_page = isset($_GET['requests_page']) ? (int)$_GET['requests_page'] : 1;
$results_page = isset($_GET['results_page']) ? (int)$_GET['results_page'] : 1;
$students_page = isset($_GET['students_page']) ? (int)$_GET['students_page'] : 1;
$recent_students_page = isset($_GET['recent_page']) ? (int)$_GET['recent_page'] : 1;

$requests_offset = ($requests_page - 1) * $items_per_page;
$results_offset = ($results_page - 1) * $items_per_page;
$students_offset = ($students_page - 1) * $items_per_page;
$recent_offset = ($recent_students_page - 1) * $items_per_page;

// Get total counts for pagination
$stmt = $pdo->query("SELECT COUNT(*) FROM exam_requests WHERE status = 'pending'");
$total_requests = $stmt->fetchColumn();
$total_requests_pages = ceil($total_requests / $items_per_page);

$stmt = $pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status = 'completed'");
$total_results = $stmt->fetchColumn();
$total_results_pages = ceil($total_results / $items_per_page);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_students = $stmt->fetchColumn();
$total_students_pages = ceil($total_students / $items_per_page);

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'");
$total_recent = $stmt->fetchColumn();
$total_recent_pages = ceil($total_recent / $items_per_page);

// Get recent exam requests with pagination
$stmt = $pdo->prepare("
    SELECT er.*, e.title as exam_title, u.email, sp.full_name, sp.phone
    FROM exam_requests er
    JOIN exam_sets e ON er.exam_set_id = e.id
    JOIN users u ON er.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE er.status = 'pending'
    ORDER BY er.request_date DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$items_per_page, $requests_offset]);
$recent_requests = $stmt->fetchAll();

// Get student statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(DISTINCT u.id) as total_students,
        COUNT(DISTINCT ea.user_id) as students_with_exams,
        COUNT(DISTINCT CASE WHEN ea.start_time >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN ea.user_id END) as active_students
    FROM users u
    LEFT JOIN exam_attempts ea ON u.id = ea.user_id
    WHERE u.role = 'user'
");
$student_stats = $stmt->fetch();

// Get exam statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_exams,
        COUNT(CASE WHEN start_date > NOW() THEN 1 END) as upcoming_exams,
        COUNT(CASE WHEN is_public = 1 THEN 1 END) as public_exams
    FROM exam_sets
");
$exam_stats = $stmt->fetch();

// Get recent exam results with pagination
$stmt = $pdo->prepare("
    SELECT ea.*, e.title as exam_title, u.email, sp.full_name
    FROM exam_attempts ea
    JOIN exam_sets e ON ea.exam_set_id = e.id
    JOIN users u ON ea.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE ea.status = 'completed'
    ORDER BY ea.end_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$items_per_page, $results_offset]);
$recent_results = $stmt->fetchAll();

// Get students list with pagination
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.email,
        u.username,
        sp.full_name,
        sp.phone,
        COUNT(DISTINCT exa.exam_set_id) as assigned_exams,
        COUNT(DISTINCT CASE WHEN att.status = 'completed' THEN att.id END) as completed_exams,
        COUNT(DISTINCT CASE WHEN er.status = 'pending' THEN er.id END) as pending_requests,
        MAX(att.start_time) as last_exam_date,
        u.is_active
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN exam_access exa ON u.id = exa.user_id
    LEFT JOIN exam_attempts att ON u.id = att.user_id
    LEFT JOIN exam_requests er ON u.id = er.user_id
    WHERE u.role = 'user'
    GROUP BY u.id, u.email, u.username, sp.full_name, sp.phone, u.is_active
    ORDER BY sp.full_name
    LIMIT ? OFFSET ?
");
$stmt->execute([$items_per_page, $students_offset]);
$students = $stmt->fetchAll();

// Get all exams for assign exam modal
$stmt = $pdo->query("SELECT id, title FROM exam_sets ORDER BY title");
$exams = $stmt->fetchAll();

// Get recent students with pagination
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.email,
        u.is_active,
        u.created_at,
        sp.full_name,
        MAX(att.start_time) as last_activity
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN exam_attempts att ON u.id = att.user_id
    WHERE u.role = 'user'
    GROUP BY u.id, u.username, u.email, u.is_active, u.created_at, sp.full_name
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$items_per_page, $recent_offset]);
$recent_students = $stmt->fetchAll();

include 'includes/header.php';
?>

<!-- Dashboard Content -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h2>Admin Dashboard</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#selectStudentModal">
        <i class='bx bx-plus'></i> Assign New Exam
    </button>
</div>

<!-- Statistics Cards -->
<div class="row justify-content-center mb-4">
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Students</h6>
                        <h3 class="mb-0"><?php echo $student_stats['total_students']; ?></h3>
                    </div>
                    <i class='bx bxs-user stat-icon'></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Active Students</h6>
                        <h3 class="mb-0"><?php echo $student_stats['active_students']; ?></h3>
                    </div>
                    <i class='bx bxs-user-check stat-icon'></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Total Exams</h6>
                        <h3 class="mb-0"><?php echo $exam_stats['total_exams']; ?></h3>
                    </div>
                    <i class='bx bxs-file stat-icon'></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="card-title">Pending Requests</h6>
                        <h3 class="mb-0"><?php echo count($recent_requests); ?></h3>
                    </div>
                    <i class='bx bxs-time stat-icon'></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row justify-content-center">
    <!-- Recent Exam Requests -->
    <div class="col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Exam Requests</h5>
                <a href="manage_requests.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_requests)): ?>
                    <p class="text-muted">No pending requests</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Exam</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($request['full_name']); ?><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($request['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['exam_title']); ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($request['request_date'])); ?></td>
                                        <td>
                                            <a href="manage_requests.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-sm btn-primary">Review</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_requests_pages > 1): ?>
                            <div class="card-footer clearfix">
                                <ul class="pagination pagination-sm m-0 float-right">
                                    <?php if ($requests_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?requests_page=<?php echo $requests_page - 1; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>">&laquo;</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_requests_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $requests_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?requests_page=<?php echo $i; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($requests_page < $total_requests_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?requests_page=<?php echo $requests_page + 1; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>">&raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Results -->
    <div class="col-xl-6 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h5 class="mb-0">Recent Exam Results</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recent_results)): ?>
                    <p class="text-muted">No recent exam results</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Exam</th>
                                    <th>Score</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_results as $result): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['full_name']); ?></td>
                                        <td><?php echo htmlspecialchars($result['exam_title']); ?></td>
                                        <td><?php echo $result['score'] . '%'; ?></td>
                                        <td><?php echo date('Y-m-d', strtotime($result['end_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php if ($total_results_pages > 1): ?>
                            <div class="card-footer clearfix">
                                <ul class="pagination pagination-sm m-0 float-right">
                                    <?php if ($results_page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page - 1; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>">&laquo;</a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $total_results_pages; $i++): ?>
                                        <li class="page-item <?php echo $i === $results_page ? 'active' : ''; ?>">
                                            <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $i; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>"><?php echo $i; ?></a>
                                        </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($results_page < $total_results_pages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page + 1; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page; ?>">&raquo;</a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Students Overview -->
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Students Overview</h5>
                <div>
                    <input type="text" id="studentSearch" class="form-control form-control-sm" placeholder="Search students...">
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover" id="studentsTable">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Contact Info</th>
                                <th>Assigned Exams</th>
                                <th>Completed</th>
                                <th>Pending Requests</th>
                                <th>Last Activity</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?><br>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['username']); ?></small>
                                    </td>
                                    <td>
                                        <?php echo htmlspecialchars($student['email']); ?><br>
                                        <?php if ($student['phone']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($student['phone']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $student['assigned_exams']; ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-success"><?php echo $student['completed_exams']; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($student['pending_requests'] > 0): ?>
                                            <span class="badge bg-warning">
                                                <?php echo $student['pending_requests']; ?> pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($student['last_exam_date']): ?>
                                            <?php echo date('Y-m-d', strtotime($student['last_exam_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No activity</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group" aria-label="Student Actions">
                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-info" 
                                               data-bs-toggle="tooltip" 
                                               title="View Student Details">
                                                <i class="fas fa-user-circle"></i>
                                            </a>
                                            
                                            <a href="assign_exam.php?student_id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-primary" 
                                               data-bs-toggle="tooltip" 
                                               title="Assign New Exam">
                                                <i class="fas fa-file-signature"></i>
                                            </a>
                                            
                                            <a href="view_results.php?student_id=<?php echo $student['id']; ?>" 
                                               class="btn btn-sm btn-success" 
                                               data-bs-toggle="tooltip" 
                                               title="View Exam Results">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>

                                            <button type="button" 
                                                    class="btn btn-sm btn-<?php echo $student['is_active'] ? 'warning' : 'dark'; ?>"
                                                    onclick="toggleStudentStatus(<?php echo $student['id']; ?>, <?php echo $student['is_active'] ? 0 : 1; ?>)"
                                                    data-bs-toggle="tooltip"
                                                    title="<?php echo $student['is_active'] ? 'Deactivate Account' : 'Activate Account'; ?>">
                                                <i class="fas fa-<?php echo $student['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if ($total_students_pages > 1): ?>
                        <div class="card-footer clearfix">
                            <ul class="pagination pagination-sm m-0 float-right">
                                <?php if ($students_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page - 1; ?>&recent_page=<?php echo $recent_students_page; ?>">&laquo;</a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php for ($i = 1; $i <= $total_students_pages; $i++): ?>
                                    <li class="page-item <?php echo $i === $students_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $i; ?>&recent_page=<?php echo $recent_students_page; ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($students_page < $total_students_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page + 1; ?>&recent_page=<?php echo $recent_students_page; ?>">&raquo;</a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Students Overview -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Recent Students</h3>
        <div class="card-tools">
            <button type="button" class="btn btn-tool" data-card-widget="collapse">
                <i class="fas fa-minus"></i>
            </button>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Last Activity</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_students as $student): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?></td>
                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                        <td>
                            <?php if ($student['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($student['last_activity']): ?>
                                <?php echo date('M j, Y H:i', strtotime($student['last_activity'])); ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="btn-group" role="group">
                                <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                   class="btn btn-sm btn-info" 
                                   data-bs-toggle="tooltip" 
                                   title="View Student Details">
                                    <i class="fas fa-user-circle"></i>
                                </a>
                                
                                <a href="assign_exam.php?student_id=<?php echo $student['id']; ?>" 
                                   class="btn btn-sm btn-primary" 
                                   data-bs-toggle="tooltip" 
                                   title="Assign New Exam">
                                    <i class="fas fa-file-signature"></i>
                                </a>
                                
                                <a href="view_results.php?student_id=<?php echo $student['id']; ?>" 
                                   class="btn btn-sm btn-success" 
                                   data-bs-toggle="tooltip" 
                                   title="View Exam Results">
                                    <i class="fas fa-chart-bar"></i>
                                </a>

                                <button type="button" 
                                        class="btn btn-sm btn-<?php echo $student['is_active'] ? 'warning' : 'dark'; ?>"
                                        onclick="toggleStudentStatus(<?php echo $student['id']; ?>, <?php echo $student['is_active'] ? 0 : 1; ?>)"
                                        data-bs-toggle="tooltip"
                                        title="<?php echo $student['is_active'] ? 'Deactivate Account' : 'Activate Account'; ?>">
                                    <i class="fas fa-<?php echo $student['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_students)): ?>
                    <tr>
                        <td colspan="5" class="text-center">No students found</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <?php if ($total_recent_pages > 1): ?>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($recent_students_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page - 1; ?>">&laquo;</a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $total_recent_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $recent_students_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($recent_students_page < $total_recent_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?requests_page=<?php echo $requests_page; ?>&results_page=<?php echo $results_page; ?>&students_page=<?php echo $students_page; ?>&recent_page=<?php echo $recent_students_page + 1; ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-footer text-center">
         <a href="manage_students.php" class="btn btn-primary btn-sm">
                <i class="fas fa-users"></i> View All Students
            </a>
    </div>
</div>

<!-- Student Selection Modal -->
<div class="modal fade" id="selectStudentModal" tabindex="-1" aria-labelledby="selectStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="selectStudentModalLabel">Select Student for Exam Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <input type="text" id="studentSearchModal" class="form-control" placeholder="Search students...">
                </div>
                <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                    <table class="table table-hover" id="studentsModalTable">
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Email</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?></td>
                                    <td><?php echo htmlspecialchars($student['email']); ?></td>
                                    <td>
                                        <button type="button" 
                                                class="btn btn-sm btn-primary assign-exam-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#assignExamModal"
                                                data-bs-dismiss="modal"
                                                data-student-id="<?php echo $student['id']; ?>"
                                                data-student-name="<?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?>">
                                            Select
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Exam Modal -->
<div class="modal fade" id="assignExamModal" tabindex="-1" aria-labelledby="assignExamModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="assign_exam.php" method="POST">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignExamModalLabel">Assign Exam</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="student_id" id="studentId">
                    <div id="studentInfo" class="alert alert-info mb-3"></div>
                    <div class="mb-3">
                        <label for="examId" class="form-label">Select Exam</label>
                        <select class="form-select" id="examId" name="exam_id" required>
                            <option value="">Choose exam...</option>
                            <?php foreach ($exams as $exam): ?>
                                <option value="<?php echo $exam['id']; ?>">
                                    <?php echo htmlspecialchars($exam['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="expiryDays" class="form-label">Access Duration (days)</label>
                        <input type="number" class="form-control" id="expiryDays" name="expiry_days" 
                               value="7" min="1" max="30" required>
                        <div class="form-text">Number of days the student will have access to this exam.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-plus'></i> Assign Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toggle Student Status Form -->
<form id="toggleStudentForm" method="POST" action="toggle_student_status.php" style="display: none;">
    <input type="hidden" name="student_id" id="toggleStudentId">
    <input type="hidden" name="status" id="toggleStudentStatus">
</form>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle assign exam modal
    const assignExamModal = document.getElementById('assignExamModal');
    if (assignExamModal) {
        const modal = new bootstrap.Modal(assignExamModal);
        
        // Handle data when modal is opened from any button
        assignExamModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const studentId = button.getAttribute('data-student-id');
            const studentName = button.getAttribute('data-student-name');
            
            document.getElementById('studentId').value = studentId || '';
            
            const studentInfoElement = document.getElementById('studentInfo');
            if (studentId && studentName) {
                studentInfoElement.innerHTML = `Assigning exam to: <strong>${studentName}</strong>`;
                studentInfoElement.style.display = 'block';
            } else {
                studentInfoElement.innerHTML = 'Please select a student first';
                studentInfoElement.style.display = 'block';
            }
        });
    }

    // Handle student search in main table
    const studentSearch = document.getElementById('studentSearch');
    const studentsTable = document.getElementById('studentsTable');
    
    if (studentSearch && studentsTable) {
        const tableRows = studentsTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        studentSearch.addEventListener('keyup', function(e) {
            const searchText = e.target.value.toLowerCase();
            
            Array.from(tableRows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }

    // Handle student search in modal
    const studentSearchModal = document.getElementById('studentSearchModal');
    const studentsModalTable = document.getElementById('studentsModalTable');
    
    if (studentSearchModal && studentsModalTable) {
        const tableRows = studentsModalTable.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        
        studentSearchModal.addEventListener('keyup', function(e) {
            const searchText = e.target.value.toLowerCase();
            
            Array.from(tableRows).forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchText) ? '' : 'none';
            });
        });
    }
});

function toggleStudentStatus(studentId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this student account?')) {
        document.getElementById('toggleStudentId').value = studentId;
        document.getElementById('toggleStudentStatus').value = newStatus;
        document.getElementById('toggleStudentForm').submit();
    }
}
</script>

<style>
.btn-group .btn {
    padding: 0.25rem 0.5rem;
    margin: 0 1px;
}

.btn-group .btn i {
    font-size: 0.875rem;
    width: 16px;
    text-align: center;
}

.btn-info { background-color: #17a2b8; }
.btn-primary { background-color: #007bff; }
.btn-success { background-color: #28a745; }
.btn-warning { background-color: #ffc107; color: #000; }
.btn-dark { background-color: #343a40; }

.tooltip {
    font-size: 12px;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

function toggleStudentStatus(studentId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    if (confirm('Are you sure you want to ' + action + ' this student account?')) {
        document.getElementById('toggleStudentId').value = studentId;
        document.getElementById('toggleStudentStatus').value = newStatus;
        document.getElementById('toggleStudentForm').submit();
    }
}
</script>