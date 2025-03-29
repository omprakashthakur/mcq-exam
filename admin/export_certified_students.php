<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Build where clause from filters
$where_conditions = [];
$params = [];

if (isset($_GET['status']) && $_GET['status'] !== '') {
    $where_conditions[] = "pass_fail = ?";
    $params[] = $_GET['status'];
}

if (isset($_GET['booking_status']) && $_GET['booking_status'] !== '') {
    $where_conditions[] = "booking_status = ?";
    $params[] = $_GET['booking_status'];
}

if (isset($_GET['type']) && $_GET['type'] !== '') {
    switch ($_GET['type']) {
        case 'apprentice':
            $where_conditions[] = "apprentice = 1";
            break;
        case 'college':
            $where_conditions[] = "college = 1";
            break;
    }
}

if (isset($_GET['search']) && $_GET['search'] !== '') {
    $search_term = '%' . $_GET['search'] . '%';
    $where_conditions[] = "(first_name LIKE ? OR last_name LIKE ? OR student_code LIKE ? OR aws_email LIKE ? OR examination_center LIKE ?)";
    $params = array_merge($params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get filtered students
$stmt = $pdo->prepare("
    SELECT 
        student_code,
        first_name,
        last_name,
        aws_email,
        personal_email,
        phone_number,
        country,
        city,
        province_state,
        examination_center,
        exam_date,
        booking_status,
        booking_date,
        pass_fail,
        CASE WHEN apprentice = 1 THEN 'Yes' ELSE 'No' END as apprentice,
        CASE WHEN college = 1 THEN 'Yes' ELSE 'No' END as college,
        education,
        remarks,
        created_at
    FROM certified_students
    $where_clause
    ORDER BY exam_date DESC, created_at DESC
");
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="certified_students_' . date('Y-m-d_H-i-s') . '.csv"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for proper Excel reading
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add the headers
fputcsv($output, [
    'Student Code', 'First Name', 'Last Name', 'AWS Email', 'Personal Email',
    'Phone', 'Country', 'City', 'Province/State', 'Examination Center',
    'Exam Date', 'Booking Status', 'Booking Date', 'Status', 'Apprentice',
    'College', 'Education', 'Remarks', 'Created At'
]);

// Add data rows
foreach ($students as $student) {
    fputcsv($output, $student);
}

// Close the file pointer
fclose($output);