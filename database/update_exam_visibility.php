<?php
require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->prepare("UPDATE exam_sets SET is_public = 1, is_active = 1");
    $stmt->execute();
    echo "Successfully updated exam visibility. Updated " . $stmt->rowCount() . " exams.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>