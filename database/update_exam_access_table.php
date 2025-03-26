<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/update_exam_access.sql');
    $pdo->exec($sql);
    echo "Successfully added created_by column to exam_access table";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>