<?php
require_once __DIR__ . '/../config/database.php';

try {
    $sql = file_get_contents(__DIR__ . '/fix_notifications.sql');
    $pdo->exec($sql);
    echo "Notifications table created successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>