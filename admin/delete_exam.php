<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$exam_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($exam_id) {
    try {
        // Start transaction to ensure all related records are deleted
        $pdo->beginTransaction();
        
        // Delete all related records first
        $stmt = $pdo->prepare("DELETE FROM user_answers WHERE question_id IN (SELECT id FROM questions WHERE exam_set_id = ?)");
        $stmt->execute([$exam_id]);
        
        $stmt = $pdo->prepare("DELETE FROM exam_attempts WHERE exam_set_id = ?");
        $stmt->execute([$exam_id]);
        
        $stmt = $pdo->prepare("DELETE FROM questions WHERE exam_set_id = ?");
        $stmt->execute([$exam_id]);
        
        // Finally delete the exam
        $stmt = $pdo->prepare("DELETE FROM exam_sets WHERE id = ?");
        $stmt->execute([$exam_id]);
        
        $pdo->commit();
        $_SESSION['success'] = 'Exam and all related data deleted successfully';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error deleting exam: ' . $e->getMessage();
    }
}

header('Location: manage_exams.php');
exit();
?>