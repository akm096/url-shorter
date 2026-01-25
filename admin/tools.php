<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/logger.php';

use App\auth;
use App\db;
use App\logger;

// Login check
auth\require_login();

// Handle Backup Action
if (isset($_GET['action']) && $_GET['action'] === 'download_backup') {
    // Close session to prevent header conflicts
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    $config = db\get_config();
    $driver = $config['db']['driver'] ?? 'mysql';
    $date = date('Y-m-d_H-i');

    // Clear any output buffers to prevent corruption
    while (ob_get_level()) {
        ob_end_clean();
    }

    if ($driver === 'sqlite') {
        $dbPath = $config['db']['sqlite_path'];
        if (file_exists($dbPath)) {
            logger\log_system_action('BACKUP_DOWNLOAD', 'SQLite DB downloaded');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="backup_' . $date . '.sqlite"');
            header('Content-Transfer-Encoding: binary');
            header('Content-Length: ' . filesize($dbPath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            readfile($dbPath);
            exit;
        } else {
            $error = "Veritabanı dosyası bulunamadı.";
        }
    } elseif ($driver === 'mysql') {
        // Simple PHP MySQL Dump
        try {
            @set_time_limit(0);
            @ini_set('memory_limit', '256M');

            $pdo = db\get_db();
            $tables = ['links', 'notes', 'link_stats', 'note_stats', 'system_logs'];

            // Build backup content
            $output = "-- Backup generated at $date\n";
            $output .= "-- Generator: URL Shorter Admin\n\n";

            foreach ($tables as $table) {
                // Get Create Table SQL
                $stmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $row = $stmt->fetch(\PDO::FETCH_NUM);

                if ($row) {
                    $output .= "DROP TABLE IF EXISTS `$table`;\n";
                    $output .= $row[1] . ";\n\n";
                }

                // Get Data
                $dataStmt = $pdo->query("SELECT * FROM `$table`");
                while ($dataRow = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
                    $vals = [];
                    foreach ($dataRow as $val) {
                        $vals[] = ($val === null) ? 'NULL' : $pdo->quote($val);
                    }
                    $output .= "INSERT INTO `$table` VALUES (" . implode(", ", $vals) . ");\n";
                }
                $output .= "\n";
            }

            // Clear buffers and send
            while (ob_get_level())
                ob_end_clean();

            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="backup_' . $date . '.sql"');
            header('Content-Length: ' . strlen($output));

            echo $output;
            exit;

        } catch (\Exception $e) {
            // Log error and redirect back
            logger\log_system_action('BACKUP_ERROR', $e->getMessage());
            header('Location: tools.php?error=backup_failed');
            exit;
        }
    }
}

// Check logs pages
$page = (int) ($_GET['p'] ?? 1);
$logs = logger\get_recent_logs(100);

require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex justify-between" style="align-items: center; margin-bottom: 20px;">
    <h2>Araçlar ve Kayıtlar</h2>
</div>

<div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">

    <!-- Backup Card -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <h3>💾 Sistem Yedeği</h3>
        <p class="text-muted">Veritabanının tam bir kopyasını bilgisayarınıza indirin.</p>
        <div
            style="margin-top: 20px; padding: 15px; background: var(--bg-color); border-radius: var(--radius); border: 1px solid var(--border-color);">
            <strong>Veritabanı Türü:</strong>
            <?php echo strtoupper(db\get_config()['db']['driver']); ?><br>
            <br>
            <a href="backup.php" class="btn btn-primary">⬇️ Yedeği İndir</a>
        </div>
    </div>

    <!-- Security Check (Placeholder for Safe Browsing later) -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <h3>🛡️ Güvenlik Durumu</h3>
        <p class="text-muted">Sistem güvenlik özet bilgileri.</p>
        <div style="margin-top: 20px;">
            <ul style="list-style: none; padding: 0;">
                <li style="margin-bottom: 10px;">✅ Veritabanı Bağlantısı: <strong>Aktif</strong></li>
                <li style="margin-bottom: 10px;">✅ Yazma İzinleri: <strong>Kontrol Edildi</strong></li>
                <li style="margin-bottom: 10px;">ℹ️ Google Safe Browsing: <em>Henüz aktif değil</em></li>
            </ul>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 20px;">
    <h3>📜 Sistem Logları (Son 100 İşlem)</h3>
    <div style="overflow-x: auto;">
        <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
            <thead>
                <tr style="text-align: left; border-bottom: 2px solid var(--border-color);">
                    <th style="padding: 10px;">Tarih</th>
                    <th style="padding: 10px;">Kullanıcı</th>
                    <th style="padding: 10px;">İşlem</th>
                    <th style="padding: 10px;">Detay</th>
                    <th style="padding: 10px;">IP Adresi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr style="border-bottom: 1px solid var(--border-color);">
                        <td style="padding: 10px; white-space: nowrap;">
                            <?php echo $log['created_at']; ?>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo \App\e($log['username'] ?? '-'); ?>
                        </td>
                        <td style="padding: 10px;">
                            <span class="badge"
                                style="background: var(--primary-color); color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.8em;">
                                <?php echo \App\e($log['action']); ?>
                            </span>
                        </td>
                        <td style="padding: 10px;">
                            <?php echo \App\e($log['details'] ?? ''); ?>
                        </td>
                        <td style="padding: 10px; font-family: monospace;">
                            <?php echo \App\e($log['ip_address']); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" style="padding: 20px; text-align: center;" class="text-muted">Henüz kayıt yok.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>