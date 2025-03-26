<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = "ALTER TABLE exam_access ADD COLUMN IF NOT EXISTS is_retake TINYINT(1) DEFAULT 0";
    $pdo->exec($sql);
    echo "Successfully added is_retake column to exam_access table";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>