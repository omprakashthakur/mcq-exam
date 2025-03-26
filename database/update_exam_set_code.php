<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Add exam_set_code column
    $pdo->exec("ALTER TABLE exam_sets ADD COLUMN IF NOT EXISTS exam_set_code VARCHAR(20) UNIQUE DEFAULT NULL");
    
    // Create index for faster lookups
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_exam_set_code ON exam_sets(exam_set_code)");
    
    echo "Successfully added exam_set_code column to exam_sets table";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>