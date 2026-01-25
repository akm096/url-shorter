<?php
/**
 * Standalone backup download script
 * Minimal dependencies to work on restrictive shared hosting
 */

// Start session
session_start();

// Check login - using the correct session variable
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Get config
$config = require __DIR__ . '/../app/config.php';
$driver = $config['db']['driver'] ?? 'mysql';
$date = date('Y-m-d_H-i');

// Clear output buffers
while (ob_get_level()) {
    ob_end_clean();
}

// Close session to free lock
session_write_close();

if ($driver === 'sqlite') {
    $dbPath = $config['db']['sqlite_path'];
    if (file_exists($dbPath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="backup_' . $date . '.sqlite"');
        header('Content-Length: ' . filesize($dbPath));
        readfile($dbPath);
        exit;
    }
    die('Database file not found');
}

// MySQL backup
// MySQL backup
try {
    require_once __DIR__ . '/../app/db.php';
    $pdo = \App\db\get_db();
    // Ensure PDO error mode is exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $tables = ['links', 'notes', 'link_stats', 'note_stats', 'system_logs'];

    // Explicitly select database to fix "No database selected" error if needed
    // $config = \App\db\get_config();
    // $pdo->exec("USE `" . $config['db']['name'] . "`"); // 'name' key check needed

    $output = "-- Backup: $date\n\n";

    foreach ($tables as $table) {
        // Schema
        $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
        $row = $stmt->fetch(PDO::FETCH_NUM);
        if ($row) {
            $output .= "DROP TABLE IF EXISTS `$table`;\n";
            $output .= $row[1] . ";\n\n";
        }

        // Data
        $data = $pdo->query("SELECT * FROM `$table`");
        while ($r = $data->fetch(PDO::FETCH_ASSOC)) {
            $values = [];
            foreach ($r as $v) {
                $values[] = $v === null ? 'NULL' : $pdo->quote($v);
            }
            $output .= "INSERT INTO `$table` VALUES(" . implode(',', $values) . ");\n";
        }
        $output .= "\n";
    }

    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="backup_' . $date . '.sql"');
    header('Content-Length: ' . strlen($output));
    echo $output;

} catch (Exception $e) {
    header('Content-Type: text/plain');
    echo "Backup Error: " . $e->getMessage();
}
