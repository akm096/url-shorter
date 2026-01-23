<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\auth;
use App\db;

// Login check
auth\require_login();

// CSRF token
$csrfToken = \App\csrf_token();

// Handle POST actions (delete/toggle) - more secure than GET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token'])) {
    $action = $_POST['action'];
    $id     = (int)$_POST['id'];
    $token  = $_POST['csrf_token'];
    
    if (\App\verify_csrf($token)) {
        if ($action === 'delete') {
            db\delete_link($id);
            header('Location: index.php');
            exit;
        } elseif ($action === 'toggle') {
            // Get record and toggle active status
            $pdo = db\get_db();
            $stmt = $pdo->prepare('SELECT * FROM links WHERE id = ?');
            $stmt->execute([$id]);
            $link = $stmt->fetch();
            if ($link) {
                $newStatus = $link['active'] ? 0 : 1;
                $stmt2 = $pdo->prepare('UPDATE links SET active = ? WHERE id = ?');
                $stmt2->execute([$newStatus, $id]);
            }
            header('Location: index.php');
            exit;
        }
    }
}

// Search
$search = $_GET['search'] ?? null;

$links = db\get_all_links($search);
$stats = db\get_stats();

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yönetim Paneli</title>
    <style>
        body {font-family: Arial, sans-serif; padding: 20px;}
        table {border-collapse: collapse; width: 100%; margin-top: 20px;}
        th, td {border: 1px solid #ddd; padding: 8px; text-align: left;}
        th {background-color: #f2f2f2;}
        .actions {white-space: nowrap;}
        .actions form {display: inline;}
        .actions button {
            background: none;
            border: none;
            color: #0066cc;
            cursor: pointer;
            padding: 0;
            font: inherit;
            text-decoration: underline;
        }
        .actions button:hover {color: #004499;}
        .actions a {margin-right: 5px;}
        .stats {margin-top: 20px;}
        .top-bar {display: flex; justify-content: space-between; align-items: center;}
    </style>
</head>
<body>
    <div class="top-bar">
        <h1>Yönetim Paneli</h1>
        <div>
            <a href="new.php">Yeni Link Ekle</a> |
            <a href="logout.php">Çıkış</a>
        </div>
    </div>
    <form method="get" action="" style="margin-top:20px;">
        <input type="text" name="search" placeholder="Slug veya URL ara" value="<?php echo \App\e($search); ?>">
        <button type="submit">Ara</button>
    </form>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Slug</th>
                <th>Hedef URL</th>
                <th>Not</th>
                <th>Yönlendirme</th>
                <th>Tıklama</th>
                <th>Oluşturulma</th>
                <th>Aktif</th>
                <th>İşlemler</th>
            </tr>
        </thead>
        <tbody>
<?php if (!empty($links)): ?>
    <?php foreach ($links as $link): ?>
        <tr>
            <td><?php echo \App\e($link['id']); ?></td>
            <td><?php echo \App\e($link['slug']); ?></td>
            <td>
                <a href="<?php echo \App\e($link['target_url']); ?>" target="_blank" rel="noopener noreferrer">
                    <?php echo \App\e($link['target_url']); ?>
                </a>
            </td>
            <td><?php echo \App\e($link['title'] ?? ''); ?></td>
            <td><?php echo \App\e($link['redirect_type']); ?></td>
            <td><?php echo \App\e($link['click_count']); ?></td>
            <td><?php echo \App\e($link['created_at']); ?></td>
            <td><?php echo !empty($link['active']) ? 'Evet' : 'Hayır'; ?></td>
            <td class="actions">
                <a href="edit.php?id=<?php echo \App\e($link['id']); ?>">Düzenle</a>
                |
                <form method="post" action="index.php" onsubmit="return confirm('Aktiflik durumunu değiştirmek istiyor musunuz?');">
                    <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?php echo \App\e($link['id']); ?>">
                    <button type="submit"><?php echo !empty($link['active']) ? 'Pasif Yap' : 'Aktif Yap'; ?></button>
                </form>
                |
                <form method="post" action="index.php" onsubmit="return confirm('Bu linki silmek istediğinizden emin misiniz?');">
                    <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?php echo \App\e($link['id']); ?>">
                    <button type="submit">Sil</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="9">Kayıt bulunamadı.</td>
    </tr>
<?php endif; ?>
</tbody>
    </table>
    <div class="stats">
        <p>Toplam link sayısı: <?php echo \App\e($stats['count']); ?></p>
        <p>Toplam tıklama: <?php echo \App\e($stats['clicks']); ?></p>
    </div>
</body>
</html>