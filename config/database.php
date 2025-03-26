<?php
// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('error_log', __DIR__ . '/../php_errors.log');

// Custom error handler to log all errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    $error_message = date('Y-m-d H:i:s') . " - Error ($errno): $errstr in $errfile on line $errline\n";
    error_log($error_message, 3, __DIR__ . '/../php_errors.log');
    return false; // Allow PHP's internal error handler to run as well
});

try {
    // Debug log for troubleshooting
    error_log("Attempting database connection...");
    
    // Load database configuration
    if (!file_exists(__DIR__ . '/database.config.php')) {
        throw new Exception('Database configuration file not found');
    }
    
    $config = require_once __DIR__ . '/database.config.php';
    
    // Create PDO connection
    $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
    
    // Log successful connection
    error_log("Database connection successful");
    
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    die('Database connection failed: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Configuration Error: " . $e->getMessage());
    die('Configuration error: ' . $e->getMessage());
}
?>