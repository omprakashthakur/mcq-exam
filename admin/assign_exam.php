<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Get student ID from GET or POST
$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : (isset($_POST['student_id']) ? intval($_POST['student_id']) : 0);

// Get student details
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.username,
        u.email,
        u.is_active,
        COALESCE(sp.full_name, u.username) as full_name
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.id = ? AND u.role = 'user'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found or invalid student ID';
    header('Location: dashboard.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $exam_id = intval($_POST['exam_id']);
        $start_date = $_POST['start_date'];
        $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
        
        // Validate required fields
        if (empty($exam_id) || empty($start_date)) {
            throw new Exception('Please fill in all required fields');
        }

        // Check for existing active exam access
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM exam_access 
            WHERE user_id = ? 
            AND exam_set_id = ? 
            AND expiry_date > NOW() 
            AND is_used = 0
        ");
        $stmt->execute([$student_id, $exam_id]);
        if ($stmt->fetchColumn() > 0) {
            throw new Exception('Student already has active access to this exam');
        }

        // Validate dates
        $start = new DateTime($start_date);
        $current = new DateTime();
        
        if ($start < $current) {
            throw new Exception('Start date cannot be in the past');
        }

        if ($end_date) {
            $end = new DateTime($end_date);
            if ($end <= $start) {
                throw new Exception('End date must be after start date');
            }
        }

        // Check if exam exists and is active
        $stmt = $pdo->prepare("SELECT id FROM exam_sets WHERE id = ? AND is_active = 1");
        $stmt->execute([$exam_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Selected exam is not available');
        }

        // Begin transaction
        $pdo->beginTransaction();

        // Generate access code and URL
        $accessCode = strtoupper(bin2hex(random_bytes(5)));
        $accessUrl = bin2hex(random_bytes(16));
        
        // Create exam access record
        $stmt = $pdo->prepare("
            INSERT INTO exam_access (
                exam_set_id,
                user_id,
                access_code,
                access_url,
                expiry_date,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $exam_id,
            $student_id,
            $accessCode,
            $accessUrl,
            $end_date ?? date('Y-m-d H:i:s', strtotime('+7 days'))
        ]);

        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_log (
                admin_id,
                action_type,
                entity_type,
                entity_id,
                details,
                ip_address
            ) VALUES (?, 'assign', 'exam', ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            $exam_id,
            "Assigned exam to student: " . $student['username'],
            $_SERVER['REMOTE_ADDR']
        ]);

        // Send email notification to student
        $stmt = $pdo->prepare("SELECT title FROM exam_sets WHERE id = ?");
        $stmt->execute([$exam_id]);
        $exam = $stmt->fetch();

        require_once '../includes/email_notifications.php';
        send_exam_access_notification(
            $student['email'],
            $exam['title'],
            $accessCode,
            $start_date,
            $end_date
        );

        $pdo->commit();
        $_SESSION['success'] = 'Exam assigned successfully';

        // Redirect based on return_to parameter
        if (isset($_POST['return_to']) && $_POST['return_to'] === 'view_student') {
            header('Location: view_student.php?id=' . $student_id);
        } else {
            header('Location: dashboard.php');
        }
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
    }
}

// Get available exams with question count
$stmt = $pdo->prepare("
    SELECT e.*, 
           COUNT(DISTINCT q.id) as question_count
    FROM exam_sets e
    LEFT JOIN questions q ON e.id = q.exam_set_id
    WHERE e.is_active = 1
    GROUP BY e.id
    HAVING question_count > 0
    ORDER BY e.title ASC
");
$stmt->execute();
$exams = $stmt->fetchAll();

$pageTitle = "Assign Exam to " . htmlspecialchars($student['full_name']);
include 'includes/header.php';
?>

<!-- Content Header -->
<div class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1 class="m-0">Assign Exam</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="view_student.php?id=<?php echo $student_id; ?>">Student Details</a></li>
                    <li class="breadcrumb-item active">Assign Exam</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<!-- Main content -->
<section class="content">
    <div class="container-fluid">
        <!-- Student Info Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title">Student Information</h3>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Name:</strong> <?php echo htmlspecialchars($student['full_name']); ?></p>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Status:</strong>
                            <?php if ($student['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Exam Form -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Assign New Exam</h3>
            </div>
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="student_id" value="<?php echo $student_id; ?>">
                <div class="card-body">
                    <?php if (empty($exams)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No available exams to assign at this time.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="exam_id" class="form-label">Select Exam</label>
                                    <select class="form-select" id="exam_id" name="exam_id" required>
                                        <option value="">Choose an exam...</option>
                                        <?php foreach ($exams as $exam): ?>
                                            <option value="<?php echo $exam['id']; ?>">
                                                <?php echo htmlspecialchars($exam['title']); ?> 
                                                (<?php echo $exam['question_count']; ?> questions, 
                                                <?php echo $exam['duration_minutes']; ?> minutes)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select an exam</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="start_date" 
                                           name="start_date" required>
                                    <div class="invalid-feedback">Please select a start date and time</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date & Time (Optional)</label>
                                    <input type="datetime-local" class="form-control" id="end_date" 
                                           name="end_date">
                                    <div class="form-text">Leave blank for no end date</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" <?php echo empty($exams) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check"></i> Assign Exam
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            // Additional date validation
            const startDate = new Date(document.getElementById('start_date').value);
            const endDate = document.getElementById('end_date').value 
                ? new Date(document.getElementById('end_date').value)
                : null;
            
            if (endDate && startDate >= endDate) {
                event.preventDefault();
                alert('End date must be after start date');
            }
            
            if (startDate < new Date()) {
                event.preventDefault();
                alert('Start date cannot be in the past');
            }
            
            form.classList.add('was-validated');
        }, false);
    });

    // Set minimum date for date inputs to now
    const now = new Date();
    const year = now.getFullYear();
    const month = String(now.getMonth() + 1).padStart(2, '0');
    const day = String(now.getDate()).padStart(2, '0');
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    
    const minDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
    
    document.getElementById('start_date').min = minDateTime;
    document.getElementById('end_date').min = minDateTime;
});
</script>

<?php include 'includes/footer.php'; ?>