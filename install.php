<?php
// Force PHP to display errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Initialize variables
$error = '';
$success = '';

// Check for installation lock file
$installLockFile = 'install.lock';
if (file_exists($installLockFile)) {
    die('MCQ Exam System is already installed. For security reasons, please remove install.php.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get database configuration from form
        $dbHost = $_POST['db_host'] ?? 'localhost';
        $dbName = $_POST['db_name'] ?? 'mcq_exam_db';
        $dbUser = $_POST['db_username'] ?? 'root';
        $dbPass = $_POST['db_password'] ?? '';

        // Get admin account details
        $adminUsername = $_POST['admin_username'] ?? '';
        $adminEmail = $_POST['admin_email'] ?? '';
        $adminPassword = $_POST['admin_password'] ?? '';

        // Validate input
        if (empty($adminUsername) || empty($adminEmail) || empty($adminPassword)) {
            throw new Exception('All admin account fields are required');
        }

        if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email format');
        }

        // Test database connection and create database
        $pdo = new PDO("mysql:host=$dbHost", $dbUser, $dbPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create database if not exists
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName`");
        $pdo->exec("USE `$dbName`");
        
        // Read and execute SQL schema
        $sql = file_get_contents('database/schema.sql');
        $pdo->exec($sql);

        // Create admin account
        require_once 'includes/security.php';
        $hashedPassword = hash_password($adminPassword);
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'admin')");
        $stmt->execute([$adminUsername, $adminEmail, $hashedPassword]);

        // Create database config file
        $configContent = "<?php\nreturn [\n    'host' => " . var_export($dbHost, true) . ",\n" .
                        "    'dbname' => " . var_export($dbName, true) . ",\n" .
                        "    'username' => " . var_export($dbUser, true) . ",\n" .
                        "    'password' => " . var_export($dbPass, true) . "\n];";
        
        if (!is_dir('config')) {
            mkdir('config', 0755, true);
        }
        
        file_put_contents('config/database.config.php', $configContent);
        
        // Create installation lock file
        file_put_contents($installLockFile, date('Y-m-d H:i:s'));
        
        $success = 'Installation completed successfully! Please delete install.php for security reasons.';
        
    } catch (Exception $e) {
        $error = 'Installation failed: ' . $e->getMessage();
        error_log('Installation error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Install MCQ Exam System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .install-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="install-container">
        <h2 class="text-center mb-4">MCQ Exam System Installation</h2>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
                <hr>
                <p class="mb-0">You can now <a href="login.php">login</a> with your admin account.</p>
            </div>
        <?php else: ?>
            <form method="POST" class="needs-validation" novalidate>
                <h5 class="mb-3">Database Configuration</h5>
                <div class="mb-3">
                    <label class="form-label">Database Host</label>
                    <input type="text" class="form-control" name="db_host" value="localhost" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Name</label>
                    <input type="text" class="form-control" name="db_name" value="mcq_exam_db" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Username</label>
                    <input type="text" class="form-control" name="db_username" value="root" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Database Password</label>
                    <input type="password" class="form-control" name="db_password">
                </div>

                <h5 class="mb-3 mt-4">Admin Account</h5>
                <div class="mb-3">
                    <label class="form-label">Admin Username</label>
                    <input type="text" class="form-control" name="admin_username" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Email</label>
                    <input type="email" class="form-control" name="admin_email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Admin Password</label>
                    <input type="password" class="form-control" name="admin_password" required>
                </div>

                <button type="submit" class="btn btn-primary w-100">Install System</button>
            </form>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>