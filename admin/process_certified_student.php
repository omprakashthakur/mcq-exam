<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();

        // Prepare values for binding
        $apprentice = isset($_POST['apprentice']) ? 1 : 0;
        $college = isset($_POST['college']) ? 1 : 0;
        $exam_date = $_POST['exam_date'] ?: null;
        $booking_date = $_POST['booking_date'] ?: null;
        $pass_fail = $_POST['pass_fail'] ?: null;
        $discount_coupon_used = isset($_POST['discount_coupon_used']) ? 1 : 0;

        // Prepare insert statement
        $stmt = $pdo->prepare("
            INSERT INTO certified_students (
                student_code, first_name, last_name, apprentice, college,
                aws_email, aws_password, credly_password, country, address,
                city, province_state, zip_postal_code, phone_number,
                education, examination_center, exam_date, booking_status,
                booking_date, pass_fail, personal_email, discount_coupon_used,
                discount_coupon_code, remarks
            ) VALUES (
                :student_code, :first_name, :last_name, :apprentice, :college,
                :aws_email, :aws_password, :credly_password, :country, :address,
                :city, :province_state, :zip_postal_code, :phone_number,
                :education, :examination_center, :exam_date, :booking_status,
                :booking_date, :pass_fail, :personal_email, :discount_coupon_used,
                :discount_coupon_code, :remarks
            )
        ");

        // Bind parameters
        $stmt->bindParam(':student_code', $_POST['student_code']);
        $stmt->bindParam(':first_name', $_POST['first_name']);
        $stmt->bindParam(':last_name', $_POST['last_name']);
        $stmt->bindParam(':apprentice', $apprentice);
        $stmt->bindParam(':college', $college);
        $stmt->bindParam(':aws_email', $_POST['aws_email']);
        $stmt->bindParam(':aws_password', $_POST['aws_password']);
        $stmt->bindParam(':credly_password', $_POST['credly_password']);
        $stmt->bindParam(':country', $_POST['country']);
        $stmt->bindParam(':address', $_POST['address']);
        $stmt->bindParam(':city', $_POST['city']);
        $stmt->bindParam(':province_state', $_POST['province_state']);
        $stmt->bindParam(':zip_postal_code', $_POST['zip_postal_code']);
        $stmt->bindParam(':phone_number', $_POST['phone_number']);
        $stmt->bindParam(':education', $_POST['education']);
        $stmt->bindParam(':examination_center', $_POST['examination_center']);
        $stmt->bindParam(':exam_date', $exam_date);
        $stmt->bindParam(':booking_status', $_POST['booking_status']);
        $stmt->bindParam(':booking_date', $booking_date);
        $stmt->bindParam(':pass_fail', $pass_fail);
        $stmt->bindParam(':personal_email', $_POST['personal_email']);
        $stmt->bindParam(':discount_coupon_used', $discount_coupon_used);
        $stmt->bindParam(':discount_coupon_code', $_POST['discount_coupon_code']);
        $stmt->bindParam(':remarks', $_POST['remarks']);

        // Execute the statement
        $stmt->execute();

        // Commit transaction
        $pdo->commit();

        $_SESSION['success'] = 'Certified student added successfully.';
        header('Location: manage_certified_students.php');
        exit();

    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
        header('Location: manage_certified_students.php');
        exit();
    }
} else {
    header('Location: manage_certified_students.php');
    exit();
}