<?php
// Check for maintenance mode
if (file_exists(__DIR__ . '/../maintenance.lock') && 
    !isset($_SESSION['user_id']) || 
    (isset($_SESSION['role']) && $_SESSION['role'] !== 'admin')) {
    
    $maintenance = json_decode(file_get_contents(__DIR__ . '/../maintenance.lock'), true);
    $expectedUpTime = isset($maintenance['expected_up_time']) ? 
        date('F j, Y, g:i a', strtotime($maintenance['expected_up_time'])) : 
        'soon';
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>System Maintenance - MCQ Exam System</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <style>
            body {
                height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f8f9fa;
            }
            .maintenance-container {
                text-align: center;
                padding: 40px;
                background: white;
                border-radius: 10px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                max-width: 600px;
                margin: 20px;
            }
            .maintenance-icon {
                font-size: 64px;
                color: #ffc107;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="maintenance-container">
            <div class="maintenance-icon">🛠️</div>
            <h2>System Maintenance</h2>
            <p class="lead">We're currently performing system maintenance to improve your experience.</p>
            <p>Expected completion time: <?php echo $expectedUpTime; ?></p>
            <p class="text-muted">We apologize for any inconvenience. Please check back later.</p>
            <?php if (isset($maintenance['message'])): ?>
                <div class="alert alert-info mt-3">
                    <?php echo htmlspecialchars($maintenance['message']); ?>
                </div>
            <?php endif; ?>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>