<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Pagination settings
$results_per_page = 6;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($current_page - 1) * $results_per_page;

// Get total number of results
$total_query = $pdo->query("
    SELECT COUNT(*) 
    FROM exam_attempts ea
    JOIN users u ON ea.user_id = u.id
    JOIN exam_sets e ON ea.exam_set_id = e.id
");
$total_results = $total_query->fetchColumn();
$total_pages = ceil($total_results / $results_per_page);

// Fetch exam attempts with pagination
$stmt = $pdo->prepare("
    SELECT 
        ea.*,
        u.username,
        COALESCE(sp.full_name, u.username) as student_name,
        e.title as exam_title,
        e.pass_percentage,
        e.exam_set_code
    FROM exam_attempts ea
    JOIN users u ON ea.user_id = u.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    JOIN exam_sets e ON ea.exam_set_id = e.id
    ORDER BY ea.start_time DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$results_per_page, $offset]);
$attempts = $stmt->fetchAll();

$pageTitle = "View Exam Results";
include 'includes/header.php';
?>

<!-- Custom CSS for table responsiveness -->
<style>
.table-responsive {
    margin: 0;
    padding: 0;
}

.table-wrapper {
    position: relative;
    max-height: 600px;
    overflow: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.25rem;
}

.table-wrapper table {
    margin-bottom: 0;
}

.table-wrapper thead th {
    position: sticky;
    top: 0;
    background-color: #f8f9fa;
    z-index: 1;
}

.pagination-wrapper {
    margin-top: 1rem;
}

@media (max-width: 768px) {
    .table-wrapper {
        max-height: 400px;
    }
    
    .table td, .table th {
        min-width: 120px;
    }
    
    .table td:first-child, .table th:first-child {
        position: sticky;
        left: 0;
        background-color: #fff;
        z-index: 2;
    }
    
    .table thead th:first-child {
        z-index: 3;
    }
}

.score-cell {
    font-weight: bold;
    white-space: nowrap;
}

.score-pass {
    color: #28a745;
}

.score-fail {
    color: #dc3545;
}

.status-badge {
    min-width: 100px;
    display: inline-block;
    text-align: center;
}
</style>

<div class="container-fluid py-4">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Exam Results</h3>
        </div>
        <div class="card-body">
            <?php if (empty($attempts)): ?>
                <div class="alert alert-info">No exam attempts found.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <div class="table-wrapper">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Exam</th>
                                    <th>Set Code</th>
                                    <th>Start Time</th>
                                    <th>End Time</th>
                                    <th>Score</th>
                                    <th>Status</th>
                                    <th>Result</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attempts as $attempt): ?>
                                    <tr>
                                        <td>
                                            <div><?php echo htmlspecialchars($attempt['student_name']); ?></div>
                                            <small class="text-muted"><?php echo $attempt['username']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($attempt['exam_title']); ?></td>
                                        <td>
                                            <code>
                                                <?php echo $attempt['exam_set_code'] ?? 'EX' . sprintf('%04d', $attempt['exam_set_id']); ?>
                                            </code>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($attempt['start_time'])); ?></td>
                                        <td>
                                            <?php echo $attempt['end_time'] ? date('Y-m-d H:i', strtotime($attempt['end_time'])) : '-'; ?>
                                        </td>
                                        <td class="score-cell <?php echo ($attempt['score'] >= $attempt['pass_percentage']) ? 'score-pass' : 'score-fail'; ?>">
                                            <?php 
                                            if ($attempt['status'] === 'completed') {
                                                echo number_format($attempt['score'], 1) . '%';
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php
                                            $status_badges = [
                                                'in_progress' => 'warning',
                                                'completed' => 'success',
                                                'pending' => 'info'
                                            ];
                                            $badge = $status_badges[$attempt['status']] ?? 'secondary';
                                            ?>
                                            <span class="badge bg-<?php echo $badge; ?> status-badge">
                                                <?php echo ucfirst(str_replace('_', ' ', $attempt['status'])); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($attempt['status'] === 'completed'): ?>
                                                <?php if ($attempt['score'] >= $attempt['pass_percentage']): ?>
                                                    <span class="badge bg-success">Pass</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Fail</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view_attempt.php?id=<?php echo $attempt['id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination-wrapper d-flex justify-content-between align-items-center mt-3">
                            <div class="pagination-info">
                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $results_per_page, $total_results); ?> 
                                of <?php echo $total_results; ?> results
                            </div>
                            <ul class="pagination mb-0">
                                <?php if ($current_page > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page - 1; ?>">
                                            <i class="fas fa-chevron-left"></i> Previous
                                        </a>
                                    </li>
                                <?php endif; ?>

                                <?php
                                $start_page = max(1, $current_page - 2);
                                $end_page = min($total_pages, $current_page + 2);
                                
                                if ($start_page > 1) {
                                    echo '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
                                    if ($start_page > 2) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                }

                                for ($i = $start_page; $i <= $end_page; $i++) {
                                    echo '<li class="page-item' . ($i === $current_page ? ' active' : '') . '">';
                                    echo '<a class="page-link" href="?page=' . $i . '">' . $i . '</a>';
                                    echo '</li>';
                                }

                                if ($end_page < $total_pages) {
                                    if ($end_page < $total_pages - 1) {
                                        echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                    }
                                    echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . '">' . $total_pages . '</a></li>';
                                }
                                ?>

                                <?php if ($current_page < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?page=<?php echo $current_page + 1; ?>">
                                            Next <i class="fas fa-chevron-right"></i>
                                        </a>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Add shadow effect when scrolling the table
    const tableWrapper = document.querySelector('.table-wrapper');
    if (tableWrapper) {
        tableWrapper.addEventListener('scroll', function() {
            const thead = this.querySelector('thead');
            if (this.scrollTop > 0) {
                thead.classList.add('shadow');
            } else {
                thead.classList.remove('shadow');
            }
        });
    }
});
</script>

<?php include 'includes/footer.php'; ?>
</body>
</html>