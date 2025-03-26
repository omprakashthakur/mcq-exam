<?php
session_start();
require_once '../includes/security.php';
require_once '../config/database.php';

// Require admin authentication
require_admin();

// Set maximum execution time to 5 minutes
set_time_limit(300);

// Create backup directory if it doesn't exist
$backupDir = __DIR__ . '/../backups';
if (!file_exists($backupDir)) {
    mkdir($backupDir, 0755, true);
}

// Create backup filename with timestamp
$timestamp = date('Y-m-d_H-i-s');
$filename = $backupDir . '/backup_' . $timestamp . '.sql';

// Get database connection details directly from the PDO connection
try {
    $dbInfo = [];
    $dbInfo['host'] = $pdo->query("SELECT @@hostname")->fetchColumn();
    $dbInfo['dbname'] = $pdo->query("SELECT DATABASE()")->fetchColumn();
    
    // Get config file path and manually extract database credentials
    $configPath = __DIR__ . '/../config/database.config.php';
    if (file_exists($configPath)) {
        $config = include $configPath;
        if (is_array($config)) {
            $dbInfo['username'] = $config['username'] ?? '';
            $dbInfo['password'] = $config['password'] ?? '';
        }
    }
    
    // Ensure we have all required credentials
    if (empty($dbInfo['host']) || empty($dbInfo['dbname']) || 
        empty($dbInfo['username'])) {
        throw new Exception("Database connection information is incomplete");
    }
    
    header('Content-Type: application/json');
    
    // Build mysqldump command
    $command = sprintf(
        'mysqldump --host=%s --user=%s --password=%s %s > %s',
        escapeshellarg($dbInfo['host']),
        escapeshellarg($dbInfo['username']),
        escapeshellarg($dbInfo['password']),
        escapeshellarg($dbInfo['dbname']),
        escapeshellarg($filename)
    );
    
    // Execute backup
    $output = [];
    $returnVar = 0;
    exec($command, $output, $returnVar);
    
    // Check if backup was successful
    if ($returnVar === 0) {
        // Compress the backup
        $zip = new ZipArchive();
        $zipName = $filename . '.zip';
        
        if ($zip->open($zipName, ZipArchive::CREATE) === TRUE) {
            $zip->addFile($filename, basename($filename));
            $zip->close();
            
            // Remove uncompressed SQL file
            unlink($filename);
            
            // Remove old backups (keep last 5)
            $backups = glob($backupDir . '/backup_*.zip');
            if (count($backups) > 5) {
                usort($backups, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                $oldBackups = array_slice($backups, 5);
                foreach ($oldBackups as $oldBackup) {
                    unlink($oldBackup);
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Backup created successfully',
                'file' => basename($zipName),
                'path' => str_replace('\\', '/', substr($zipName, strlen($_SERVER['DOCUMENT_ROOT'])))
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to create zip archive'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create backup. Command returned: ' . implode(', ', $output)
        ]);
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>