<?php
declare(strict_types=1);

/**
 * Password verification page for protected links/notes
 */

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/auth.php'; // Required for session management in csrf.php
require_once __DIR__ . '/app/functions.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\db;

// Config & Base URL Logic
$config = db\get_config();
$baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $baseUrl = $host ? ($scheme . '://' . $host) : '';
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$slug = $_GET['slug'] ?? '';
$type = $_GET['type'] ?? 'link'; // 'link' or 'note'

if (!$slug) {
    header('Location: /');
    exit;
}

// Check if already unlocked in session
$sessionKey = 'unlocked_' . $type . '_' . $slug;
if (isset($_SESSION[$sessionKey]) && $_SESSION[$sessionKey] === true) {
    header('Location: /' . urlencode($slug));
    exit;
}

// Get the item
if ($type === 'link') {
    $item = db\get_link_by_slug($slug);
} else {
    $item = db\get_note_by_slug($slug);
}

if (!$item) {
    include __DIR__ . '/404.php';
    exit;
}

// If no password required, redirect
if (empty($item['password_hash'])) {
    header('Location: /' . urlencode($slug));
    exit;
}

$error = '';

// Handle password submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $password = $_POST['password'] ?? '';
        if (password_verify($password, $item['password_hash'])) {
            $_SESSION[$sessionKey] = true;
            header('Location: /' . urlencode($slug));
            exit;
        } else {
            sleep(1);
            $error = 'Yanlış şifre.';
        }
    }
}

$itemTitle = $item['title'] ?? ($type === 'link' ? 'Kısa Link' : 'Not');
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Gerekli - <?php echo \App\e($itemTitle); ?></title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo \App\e($baseUrl); ?>/assets/css/admin.css?v=<?php echo time(); ?>">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-color, #f8fafc);
            color: var(--text-color, #333);
            padding: 20px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: var(--card-bg, #fff);
            padding: 2.5rem;
            border-radius: var(--radius, 0.5rem);
            box-shadow: var(--shadow-md, 0 4px 6px -1px rgb(0 0 0 / 0.1));
            border: 1px solid var(--border-color, #e2e8f0);
            text-align: center;
        }

        .login-box h1 {
            margin-bottom: 0.5rem;
            color: var(--text-color);
        }

        .login-box .subtitle {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }

        .lock-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
    <script>
        // Init Dark Mode
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>

<body>
    <div class="login-box">
        <div class="lock-icon">🔒</div>
        <h1>Şifre Korumalı İçerik</h1>
        <p class="subtitle">Bu <?php echo $type === 'link' ? 'bağlantıya' : 'nota'; ?> erişmek için şifre gerekiyor.</p>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>
            <div class="form-group">
                <label for="password">Şifre</label>
                <input type="password" name="password" id="password" required autofocus placeholder="Şifreyi girin...">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">Aç</button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="/" class="btn btn-outline" style="font-size: 0.85rem;">← Ana Sayfa</a>
        </div>
    </div>
</body>

</html>