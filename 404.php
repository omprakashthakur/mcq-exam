<?php
http_response_code(404);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Page Not Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
        }
        .error-container {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .error-code {
            font-size: 120px;
            font-weight: bold;
            color: #dc3545;
            line-height: 1;
        }
        .error-text {
            font-size: 24px;
            margin: 20px 0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">404</div>
        <div class="error-text">Page Not Found</div>
        <p class="mb-4">The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.</p>
        <?php if (isset($_SESSION['user_id'])): ?>
            <?php if ($_SESSION['role'] === 'admin'): ?>
                <a href="/admin/dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            <?php else: ?>
                <a href="/student/dashboard.php" class="btn btn-primary">Return to Dashboard</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="/login.php" class="btn btn-primary">Go to Login</a>
        <?php endif; ?>
    </div>
</body>
</html>