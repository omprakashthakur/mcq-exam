<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// CSRF Token functions
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        throw new Exception('CSRF token validation failed');
    }
    return true;
}

// Input sanitization functions
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

function sanitize_array($array) {
    return array_map('sanitize_input', $array);
}

// Authentication check functions
function require_auth() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error'] = 'Please login to continue.';
        header('Location: /login.php');
        exit();
    }
}

function require_admin() {
    require_auth();
    if ($_SESSION['role'] !== 'admin') {
        $_SESSION['error'] = 'Access denied. Admin privileges required.';
        header('Location: /student/dashboard.php');
        exit();
    }
}

// Password hashing function with pepper
function hash_password($password) {
    $pepper = "your_secret_pepper_string"; // In production, this should be in a secure config file
    return password_hash($password . $pepper, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    $pepper = "your_secret_pepper_string"; // Same pepper as above
    return password_verify($password . $pepper, $hash);
}

// Session security enhancement
function regenerate_session() {
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } else if (time() - $_SESSION['created'] > 1800) { // Regenerate after 30 minutes
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// Input validation functions
function validate_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function validate_username($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

function validate_password($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

// Anti-brute force function
function check_login_attempts($username) {
    if (!isset($_SESSION['login_attempts'][$username])) {
        $_SESSION['login_attempts'][$username] = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }

    $attempts = &$_SESSION['login_attempts'][$username];
    
    // Reset attempts after 15 minutes
    if (time() - $attempts['first_attempt'] > 900) {
        $attempts['count'] = 0;
        $attempts['first_attempt'] = time();
        return true;
    }

    // Block after 5 attempts
    if ($attempts['count'] >= 5) {
        return false;
    }

    $attempts['count']++;
    return true;
}

// XSS Prevention for output
function html_escape($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Secure file upload function
function secure_file_upload($file, $allowed_types = ['jpg', 'jpeg', 'png'], $max_size = 5242880) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }

    if ($file['size'] > $max_size) {
        throw new Exception('File too large');
    }

    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension']);

    if (!in_array($extension, $allowed_types)) {
        throw new Exception('Invalid file type');
    }

    // Generate secure filename
    $new_filename = bin2hex(random_bytes(16)) . '.' . $extension;
    return $new_filename;
}

// Database input escaping (when not using prepared statements)
function escape_sql($string) {
    global $pdo;
    return $pdo->quote($string);
}

// Rate limiting function
function check_rate_limit($key, $limit = 60, $period = 60) {
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 0,
            'start_time' => time()
        ];
    }

    $rate = &$_SESSION['rate_limits'][$key];

    if (time() - $rate['start_time'] > $period) {
        $rate['count'] = 0;
        $rate['start_time'] = time();
    }

    if ($rate['count'] >= $limit) {
        return false;
    }

    $rate['count']++;
    return true;
}
?>