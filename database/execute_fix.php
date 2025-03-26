<?php
require_once __DIR__ . '/../config/database.php';

try {
    // Execute schema fixes
    $sql = file_get_contents(__DIR__ . '/fix_schema.sql');
    $pdo->exec($sql);
    echo "Schema fixes applied successfully. You can now delete this file.";
} catch (PDOException $e) {
    echo "Error applying schema fixes: " . $e->getMessage();
}