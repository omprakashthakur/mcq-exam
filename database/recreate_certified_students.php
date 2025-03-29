<?php
require_once dirname(__FILE__) . '/../config/database.php';

try {
    // Drop and recreate the table to ensure correct structure
    $pdo->exec("DROP TABLE IF EXISTS certified_students");
    
    $sql = "CREATE TABLE certified_students (
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
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    
    $pdo->exec($sql);
    
    // Add indexes
    $pdo->exec("CREATE INDEX idx_user_id ON certified_students(user_id)");
    $pdo->exec("CREATE INDEX idx_exam_date ON certified_students(exam_date)");
    $pdo->exec("CREATE INDEX idx_pass_fail ON certified_students(pass_fail)");
    $pdo->exec("CREATE INDEX idx_student_code ON certified_students(student_code)");
    $pdo->exec("CREATE INDEX idx_booking_status ON certified_students(booking_status)");
    
    echo "Table certified_students recreated successfully with all columns and indexes\n";

} catch (PDOException $e) {
    die("Error creating table: " . $e->getMessage());
}