<?php
require_once __DIR__ . '/../config/env.php';
loadEnv(__DIR__ . '/../.env');

$checks = [
    'environment' => false,
    'database' => false,
    'file_permissions' => false,
    'required_extensions' => false
];

// Check environment
$checks['environment'] = file_exists(__DIR__ . '/../.env');

// Check database connection
try {
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        getenv('DB_HOST'),
        getenv('DB_NAME'),
        getenv('DB_CHARSET')
    );
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'));
    $checks['database'] = true;
} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
}

// Check file permissions
$directories = [
    __DIR__ . '/../uploads' => 770,
    __DIR__ . '/../backups' => 770,
    __DIR__ . '/../logs' => 770
];

$checks['file_permissions'] = true;
foreach ($directories as $dir => $required_perms) {
    if (!is_dir($dir)) {
        echo "Directory missing: $dir\n";
        $checks['file_permissions'] = false;
        continue;
    }
    
    $perms = substr(sprintf('%o', fileperms($dir)), -3);
    if ($perms != $required_perms) {
        echo "Invalid permissions for $dir: $perms (should be $required_perms)\n";
        $checks['file_permissions'] = false;
    }
}

// Check PHP extensions
$required_extensions = ['pdo', 'pdo_mysql', 'mbstring', 'gd', 'xml'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (!extension_loaded($ext)) {
        $missing_extensions[] = $ext;
    }
}

$checks['required_extensions'] = empty($missing_extensions);
if (!empty($missing_extensions)) {
    echo "Missing PHP extensions: " . implode(', ', $missing_extensions) . "\n";
}

// Output results
echo "\nDeployment Validation Results:\n";
echo "===========================\n";
foreach ($checks as $check => $status) {
    echo sprintf("%-20s: %s\n", $check, $status ? '✓ PASS' : '✗ FAIL');
}

// Overall status
$deployment_ready = !in_array(false, $checks);
echo "\nDeployment Status: " . ($deployment_ready ? "READY" : "NOT READY") . "\n";

if (!$deployment_ready) {
    exit(1);
}