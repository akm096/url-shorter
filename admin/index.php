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

// Handle POST actions (delete/toggle)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'], $_POST['csrf_token'])) {
    $action = $_POST['action'];
    $id = (int) $_POST['id'];
    $token = $_POST['csrf_token'];

    if (\App\verify_csrf($token)) {
        if ($action === 'delete') {
            db\delete_link($id);
            header('Location: index.php');
            exit;
        } elseif ($action === 'toggle') {
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

// Search & Pagination
$search = $_GET['search'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$allLinks = db\get_all_links($search);
$totalLinks = count($allLinks);
$totalPages = max(1, (int) ceil($totalLinks / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$links = array_slice($allLinks, $offset, $perPage);

$stats = db\get_stats();

// Base URL for short links
$config = db\get_config();
$baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $baseUrl = $host ? ($scheme . '://' . $host) : '';
}

?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetim Paneli</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .top-bar h1 {
            margin: 0;
        }

        .top-bar nav a {
            margin-left: 15px;
            text-decoration: none;
            color: #333;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }

        .top-bar nav a:hover {
            background: #eee;
        }

        .top-bar nav a.primary {
            background: #333;
            color: #fff;
            border-color: #333;
        }

        .top-bar nav a.primary:hover {
            background: #555;
        }

        .search-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 8px;
        }

        .search-bar input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            width: 250px;
        }

        .search-bar button {
            padding: 8px 16px;
            border: 1px solid #333;
            background: #333;
            color: #fff;
            border-radius: 4px;
            cursor: pointer;
        }

        .card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th,
        td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }

        th {
            background: #fafafa;
            font-weight: 600;
            white-space: nowrap;
        }

        tr:hover td {
            background: #fafafa;
        }

        .url-cell {
            max-width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .url-cell a {
            color: #0066cc;
            text-decoration: none;
        }

        .url-cell a:hover {
            text-decoration: underline;
        }

        .short-url {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .copy-btn {
            padding: 3px 8px;
            font-size: 11px;
            background: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 3px;
            cursor: pointer;
        }

        .copy-btn:hover {
            background: #e0e0e0;
        }

        .copy-btn.copied {
            background: #4caf50;
            color: #fff;
            border-color: #4caf50;
        }

        .badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
        }

        .badge-active {
            background: #e8f5e9;
            color: #2e7d32;
        }

        .badge-inactive {
            background: #eeeeee;
            color: #666;
        }

        .actions {
            white-space: nowrap;
        }

        .actions form {
            display: inline;
        }

        .actions a,
        .actions button {
            padding: 4px 8px;
            font-size: 12px;
            border-radius: 3px;
            text-decoration: none;
            cursor: pointer;
            margin-right: 4px;
        }

        .actions a {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ccc;
        }

        .actions button {
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ccc;
            font-family: inherit;
        }

        .actions .del-btn {
            background: #ffebee;
            color: #c62828;
            border-color: #ffcdd2;
        }

        .actions a:hover,
        .actions button:hover {
            opacity: 0.8;
        }

        .stats {
            margin-top: 20px;
            display: flex;
            gap: 30px;
        }

        .stat-box {
            background: #fff;
            padding: 15px 20px;
            border: 1px solid #ddd;
            border-radius: 6px;
            text-align: center;
        }

        .stat-box .num {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .stat-box .label {
            font-size: 12px;
            color: #666;
        }

        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .pagination a,
        .pagination span {
            padding: 6px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
            font-size: 13px;
        }

        .pagination a:hover {
            background: #eee;
        }

        .pagination .current {
            background: #333;
            color: #fff;
            border-color: #333;
        }

        .empty {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="top-bar">
            <h1>Yönetim Paneli</h1>
            <nav>
                <a href="new.php" class="primary">+ Yeni Link</a>
                <a href="logout.php">Çıkış</a>
            </nav>
        </div>

        <form method="get" action="" class="search-bar">
            <input type="text" name="search" placeholder="Slug veya URL ara..." value="<?php echo \App\e($search); ?>">
            <button type="submit">Ara</button>
            <?php if ($search): ?><a href="index.php" style="padding:8px 12px;text-decoration:none;color:#666;">✕
                    Temizle</a><?php endif; ?>
        </form>

        <div class="card">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Kısa Link</th>
                        <th>Hedef URL</th>
                        <th>Not</th>
                        <th>Tip</th>
                        <th>Tık</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($links)): ?>
                        <?php foreach ($links as $link): ?>
                            <?php $shortUrl = $baseUrl . '/' . $link['slug']; ?>
                            <tr>
                                <td><?php echo \App\e($link['id']); ?></td>
                                <td>
                                    <div class="short-url">
                                        <a href="<?php echo \App\e($shortUrl); ?>"
                                            target="_blank">/<?php echo \App\e($link['slug']); ?></a>
                                        <button type="button" class="copy-btn"
                                            onclick="copyUrl('<?php echo \App\e($shortUrl); ?>', this)">Kopyala</button>
                                    </div>
                                </td>
                                <td class="url-cell" title="<?php echo \App\e($link['target_url']); ?>">
                                    <a href="<?php echo \App\e($link['target_url']); ?>" target="_blank"
                                        rel="noopener"><?php echo \App\e($link['target_url']); ?></a>
                                </td>
                                <td><?php echo \App\e($link['title'] ?? '-'); ?></td>
                                <td><?php echo \App\e($link['redirect_type']); ?></td>
                                <td><strong><?php echo \App\e($link['click_count']); ?></strong></td>
                                <td><?php echo \App\e(substr($link['created_at'], 0, 10)); ?></td>
                                <td>
                                    <?php if (!empty($link['active'])): ?>
                                        <span class="badge badge-active">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-inactive">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="edit.php?id=<?php echo \App\e($link['id']); ?>">Düzenle</a>
                                    <form method="post" action="index.php" onsubmit="return confirm('Durumu değiştir?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo \App\e($link['id']); ?>">
                                        <button
                                            type="submit"><?php echo !empty($link['active']) ? 'Pasif' : 'Aktif'; ?></button>
                                    </form>
                                    <form method="post" action="index.php"
                                        onsubmit="return confirm('Silmek istediğinize emin misiniz?');">
                                        <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo \App\e($link['id']); ?>">
                                        <button type="submit" class="del-btn">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="empty">
                                <?php echo $search ? 'Arama sonucu bulunamadı.' : 'Henüz link yok.'; ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">«</a>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">‹</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <?php if ($i === $page): ?>
                        <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a
                            href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">›</a>
                    <a
                        href="?page=<?php echo $totalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">»</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="stats">
            <div class="stat-box">
                <div class="num"><?php echo \App\e($stats['count']); ?></div>
                <div class="label">Toplam Link</div>
            </div>
            <div class="stat-box">
                <div class="num"><?php echo \App\e($stats['clicks']); ?></div>
                <div class="label">Toplam Tıklama</div>
            </div>
        </div>
    </div>

    <script>
function copyUrl(text, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(text).then(function() { showCopied(btn); });
    } else {
        var inp = document.createElement('input');
        inp.value = text;
        document.body.appendChild(inp);
        inp.select();
        try { document.execCommand('copy'); showCopied(btn); } catch(e) {}
        document.body.removeChild(inp);
    }
}
function showCopied(btn) {
    var orig = btn.textContent;
    btn.textContent = '✓';
    btn.classList.add('copied');
    setTimeout(function() { btn.textContent = orig; btn.classList.remove('copied'); }, 1200);
}
</script>
</body>
</html>