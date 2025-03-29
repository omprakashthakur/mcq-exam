<?php
require_once dirname(__FILE__) . '/../config/database.php';

try {
    // Create the certified_students table
    $sql = "CREATE TABLE IF NOT EXISTS certified_students (
        id INT PRIMARY KEY AUTO_INCREMENT,
        student_code VARCHAR(20),
        user_id INT,
        first_name VARCHAR(100),
        last_name VARCHAR(100),
        apprentice BOOLEAN DEFAULT 0,
        college BOOLEAN DEFAULT 0,
        aws_email VARCHAR(255),
        aws_password VARCHAR(255),
        credly_password VARCHAR(255),
        country VARCHAR(100),
        address TEXT,
        city VARCHAR(100),
        province_state VARCHAR(100),
        zip_postal_code VARCHAR(20),
        phone_number VARCHAR(50),
        education TEXT,
        examination_center VARCHAR(255),
        exam_date DATE,
        booking_status ENUM('pending', 'confirmed', 'cancelled', 'rescheduled') DEFAULT 'pending',
        booking_date DATE,
        pass_fail ENUM('pass', 'fail'),
        congratulations_email_sent BOOLEAN DEFAULT 0,
        apprenticeship_letters_sent BOOLEAN DEFAULT 0,
        personal_email VARCHAR(255),
        discount_coupon_used BOOLEAN DEFAULT 0,
        discount_coupon_code VARCHAR(50),
        remarks TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
        INDEX (user_id),
        INDEX (exam_date),
        INDEX (pass_fail),
        INDEX (student_code),
        INDEX (booking_status)
    )";

    $pdo->exec($sql);
    echo "Table certified_students created successfully\n";

} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}