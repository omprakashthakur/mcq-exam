<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $stmt = $pdo->prepare("
            UPDATE certified_students SET
                first_name = ?,
                last_name = ?,
                apprentice = ?,
                aws_email = ?,
                aws_password = ?,
                credly_password = ?,
                country = ?,
                address = ?,
                city = ?,
                province_state = ?,
                zip_postal_code = ?,
                phone_number = ?,
                education = ?,
                examination_center = ?,
                exam_date = ?,
                pass_fail = ?,
                congratulations_email_sent = ?,
                apprenticeship_letters_sent = ?,
                personal_email = ?,
                discount_coupon_used = ?,
                discount_coupon_code = ?,
                remarks = ?,
                student_code = ?,
                college = ?,
                booking_status = ?,
                booking_date = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            isset($_POST['apprentice']) ? 1 : 0,
            $_POST['aws_email'],
            $_POST['aws_password'],
            $_POST['credly_password'],
            $_POST['country'],
            $_POST['address'],
            $_POST['city'],
            $_POST['province_state'],
            $_POST['zip_postal_code'],
            $_POST['phone_number'],
            $_POST['education'],
            $_POST['examination_center'],
            $_POST['exam_date'] ?: null,
            $_POST['pass_fail'] ?: null,
            isset($_POST['congratulations_email_sent']) ? 1 : 0,
            isset($_POST['apprenticeship_letters_sent']) ? 1 : 0,
            $_POST['personal_email'],
            isset($_POST['discount_coupon_used']) ? 1 : 0,
            $_POST['discount_coupon_code'],
            $_POST['remarks'],
            $_POST['student_code'],
            isset($_POST['college']) ? 1 : 0,
            $_POST['booking_status'],
            $_POST['booking_date'] ?: null,
            $student_id
        ]);

        $_SESSION['success'] = 'Student information updated successfully.';
        header('Location: manage_certified_students.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = 'Error updating student: ' . $e->getMessage();
    }
}

// Get student data
$stmt = $pdo->prepare("SELECT * FROM certified_students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found';
    header('Location: manage_certified_students.php');
    exit();
}

$pageTitle = "Edit Certified Student";
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Edit Certified Student</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_certified_students.php">Certified Students</a></li>
                    <li class="breadcrumb-item active">Edit Student</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Edit Student Information</h3>
            </div>
            <form action="edit_certified_student.php?id=<?php echo $student_id; ?>" method="POST">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">First Name</label>
                                <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Last Name</label>
                                <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Student Code</label>
                                <input type="text" class="form-control" name="student_code" value="<?php echo htmlspecialchars($student['student_code']); ?>" required pattern="[A-Za-z0-9]+" title="Letters and numbers only">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">AWS Email</label>
                                <input type="email" class="form-control" name="aws_email" value="<?php echo htmlspecialchars($student['aws_email']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">AWS Password (Optional)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="aws_password" id="awsPassword" value="<?php echo htmlspecialchars($student['aws_password']); ?>">
                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('awsPassword')">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <div class="form-text w-100">Leave blank if not available</div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Credly Password (Optional)</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" name="credly_password" id="credlyPassword" value="<?php echo htmlspecialchars($student['credly_password']); ?>">
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
                                <input type="text" class="form-control" name="country" value="<?php echo htmlspecialchars($student['country']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Address</label>
                                <textarea class="form-control" name="address" rows="2"><?php echo htmlspecialchars($student['address']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" value="<?php echo htmlspecialchars($student['city']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Province/State</label>
                                <input type="text" class="form-control" name="province_state" value="<?php echo htmlspecialchars($student['province_state']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ZIP/Postal Code</label>
                                <input type="text" class="form-control" name="zip_postal_code" value="<?php echo htmlspecialchars($student['zip_postal_code']); ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone_number" value="<?php echo htmlspecialchars($student['phone_number']); ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Education</label>
                                <textarea class="form-control" name="education" rows="2"><?php echo htmlspecialchars($student['education']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Personal Email</label>
                                <input type="email" class="form-control" name="personal_email" value="<?php echo htmlspecialchars($student['personal_email']); ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Examination Center</label>
                                <input type="text" class="form-control" name="examination_center" value="<?php echo htmlspecialchars($student['examination_center']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Exam Date</label>
                                <input type="date" class="form-control" name="exam_date" value="<?php echo $student['exam_date']; ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="pass_fail">
                                    <option value="">Select Status</option>
                                    <option value="pass" <?php echo $student['pass_fail'] === 'pass' ? 'selected' : ''; ?>>Pass</option>
                                    <option value="fail" <?php echo $student['pass_fail'] === 'fail' ? 'selected' : ''; ?>>Fail</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Booking Status</label>
                                <select class="form-select" name="booking_status">
                                    <option value="pending" <?php echo $student['booking_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $student['booking_status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="cancelled" <?php echo $student['booking_status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="rescheduled" <?php echo $student['booking_status'] === 'rescheduled' ? 'selected' : ''; ?>>Rescheduled</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Booking Date</label>
                                <input type="date" class="form-control" name="booking_date" value="<?php echo $student['booking_date']; ?>">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="apprentice" value="1" <?php echo $student['apprentice'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Apprentice</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="congratulations_email_sent" value="1" <?php echo $student['congratulations_email_sent'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Congratulations Email Sent</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="apprenticeship_letters_sent" value="1" <?php echo $student['apprenticeship_letters_sent'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Apprenticeship Letters Sent</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="discount_coupon_used" value="1" id="discountCheck" <?php echo $student['discount_coupon_used'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">Discount Coupon Used</label>
                                </div>
                            </div>
                            <div class="mb-3 discount-code-field" <?php echo !$student['discount_coupon_used'] ? 'style="display: none;"' : ''; ?>>
                                <label class="form-label">Discount Coupon Code</label>
                                <input type="text" class="form-control" name="discount_coupon_code" value="<?php echo htmlspecialchars($student['discount_coupon_code']); ?>">
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="college" value="1" <?php echo $student['college'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label">College</label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Remarks</label>
                                <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($student['remarks']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <a href="manage_certified_students.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.getElementById('discountCheck')?.addEventListener('change', function() {
    const discountCodeField = document.querySelector('.discount-code-field');
    discountCodeField.style.display = this.checked ? 'block' : 'none';
});

function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
}
</script>

<?php include 'includes/footer.php'; ?>