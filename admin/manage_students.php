<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Handle filter parameter
$filter = $_GET['filter'] ?? '';
$where_clause = "WHERE u.role = 'user'";

switch ($filter) {
    case 'active':
        $where_clause .= " AND u.is_active = 1";
        break;
    case 'pending':
        $where_clause .= " AND EXISTS (SELECT 1 FROM exam_requests er WHERE er.user_id = u.id AND er.status = 'pending')";
        break;
    case 'recent':
        // Will use last_activity in ORDER BY
        break;
}

// Get students list with detailed information
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.email,
        u.is_active,
        u.created_at,
        sp.full_name,
        sp.phone,
        COUNT(DISTINCT ea.exam_set_id) as assigned_exams,
        COUNT(DISTINCT CASE WHEN att.status = 'completed' THEN att.id END) as completed_exams,
        COUNT(DISTINCT CASE WHEN er.status = 'pending' THEN er.id END) as pending_requests,
        MAX(att.start_time) as last_activity
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN exam_access ea ON u.id = ea.user_id
    LEFT JOIN exam_attempts att ON u.id = att.user_id
    LEFT JOIN exam_requests er ON u.id = er.user_id
    {$where_clause}
    GROUP BY u.id, u.username, u.email, u.is_active, u.created_at, sp.full_name, sp.phone
    ORDER BY " . ($filter === 'recent' ? "COALESCE(MAX(att.start_time), u.created_at) DESC" : "u.created_at DESC")
);
$stmt->execute();
$students = $stmt->fetchAll();

$pageTitle = "Manage Students" . ($filter ? " - " . ucfirst($filter) : "");
include 'includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Students</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Students</li>
                </ol>
            </div>
        </div>
        <?php if ($filter): ?>
        <div class="row mt-2">
            <div class="col-12">
                <div class="alert alert-info">
                    <i class="fas fa-filter"></i> 
                    Showing <?php echo ucfirst($filter); ?> students
                    <a href="manage_students.php" class="float-end text-info">
                        <i class="fas fa-times"></i> Clear filter
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">Manage Students</h1>
        <div class="btn-group">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                <i class="fas fa-filter"></i> Filter
            </button>
            <button type="button" class="btn btn-success" onclick="exportToCSV()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>

    <!-- Quick Stats Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-uppercase mb-1">Total Students</h6>
                            <h2><?php echo count($students); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-users fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-uppercase mb-1">Active Students</h6>
                            <h2><?php echo count(array_filter($students, fn($s) => $s['is_active'])); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-user-check fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-uppercase mb-1">With Exams</h6>
                            <h2><?php echo count(array_filter($students, fn($s) => $s['assigned_exams'] > 0)); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-file-alt fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-3 col-md-6">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-uppercase mb-1">Pending Requests</h6>
                            <h2><?php echo array_sum(array_column($students, 'pending_requests')); ?></h2>
                        </div>
                        <div class="icon">
                            <i class="fas fa-clock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Student List</h5>
            <div class="btn-group">
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <button type="button" class="btn btn-success" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover" id="studentsTable">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Contact</th>
                            <th>Status</th>
                            <th>Exams</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr data-student-id="<?php echo $student['id']; ?>" data-status="<?php echo $student['is_active'] ? 'active' : 'inactive'; ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-3">
                                            <i class="fas fa-user-circle fa-2x text-primary"></i>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($student['full_name'] ?: $student['username']); ?></div>
                                            <small class="text-muted">Joined: <?php echo date('M j, Y', strtotime($student['created_at'])); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($student['email']); ?></div>
                                    <?php if ($student['phone']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($student['phone']); ?></small>
                                    <?php endif; ?>
                                </td>
                                <td class="status-cell">
                                    <?php if ($student['is_active']): ?>
                                        <span class="badge bg-success status-badge">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary status-badge">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="d-flex gap-2">
                                        <span class="badge bg-primary" title="Assigned">
                                            <?php echo $student['assigned_exams']; ?>
                                        </span>
                                        <span class="badge bg-success" title="Completed">
                                            <?php echo $student['completed_exams']; ?>
                                        </span>
                                        <?php if ($student['pending_requests']): ?>
                                            <span class="badge bg-warning" title="Pending Requests">
                                                <?php echo $student['pending_requests']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($student['last_activity']): ?>
                                        <?php echo date('M j, Y H:i', strtotime($student['last_activity'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Never</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                           class="btn btn-sm btn-info" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <button type="button" 
                                                class="btn btn-sm status-toggle-btn <?php echo $student['is_active'] ? 'btn-warning' : 'btn-success'; ?>"
                                                onclick="toggleStudentStatus(<?php echo $student['id']; ?>, <?php echo $student['is_active'] ? 0 : 1; ?>)"
                                                title="<?php echo $student['is_active'] ? 'Deactivate' : 'Activate'; ?> Account">
                                            <i class="fas fa-<?php echo $student['is_active'] ? 'user-slash' : 'user-check'; ?>"></i>
                                        </button>

                                        <a href="assign_exam.php?student_id=<?php echo $student['id']; ?>" 
                                           class="btn btn-sm btn-primary" title="Assign Exam">
                                            <i class="fas fa-file-signature"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Filter Students</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Status</label>
                    <select class="form-select" id="statusFilter">
                        <option value="">All</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Exam Status</label>
                    <select class="form-select" id="examFilter">
                        <option value="">All</option>
                        <option value="with-exams">With Assigned Exams</option>
                        <option value="no-exams">No Assigned Exams</option>
                        <option value="completed">Completed Exams</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchFilter" placeholder="Name, email, or phone...">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="applyFilters()">Apply Filters</button>
            </div>
        </div>
    </div>
</div>

<!-- Toggle Status Form -->
<form id="toggleStatusForm" method="POST" action="toggle_student_status.php" style="display: none;">
    <input type="hidden" name="student_id" id="toggleStudentId">
    <input type="hidden" name="status" id="toggleStudentStatus">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
</form>

<script>
function toggleStudentStatus(studentId, newStatus) {
    const action = newStatus ? 'activate' : 'deactivate';
    const message = `Are you sure you want to ${action} this student account?`;
    
    if (confirm(message)) {
        const form = document.getElementById('toggleStatusForm');
        document.getElementById('toggleStudentId').value = studentId;
        document.getElementById('toggleStudentStatus').value = newStatus;
        
        // Submit form
        form.submit();
    }
}

// Status filter functionality
document.getElementById('statusFilter')?.addEventListener('change', function(e) {
    const status = e.target.value;
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    
    rows.forEach(row => {
        const rowStatus = row.dataset.status;
        if (!status || rowStatus === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

// Search functionality
document.getElementById('searchFilter')?.addEventListener('keyup', function(e) {
    const searchTerm = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
});

// Success/Error message auto-hide
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, 3000);
    });
});

function applyFilters() {
    const status = document.getElementById('statusFilter').value;
    const examStatus = document.getElementById('examFilter').value;
    const search = document.getElementById('searchFilter').value.toLowerCase();
    
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    rows.forEach(row => {
        let show = true;
        
        // Status filter
        if (status) {
            const isActive = row.querySelector('.badge').classList.contains('bg-success');
            if ((status === 'active' && !isActive) || (status === 'inactive' && isActive)) {
                show = false;
            }
        }
        
        // Exam status filter
        if (examStatus && show) {
            const assignedExams = parseInt(row.querySelector('.badge.bg-primary').textContent);
            const completedExams = parseInt(row.querySelector('.badge.bg-success').textContent);
            
            switch (examStatus) {
                case 'with-exams':
                    show = assignedExams > 0;
                    break;
                case 'no-exams':
                    show = assignedExams === 0;
                    break;
                case 'completed':
                    show = completedExams > 0;
                    break;
            }
        }
        
        // Search filter
        if (search && show) {
            const text = row.textContent.toLowerCase();
            show = text.includes(search);
        }
        
        row.style.display = show ? '' : 'none';
    });
    
    // Close modal
    document.querySelector('#filterModal .btn-close').click();
}

function exportToCSV() {
    let csv = 'Name,Email,Phone,Status,Assigned Exams,Completed Exams,Last Activity\n';
    
    const rows = document.querySelectorAll('#studentsTable tbody tr:not([style*="display: none"])');
    rows.forEach(row => {
        const name = row.querySelector('.fw-bold').textContent;
        const email = row.querySelector('td:nth-child(2) div').textContent;
        const phone = row.querySelector('td:nth-child(2) small')?.textContent || '';
        const status = row.querySelector('.badge').textContent;
        const assignedExams = row.querySelector('.badge.bg-primary').textContent;
        const completedExams = row.querySelector('.badge.bg-success').textContent;
        const lastActivity = row.querySelector('td:nth-child(5)').textContent.trim();
        
        csv += `"${name}","${email}","${phone}","${status}","${assignedExams}","${completedExams}","${lastActivity}"\n`;
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students-report.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}
</script>

<?php include 'includes/footer.php'; ?>