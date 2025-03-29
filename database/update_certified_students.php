<?php
require_once dirname(__FILE__) . '/../config/database.php';

try {
    // Add new columns to certified_students table
    $sql = "ALTER TABLE certified_students 
            ADD COLUMN IF NOT EXISTS student_code VARCHAR(20) AFTER id,
            ADD COLUMN IF NOT EXISTS college BOOLEAN DEFAULT 0 AFTER apprentice,
            ADD COLUMN IF NOT EXISTS booking_status ENUM('pending', 'confirmed', 'cancelled', 'rescheduled') DEFAULT 'pending' AFTER exam_date,
            ADD COLUMN IF NOT EXISTS booking_date DATE AFTER booking_status,
            ADD INDEX IF NOT EXISTS idx_student_code (student_code),
            ADD INDEX IF NOT EXISTS idx_booking_status (booking_status)";

    $pdo->exec($sql);
    echo "Table certified_students updated successfully\n";

} catch (PDOException $e) {
    die("Error updating table: " . $e->getMessage());
}