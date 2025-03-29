<?php
if (!isset($_SESSION)) {
    session_start();
}
require_once dirname(__DIR__, 2) . '/includes/security.php';
require_admin();
if (!isset($pageTitle)) {
    $pageTitle = 'Admin Dashboard';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - MCQ Exam' : 'MCQ Exam Admin'; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <!-- AdminLTE Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
    
    <!-- Custom styles -->
    <style>
    .accordion-button:not(.collapsed)::after {
        background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23333'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        transform: rotate(-180deg);
    }

    .accordion-button::after {
        transition: transform 0.2s ease;
    }

    .accordion-button {
        padding: 1rem;
        background-color: #f8f9fa;
    }

    .accordion-button:not(.collapsed) {
        color: #333;
        background-color: #e9ecef;
        box-shadow: none;
    }

    .accordion-button:focus {
        box-shadow: none;
        border-color: rgba(0,0,0,.125);
    }
    .content-wrapper {
        background-color: #f4f6f9;
    }
    .nav-link.active {
        font-weight: bold;
    }
    .main-sidebar .nav-link {
        padding: 0.7rem 1rem;
    }
    .question-content img,
    .option-content img,
    .explanation-content img {
        max-width: 100%;
        height: auto;
    }
    .ck-editor__editable {
        min-height: 200px;
    }
    .nav-link .fas.fa-user-circle {
        font-size: 1.2rem;
        margin-right: 0.5rem;
    }
    .dropdown-menu {
        border: 0;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
    }
    .dropdown-item {
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
    }
    .dropdown-item .fas {
        width: 1.25rem;
        margin-right: 0.5rem;
    }
    .dropdown-item:hover {
        background-color: #f8f9fa;
    }
    .dropdown-item.active {
        background-color: #007bff;
        color: white;
    }
    /* Fix AdminLTE + Bootstrap 5 compatibility issues */
    .card-title {
        margin-bottom: 0;
    }
    .btn-group-sm>.btn, .btn-sm {
        padding: 0.25rem 0.5rem;
    }
    .modal-header .btn-close {
        margin: -0.5rem -0.5rem -0.5rem auto;
    }
    .navbar-nav .nav-link {
        padding: 0.5rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    .navbar-nav .dropdown-menu {
        margin-top: 0.5rem;
        right: 0;
        left: auto;
    }
    .navbar .fa-user-circle {
        font-size: 1.25rem;
    }
    </style>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <!-- Left navbar links -->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>

        <!-- Right navbar links -->
        <ul class="navbar-nav ml-auto">
            <li class="nav-item dropdown">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center" id="navbarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle mr-2"></i> 
                    <?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="navbarDropdown">
                    <li>
                        <a class="dropdown-item" href="view_admin.php?id=<?php echo $_SESSION['user_id']; ?>">
                            <i class="fas fa-user fa-fw"></i> My Profile
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt fa-fw"></i> Logout
                        </a>
                    </li>
                </ul>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <!-- Brand Logo -->
        <a href="dashboard.php" class="brand-link">
            <i class="fas fa-graduation-cap brand-image img-circle elevation-3" style="opacity: .8"></i>
            <span class="brand-text font-weight-light">MCQ Exam System</span>
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user panel -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image">
                    <i class="fas fa-user-circle fa-2x text-info"></i>
                </div>
                <div class="info">
                    <a href="#" class="d-block"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Administrator'); ?></a>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu">
                    <li class="nav-item">
                        <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-tachometer-alt"></i>
                            <p>Dashboard</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_admins.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_admins.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-user-shield"></i>
                            <p>Admin Users</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="manage_students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_students.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-user-graduate"></i>
                            <p>Students</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="manage_certified_students.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_certified_students.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-certificate"></i>
                            <p>Certified Students</p>
                        </a>
                    </li>
                    
                    <li class="nav-item">
                        <a href="manage_exams.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_exams.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-book"></i>
                            <p>Manage Exams</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="manage_requests.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'manage_requests.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-clock"></i>
                            <p>Exam Requests</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="view_results.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'view_results.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-chart-bar"></i>
                            <p>View Results</p>
                        </a>
                    </li>
            
                    <!-- <li class="nav-item">
                        <a href="manage_questions.php" class="nav-link <?php echo $current_page === 'manage_questions.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-question-circle"></i>
                            <p>Questions</p>
                        </a>
                    </li> -->
                    <li class="nav-item">
                        <a href="manage_retakes.php" class="nav-link <?php echo $current_page === 'manage_retakes.php' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-redo"></i>
                            <p>Exam Retakes</p>
                        </a>
                    </li>
                    
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <!-- Content Header (Page header) -->
        <div class="content-header">
            <div class="container-fluid">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['success'];
                        unset($_SESSION['success']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php 
                        echo $_SESSION['error'];
                        unset($_SESSION['error']);
                        ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main content -->
        <section class="content">
            <div class="container-fluid">