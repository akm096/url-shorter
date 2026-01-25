<?php
declare(strict_types=1);

namespace App\logger;

use App\db;

require_once __DIR__ . '/db.php';

/**
 * Log a system action to the database.
 * 
 * @param string $action The action name (e.g., 'LOGIN', 'DELETE_LINK')
 * @param string|null $details Optional details about the action
 * @return void
 */
function log_system_action(string $action, ?string $details = null): void
{
    try {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $pdo = db\get_db();
        $username = $_SESSION['admin_username'] ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $createdAt = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            'INSERT INTO system_logs (username, action, details, ip_address, created_at)
             VALUES (:username, :action, :details, :ip, :created_at)'
        );

        $stmt->execute([
            ':username' => $username,
            ':action' => $action,
            ':details' => $details,
            ':ip' => $ip,
            ':created_at' => $createdAt
        ]);
    } catch (\Exception $e) {
        // Logging should not break the application flow
        error_log('System Log Error: ' . $e->getMessage());
    }
}

/**
 * Get recent system logs.
 * 
 * @param int $limit
 * @return array
 */
function get_recent_logs(int $limit = 100): array
{
    $pdo = db\get_db();
    $stmt = $pdo->prepare('SELECT * FROM system_logs ORDER BY id DESC LIMIT :limit');
    // PDO limit binding can be tricky, direct injection for int is safe here
    $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}
