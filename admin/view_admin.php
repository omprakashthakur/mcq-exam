<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$admin_id = $_GET['id'] ?? 0;

// Check if current user has super admin privileges
$stmt = $pdo->prepare("
    SELECT a.is_super_admin 
    FROM admin_profiles a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$current_admin = $stmt->fetch();
$is_super_admin = $current_admin['is_super_admin'] ?? 0;

// Get admin details
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.email, u.created_at, u.is_active, u.last_login,
           a.full_name, a.is_super_admin, a.phone
    FROM users u
    LEFT JOIN admin_profiles a ON u.id = a.user_id
    WHERE u.id = ? AND u.role = 'admin'
");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

if (!$admin) {
    $_SESSION['error'] = 'Admin user not found';
    header('Location: manage_admins.php');
    exit();
}

// Get activity statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM exam_sets WHERE created_by = ?) as exams_created,
        (SELECT COUNT(*) FROM exam_requests WHERE reviewed_by = ?) as requests_handled,
        (SELECT COUNT(*) FROM admin_activity_log WHERE admin_id = ?) as total_activities
");
$stmt->execute([$admin_id, $admin_id, $admin_id]);
$stats = $stmt->fetch();

// Get recent activity
$stmt = $pdo->prepare("
    SELECT al.*, u.username
    FROM admin_activity_log al
    LEFT JOIN users u ON al.entity_id = u.id
    WHERE al.admin_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute([$admin_id]);
$activities = $stmt->fetchAll();

$pageTitle = "View Admin: " . htmlspecialchars($admin['username']);
include 'includes/header.php';
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Admin Details</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_admins.php">Administrators</a></li>
                    <li class="breadcrumb-item active">Admin Details</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <div class="row">
            <!-- Admin Info -->
            <div class="col-md-4">
                <div class="card card-primary card-outline">
                    <div class="card-body box-profile">
                        <div class="text-center">
                            <i class="fas fa-user-circle fa-5x text-primary"></i>
                        </div>

                        <h3 class="profile-username text-center"><?php echo htmlspecialchars($admin['full_name']); ?></h3>
                        <p class="text-muted text-center">
                            <?php if ($admin['is_super_admin']): ?>
                                <span class="badge bg-danger">Super Administrator</span>
                            <?php else: ?>
                                <span class="badge bg-info">Administrator</span>
                            <?php endif; ?>
                            
                            <?php if ($admin['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </p>

                        <ul class="list-group list-group-unbordered mb-3">
                            <li class="list-group-item">
                                <b>Username</b> <a class="float-right"><?php echo htmlspecialchars($admin['username']); ?></a>
                            </li>
                            <li class="list-group-item">
                                <b>Email</b> <a class="float-right"><?php echo htmlspecialchars($admin['email']); ?></a>
                            </li>
                            <?php if ($admin['phone']): ?>
                            <li class="list-group-item">
                                <b>Phone</b> <a class="float-right"><?php echo htmlspecialchars($admin['phone']); ?></a>
                            </li>
                            <?php endif; ?>
                            <li class="list-group-item">
                                <b>Joined</b> <a class="float-right"><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></a>
                            </li>
                            <?php if ($admin['last_login']): ?>
                            <li class="list-group-item">
                                <b>Last Login</b> <a class="float-right"><?php echo date('M j, Y H:i', strtotime($admin['last_login'])); ?></a>
                            </li>
                            <?php endif; ?>
                        </ul>

                        <?php if ($is_super_admin): ?>
                        <div class="d-flex justify-content-between">
                            <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" class="btn btn-warning">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <button type="button" class="btn btn-<?php echo $admin['is_active'] ? 'secondary' : 'success'; ?>" 
                                    onclick="toggleAdminStatus(<?php echo $admin['id']; ?>, <?php echo $admin['is_active'] ? '0' : '1'; ?>)">
                                <i class="fas fa-<?php echo $admin['is_active'] ? 'lock' : 'unlock'; ?>"></i> 
                                <?php echo $admin['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Activity stats -->
                <div class="card card-primary">
                    <div class="card-header">
                        <h3 class="card-title">Activity Statistics</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 text-center">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Exams Created</span>
                                        <span class="info-box-number"><?php echo $stats['exams_created']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Requests</span>
                                        <span class="info-box-number"><?php echo $stats['requests_handled']; ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-center">
                                <div class="info-box bg-light">
                                    <div class="info-box-content">
                                        <span class="info-box-text">Activities</span>
                                        <span class="info-box-number"><?php echo $stats['total_activities']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Recent Activity</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>Entity</th>
                                        <th>Details</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($activities)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">No recent activity</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($activities as $activity): ?>
                                            <tr>
                                                <td>
                                                    <?php 
                                                    $actionBadges = [
                                                        'create' => 'success',
                                                        'update' => 'info',
                                                        'delete' => 'danger',
                                                        'approve' => 'primary',
                                                        'reject' => 'warning',
                                                        'backup' => 'secondary',
                                                        'restore' => 'dark',
                                                        'share' => 'light'
                                                    ];
                                                    $badge = $actionBadges[$activity['action_type']] ?? 'info';
                                                    ?>
                                                    <span class="badge bg-<?php echo $badge; ?>">
                                                        <?php echo ucfirst($activity['action_type']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo ucfirst($activity['entity_type']); ?></td>
                                                <td><?php echo htmlspecialchars($activity['details']); ?></td>
                                                <td><?php echo date('M j, Y H:i', strtotime($activity['created_at'])); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Managed Exams -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Managed Exams</h3>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->prepare("
                            SELECT id, title, created_at, is_active
                            FROM exam_sets
                            WHERE created_by = ?
                            ORDER BY created_at DESC
                            LIMIT 5
                        ");
                        $stmt->execute([$admin_id]);
                        $exams = $stmt->fetchAll();
                        ?>

                        <?php if (empty($exams)): ?>
                            <p class="text-muted">No exams created by this administrator.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Exam Title</th>
                                            <th>Created On</th>
                                            <th>Status</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($exams as $exam): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($exam['created_at'])); ?></td>
                                                <td>
                                                    <?php if ($exam['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="view_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if ($stats['exams_created'] > 5): ?>
                                <div class="text-center mt-3">
                                    <a href="manage_exams.php?admin_id=<?php echo $admin_id; ?>" class="btn btn-sm btn-outline-primary">
                                        View All Exams (<?php echo $stats['exams_created']; ?>)
                                    </a>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" action="toggle_admin_status.php" method="POST" style="display:none;">
    <input type="hidden" id="admin_id" name="admin_id">
    <input type="hidden" id="new_status" name="new_status">
</form>

<?php include 'includes/footer.php'; ?>

<script>
function toggleAdminStatus(adminId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this admin user?')) {
        document.getElementById('admin_id').value = adminId;
        document.getElementById('new_status').value = newStatus;
        document.getElementById('toggleStatusForm').submit();
    }
}
</script>