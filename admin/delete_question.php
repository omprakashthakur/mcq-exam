<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$question_id = isset($_GET['id']) ? $_GET['id'] : null;

if ($question_id) {
    try {
        $stmt = $pdo->prepare("DELETE FROM questions WHERE id = ?");
        $stmt->execute([$question_id]);
        $_SESSION['success'] = 'Question deleted successfully';
    } catch (PDOException $e) {
        $_SESSION['error'] = 'Error deleting question';
    }
}

// Redirect back to manage questions page
header('Location: ' . $_SERVER['HTTP_REFERER']);
exit();
?>