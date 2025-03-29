<?php
require_once __DIR__ . '/env.php';
loadEnv(__DIR__ . '/../.env');

// Enable error reporting based on environment
if (getenv('APP_ENV') === 'development' && getenv('APP_DEBUG') === 'true') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

ini_set('error_log', __DIR__ . '/../php_errors.log');

// Custom error handler to log all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error ($errno): $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, __DIR__ . '/../php_errors.log');
    return false; // Allow PHP's internal error handler to run as well
});

try {
    // Create PDO connection
    $dsn = sprintf(
        "mysql:host=%s;dbname=%s;charset=%s",
        getenv('DB_HOST'),
        getenv('DB_NAME'),
        getenv('DB_CHARSET')
    );
    
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASSWORD'), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die('Database connection failed. Check your configuration and try again.');
} catch (Exception $e) {
    error_log("Configuration Error: " . $e->getMessage());
    die('Configuration error: ' . $e->getMessage());
}
?>