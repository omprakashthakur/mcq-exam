<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Check if current user has admin profile
$stmt = $pdo->prepare("
    SELECT a.is_super_admin 
    FROM admin_profiles a 
    JOIN users u ON a.user_id = u.id 
    WHERE u.id = ? AND u.role = 'admin'
");
$stmt->execute([$_SESSION['user_id']]);
$admin_info = $stmt->fetch();

// If no admin profile exists for the current admin user, create one
if (!$admin_info) {
    try {
        $pdo->beginTransaction();
        
        // Create admin profile for current user
        $stmt = $pdo->prepare("
            INSERT INTO admin_profiles (user_id, full_name, is_super_admin)
            VALUES (?, 'System Administrator', 1)
        ");
        $stmt->execute([$_SESSION['user_id']]);
        
        $pdo->commit();
        $is_super_admin = 1; // First admin is super admin
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        $is_super_admin = 0;
    }
} else {
    $is_super_admin = $admin_info['is_super_admin'] ?? 0;
}

// Regular admins can view but not add/edit/delete other admins
if (!$is_super_admin && isset($_GET['action'])) {
    $_SESSION['error'] = 'You do not have permission to perform this action.';
    header('Location: manage_admins.php');
    exit();
}

// Fetch all admin users with left join to handle missing profiles
$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.created_at, 
        u.role,
        u.is_active,
        COALESCE(a.full_name, u.username) as full_name, 
        COALESCE(a.is_super_admin, 0) as is_super_admin,
        (SELECT COUNT(*) FROM exam_sets WHERE created_by = u.id) as exams_created
    FROM users u
    LEFT JOIN admin_profiles a ON u.id = a.user_id
    WHERE u.role = 'admin' AND u.id != ?
    ORDER BY u.id DESC
");
$stmt->execute([$_SESSION['user_id']]);
$admins = $stmt->fetchAll();

// Get current admin's info with left join
$stmt = $pdo->prepare("
    SELECT 
        u.id, 
        u.username, 
        u.email, 
        u.created_at,
        u.is_active,
        COALESCE(a.full_name, u.username) as full_name, 
        COALESCE(a.is_super_admin, 0) as is_super_admin
    FROM users u
    LEFT JOIN admin_profiles a ON u.id = a.user_id
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$current_admin = $stmt->fetch();

$pageTitle = "Manage Administrators";
include 'includes/header.php';
?>

<!-- Content Header (Page header) -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Manage Administrators</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Manage Administrators</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Current Admin Profile Card -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title">
                            <i class="fas fa-user-shield mr-2"></i>
                            Your Admin Profile
                        </h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 text-center">
                                <div class="mb-3">
                                    <i class="fas fa-user-circle fa-5x text-muted"></i>
                                </div>
                            </div>
                            <div class="col-md-10">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($current_admin['username']); ?></p>
                                        <p><strong>Full Name:</strong> <?php echo htmlspecialchars($current_admin['full_name']); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($current_admin['email']); ?></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Role:</strong> 
                                            <?php if ($current_admin['is_super_admin']): ?>
                                                <span class="badge bg-danger">Super Administrator</span>
                                            <?php else: ?>
                                                <span class="badge bg-info">Administrator</span>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Status:</strong>
                                            <?php if ($current_admin['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </p>
                                        <p><strong>Joined:</strong> <?php echo date('M j, Y', strtotime($current_admin['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h3 class="card-title">Admin Users</h3>
                            <?php if ($is_super_admin): ?>
                            <a href="create_admin.php" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Add New Admin
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($admins)): ?>
                            <p class="text-muted">No other administrators found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Status</th>
                                            <th>Exams Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($admins as $admin): ?>
                                            <tr>
                                                <td><?php echo $admin['id']; ?></td>
                                                <td><?php echo htmlspecialchars($admin['username']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($admin['email']); ?></td>
                                                <td>
                                                    <?php if ($admin['is_super_admin']): ?>
                                                        <span class="badge bg-danger">Super Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-info">Admin</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (isset($admin['is_active']) && $admin['is_active']): ?>
                                                        <span class="badge bg-success">Active</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary"><?php echo $admin['exams_created']; ?></span>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="view_admin.php?id=<?php echo $admin['id']; ?>" 
                                                           class="btn btn-sm btn-info" title="View Details">
                                                            <i class="fas fa-eye"></i>
                                                        </a>
                                                        <?php if ($is_super_admin): ?>
                                                        <a href="edit_admin.php?id=<?php echo $admin['id']; ?>" 
                                                           class="btn btn-sm btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-<?php echo (isset($admin['is_active']) && $admin['is_active']) ? 'secondary' : 'success'; ?>" 
                                                                onclick="toggleAdminStatus(<?php echo $admin['id']; ?>, <?php echo (isset($admin['is_active']) && $admin['is_active']) ? '0' : '1'; ?>)" 
                                                                title="<?php echo (isset($admin['is_active']) && $admin['is_active']) ? 'Deactivate' : 'Activate'; ?>">
                                                            <i class="fas fa-<?php echo (isset($admin['is_active']) && $admin['is_active']) ? 'lock' : 'unlock'; ?>"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" 
                                                                onclick="confirmDelete(<?php echo $admin['id']; ?>, '<?php echo htmlspecialchars(addslashes($admin['username'])); ?>')" 
                                                                title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                        <?php endif; ?>
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
        </div>
    </div>
</section>

<!-- Toggle Status Form (hidden) -->
<form id="toggleStatusForm" action="toggle_admin_status.php" method="POST" style="display:none;">
    <input type="hidden" id="admin_id" name="admin_id">
    <input type="hidden" id="new_status" name="new_status">
</form>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete admin user: <strong id="adminUsernameToDelete"></strong>?</p>
                <p class="text-danger">
                    <i class="fas fa-exclamation-triangle"></i> 
                    This action cannot be undone! Any exams or content created by this admin will remain.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Delete Permanently
                </a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
function toggleAdminStatus(adminId, newStatus) {
    if (confirm('Are you sure you want to ' + (newStatus ? 'activate' : 'deactivate') + ' this admin user?')) {
        document.getElementById('admin_id').value = adminId;
        document.getElementById('new_status').value = newStatus;
        document.getElementById('toggleStatusForm').submit();
    }
}

function confirmDelete(adminId, adminUsername) {
    document.getElementById('adminUsernameToDelete').textContent = adminUsername;
    document.getElementById('confirmDeleteBtn').href = `delete_admin.php?id=${adminId}`;
    
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    deleteModal.show();
}
</script>