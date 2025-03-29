<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_certified,
        SUM(CASE WHEN pass_fail = 'pass' THEN 1 ELSE 0 END) as total_passed,
        SUM(CASE WHEN pass_fail = 'fail' THEN 1 ELSE 0 END) as total_failed,
        COUNT(DISTINCT examination_center) as total_centers,
        SUM(CASE WHEN apprentice = 1 THEN 1 ELSE 0 END) as total_apprentices
    FROM certified_students
");
$stats = $stmt->fetch();

// Get certified students with pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 10;
$offset = ($page - 1) * $items_per_page;

// Additional query conditions based on filters
$where_conditions = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "pass_fail = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['booking_status']) && $_GET['booking_status'] !== '') {
    $where_conditions[] = "booking_status = ?";
    $params[] = $_GET['booking_status'];
}

if (isset($_GET['type']) && $_GET['type'] !== '') {
    switch ($_GET['type']) {
        case 'apprentice':
            $where_conditions[] = "apprentice = 1";
            break;
        case 'college':
            $where_conditions[] = "college = 1";
            break;
    }
}

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_code LIKE ? OR aws_email LIKE ? OR examination_center LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get students with filters
$stmt = $pdo->prepare("
    SELECT cs.*, u.username, u.email 
    FROM certified_students cs
    LEFT JOIN users u ON cs.user_id = u.id
    $where_clause
    ORDER BY cs.exam_date DESC, cs.created_at DESC
    LIMIT ? OFFSET ?
");

$params = array_merge($params, [$items_per_page, $offset]);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get total count with filters
$count_sql = "SELECT COUNT(*) FROM certified_students cs $where_clause";
$stmt = $pdo->prepare($count_sql);
$stmt->execute(array_slice($params, 0, -2)); // Remove limit/offset from params
$total_items = $stmt->fetchColumn();
$total_pages = ceil($total_items / $items_per_page);

$pageTitle = "Certified Students Management";
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Certified Students Management</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">Certified Students</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <!-- Statistics Cards -->
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-info">
                    <div class="inner">
                        <h3><?php echo $stats['total_certified']; ?></h3>
                        <p>Total Certified Students</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?php echo $stats['total_passed']; ?></h3>
                        <p>Passed Students</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning">
                    <div class="inner">
                        <h3><?php echo $stats['total_failed']; ?></h3>
                        <p>Failed Students</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?php echo $stats['total_apprentices']; ?></h3>
                        <p>Total Apprentices</p>
                    </div>
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All</option>
                            <option value="pass" <?php echo isset($_GET['status']) && $_GET['status'] === 'pass' ? 'selected' : ''; ?>>Pass</option>
                            <option value="fail" <?php echo isset($_GET['status']) && $_GET['status'] === 'fail' ? 'selected' : ''; ?>>Fail</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Booking Status</label>
                        <select class="form-select" name="booking_status">
                            <option value="">All</option>
                            <option value="pending" <?php echo isset($_GET['booking_status']) && $_GET['booking_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo isset($_GET['booking_status']) && $_GET['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo isset($_GET['booking_status']) && $_GET['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            <option value="rescheduled" <?php echo isset($_GET['booking_status']) && $_GET['booking_status'] === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="type">
                            <option value="">All</option>
                            <option value="apprentice" <?php echo isset($_GET['type']) && $_GET['type'] === 'apprentice' ? 'selected' : ''; ?>>Apprentice</option>
                            <option value="college" <?php echo isset($_GET['type']) && $_GET['type'] === 'college' ? 'selected' : ''; ?>>College</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>" placeholder="Search...">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="manage_certified_students.php" class="btn btn-secondary">Clear Filters</a>
                        <button type="button" class="btn btn-success float-end" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export to Excel
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Certified Students List</h3>
                <div class="card-tools">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                        <i class="fas fa-plus"></i> Add New Student
                    </button>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead>
                            <tr>
                                <th>S.N.</th>
                                <th>Name</th>
                                <th>AWS Email</th>
                                <th>Country</th>
                                <th>Examination Center</th>
                                <th>Exam Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $offset + $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['aws_email']); ?></td>
                                    <td><?php echo htmlspecialchars($student['country']); ?></td>
                                    <td><?php echo htmlspecialchars($student['examination_center']); ?></td>
                                    <td><?php echo $student['exam_date'] ? date('Y-m-d', strtotime($student['exam_date'])) : 'Not set'; ?></td>
                                    <td>
                                        <?php if ($student['pass_fail'] == 'pass'): ?>
                                            <span class="badge bg-success">Passed</span>
                                        <?php elseif ($student['pass_fail'] == 'fail'): ?>
                                            <span class="badge bg-danger">Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-sm btn-info" onclick="viewStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-warning" onclick="editStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteStudent(<?php echo $student['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($total_pages > 1): ?>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Add Student Modal -->
<div class="modal fade" id="addStudentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Certified Student</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="addStudentForm" action="process_certified_student.php" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Student Code</label>
                                <input type="text" class="form-control" name="student_code" required pattern="[A-Za-z0-9]+" title="Letters and numbers only">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">AWS Email</label>
                                <input type="email" class="form-control" name="aws_email" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">AWS Password (Optional)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="aws_password" id="awsPassword">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('awsPassword')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="form-text w-100">Leave blank if not available</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Credly Password (Optional)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="credly_password" id="credlyPassword">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('credlyPassword')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="form-text w-100">Leave blank if not available</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Province/State</label>
                                <input type="text" class="form-control" name="province_state">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ZIP/Postal Code</label>
                                <input type="text" class="form-control" name="zip_postal_code">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone_number">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Education</label>
                                <textarea class="form-control" name="education" rows="2"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Personal Email</label>
                                <input type="email" class="form-control" name="personal_email">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Examination Center</label>
                                <input type="text" class="form-control" name="examination_center" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Exam Date</label>
                                <input type="date" class="form-control" name="exam_date">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="pass_fail">
                                    <option value="">Select Status</option>
                                    <option value="pass">Pass</option>
                                    <option value="fail">Fail</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Booking Status</label>
                                <select class="form-select" name="booking_status">
                                    <option value="pending">Pending</option>
                                    <option value="confirmed">Confirmed</option>
                                    <option value="cancelled">Cancelled</option>
                                    <option value="rescheduled">Rescheduled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Booking Date</label>
                                <input type="date" class="form-control" name="booking_date">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="apprentice" value="1" id="apprenticeCheck">
                                    <label class="form-check-label" for="apprenticeCheck">
                                        Apprentice
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="college" value="1" id="collegeCheck">
                                    <label class="form-check-label" for="collegeCheck">
                                        College
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="discount_coupon_used" value="1" id="discountCheck">
                                    <label class="form-check-label" for="discountCheck">
                                        Discount Coupon Used
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3 discount-code-field" style="display: none;">
                                <label class="form-label">Discount Coupon Code</label>
                                <input type="text" class="form-control" name="discount_coupon_code">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Student</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Show/hide discount coupon code field
document.getElementById('discountCheck')?.addEventListener('change', function() {
    const discountCodeField = document.querySelector('.discount-code-field');
    discountCodeField.style.display = this.checked ? 'block' : 'none';
});

function viewStudent(id) {
    // Implement view functionality
    window.location.href = `view_certified_student.php?id=${id}`;
}

function editStudent(id) {
    // Implement edit functionality
    window.location.href = `edit_certified_student.php?id=${id}`;
}

function deleteStudent(id) {
    if (confirm('Are you sure you want to delete this student?')) {
        window.location.href = `delete_certified_student.php?id=${id}`;
    }
}

function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const button = input.nextElementSibling;
    const icon = button.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function exportToExcel() {
    // Get current filters
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status') || '';
    const bookingStatus = urlParams.get('booking_status') || '';
    const type = urlParams.get('type') || '';
    const search = urlParams.get('search') || '';

    // Redirect to export script with filters
    window.location.href = `export_certified_students.php?status=${status}&booking_status=${bookingStatus}&type=${type}&search=${search}`;
}
</script>
<?php include 'includes/footer.php'; ?>