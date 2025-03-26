<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/exam_retake.sql');
    $pdo->exec($sql);
    echo "Exam retake table created successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>