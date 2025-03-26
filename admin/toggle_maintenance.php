<?php
session_start();
require_once '../includes/security.php';

require_admin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

try {
    verify_csrf_token($_POST['csrf_token']);
    
    $maintenanceFile = __DIR__ . '/../maintenance.lock';
    $isEnabled = isset($_POST['enabled']) && $_POST['enabled'] === 'true';
    
    if ($isEnabled) {
        $maintenance = [
            'enabled' => true,
            'started_at' => date('Y-m-d H:i:s'),
            'expected_up_time' => $_POST['expected_up_time'] ?? null,
            'message' => $_POST['message'] ?? 'System is under maintenance'
        ];
        file_put_contents($maintenanceFile, json_encode($maintenance));
        echo json_encode(['success' => true, 'message' => 'Maintenance mode enabled']);
    } else {
        if (file_exists($maintenanceFile)) {
            unlink($maintenanceFile);
        }
        echo json_encode(['success' => true, 'message' => 'Maintenance mode disabled']);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()]);
}
?>