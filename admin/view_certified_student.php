<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get student details
$stmt = $pdo->prepare("
    SELECT cs.*, u.username, u.email
    FROM certified_students cs
    LEFT JOIN users u ON cs.user_id = u.id
    WHERE cs.id = ?
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found';
    header('Location: manage_certified_students.php');
    exit();
}

$pageTitle = "View Certified Student";
include 'includes/header.php';
?>

<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">View Certified Student</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="manage_certified_students.php">Certified Students</a></li>
                    <li class="breadcrumb-item active">View Student</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">Student Information</h3>
                        <div class="card-tools">
                            <a href="edit_certified_student.php?id=<?php echo $student_id; ?>" class="btn btn-warning btn-sm">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="manage_certified_students.php" class="btn btn-secondary btn-sm">
                                <i class="fas fa-arrow-left"></i> Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200">Name</th>
                                        <td><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>AWS Email</th>
                                        <td><?php echo htmlspecialchars($student['aws_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Personal Email</th>
                                        <td><?php echo htmlspecialchars($student['personal_email']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Phone Number</th>
                                        <td><?php echo htmlspecialchars($student['phone_number']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Address</th>
                                        <td>
                                            <?php echo htmlspecialchars($student['address']); ?><br>
                                            <?php echo htmlspecialchars($student['city']); ?>, 
                                            <?php echo htmlspecialchars($student['province_state']); ?><br>
                                            <?php echo htmlspecialchars($student['zip_postal_code']); ?><br>
                                            <?php echo htmlspecialchars($student['country']); ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200">Examination Center</th>
                                        <td><?php echo htmlspecialchars($student['examination_center']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Exam Date</th>
                                        <td><?php echo $student['exam_date'] ? date('Y-m-d', strtotime($student['exam_date'])) : 'Not set'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Status</th>
                                        <td>
                                            <?php if ($student['pass_fail'] == 'pass'): ?>
                                                <span class="badge bg-success">Passed</span>
                                            <?php elseif ($student['pass_fail'] == 'fail'): ?>
                                                <span class="badge bg-danger">Failed</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Education</th>
                                        <td><?php echo nl2br(htmlspecialchars($student['education'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Apprentice</th>
                                        <td><?php echo $student['apprentice'] ? 'Yes' : 'No'; ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>

                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h4>Additional Information</h4>
                                <table class="table table-bordered">
                                    <tr>
                                        <th width="200">Congratulations Email</th>
                                        <td><?php echo $student['congratulations_email_sent'] ? 'Sent' : 'Not sent'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Apprenticeship Letters</th>
                                        <td><?php echo $student['apprenticeship_letters_sent'] ? 'Sent' : 'Not sent'; ?></td>
                                    </tr>
                                    <tr>
                                        <th>Discount Coupon</th>
                                        <td>
                                            <?php if ($student['discount_coupon_used']): ?>
                                                Yes (Code: <?php echo htmlspecialchars($student['discount_coupon_code']); ?>)
                                            <?php else: ?>
                                                No
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Remarks</th>
                                        <td><?php echo nl2br(htmlspecialchars($student['remarks'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>