<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Add time_taken column to exam_attempts table
    $pdo->exec("ALTER TABLE exam_attempts ADD COLUMN IF NOT EXISTS time_taken INT DEFAULT 0");
    echo "Database schema updated successfully!";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}