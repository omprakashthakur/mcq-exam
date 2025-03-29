<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pageTitle)) {
    $pageTitle = 'Student Dashboard';
}

// Function to display flash messages
function display_flash_messages() {
    if (isset($_SESSION['success'])) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['success']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['success']);
    }
    
    if (isset($_SESSION['error'])) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo htmlspecialchars($_SESSION['error']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
        unset($_SESSION['error']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo $pageTitle; ?> - MCQ Exam System</title>
    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="/mcq-exam/assets/css/style.css" rel="stylesheet">
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- Custom styles -->
    <style>
    body {
        background-color: #f4f6f9;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
    }

    .navbar {
        background-color: #cce7e8 !important;
        box-shadow: 0 2px 4px rgba(0,0,0,.1);
    }

    footer {
        background-color: #cce7e8 !important;
    }

    .main-content {
        flex: 1;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        padding: 2rem 1rem;
    }

    .card {
        border-radius: 0.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,.05);
    }

    .card-header {
        background-color: #fff;
        border-bottom: 1px solid rgba(0,0,0,.125);
    }

    .table-responsive {
        margin: 0;
    }

    /* Improved table styles */
    .table {
        margin-bottom: 0;
    }

    .table th {
        background-color: #f8f9fa;
        border-bottom-width: 1px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        white-space: nowrap;
    }

    .table td {
        vertical-align: middle;
    }

    .table-hover tbody tr:hover {
        background-color: rgba(0,0,0,.02);
    }

    .text-wrap {
        word-break: break-word;
        max-width: 300px;
    }

    .badge {
        font-weight: 500;
        padding: 0.5em 0.75em;
    }

    .question-content img,
    .option-content img,
    .explanation-content img {
        max-width: 100%;
        height: auto;
    }

    .exam-card {
        transition: transform 0.2s;
    }

    .exam-card:hover {
        transform: translateY(-5px);
    }

    .navbar-brand i {
        margin-right: 8px;
    }

    .nav-link.active {
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .main-content {
            padding: 1rem;
        }
        
        .card {
            margin-bottom: 1rem;
        }

        .table td {
            min-width: 100px;
        }

        .text-wrap {
            max-width: 200px;
        }
    }

    .alert {
        margin-bottom: 1.5rem;
    }

    .modal-content {
        border-radius: 0.5rem;
    }

    /* Card and table improvements */
    .card-table {
        max-height: 600px;
        margin: 0 auto;
    }

    .table-responsive {
        max-height: 500px;
        overflow-y: auto;
    }

    .table thead th {
        position: sticky;
        top: 0;
        background: #f8f9fa;
        z-index: 1;
        border-top: none;
    }

    /* Custom scrollbar for table */
    .table-responsive::-webkit-scrollbar {
        width: 8px;
    }

    .table-responsive::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb {
        background: #888;
        border-radius: 4px;
    }

    .table-responsive::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    /* Pagination styles */
    .pagination {
        margin: 1rem 0 0 0;
    }

    .pagination .page-link {
        padding: 0.375rem 0.75rem;
        border-radius: 0.25rem;
        margin: 0 0.125rem;
    }

    .pagination .page-item.active .page-link {
        background-color: #0d6efd;
        border-color: #0d6efd;
    }

    .pagination-info {
        margin-top: 1rem;
        text-align: center;
        color: #6c757d;
    }

    /* Recent Results styles */
    .list-group-item {
        padding: 1rem 0;
        border-width: 0 0 1px 0;
    }

    .list-group-item:last-child {
        border-bottom: none;
    }

    .list-group-flush {
        max-height: none;
    }

    /* Improved pagination for smaller sections */
    .pagination-sm .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }

    .pagination-info.small {
        font-size: 0.875rem;
        color: #6c757d;
    }

    .gap-2 {
        gap: 0.5rem !important;
    }

    /* Recent Results card improvements */
    .card .list-group-item .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
        min-width: 60px;
    }

    .card .list-group-item h6 {
        font-size: 0.95rem;
        margin-bottom: 0.5rem;
        color: #2c3e50;
    }

    .card .list-group-item p {
        font-size: 0.875rem;
        margin-bottom: 0.25rem;
    }

    .card .list-group-item small {
        font-size: 0.8125rem;
    }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white sticky-top">
        <div class="container">
            <a class="navbar-brand" href="/mcq-exam/student/dashboard.php">
                <i class="fas fa-graduation-cap"></i>
                MCQ Exam
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>" 
                           href="/mcq-exam/student/dashboard.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" 
                           data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> 
                            <?php echo htmlspecialchars($_SESSION['username'] ?? 'Student'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item <?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?>" 
                                   href="/mcq-exam/student/profile.php">
                                    <i class="fas fa-user fa-fw"></i> My Profile
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item" href="/mcq-exam/auth/logout.php">
                                    <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <?php display_flash_messages(); ?>
        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">