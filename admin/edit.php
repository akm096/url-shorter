<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';

\App\security\send_security_headers();

use App\auth;
use App\db;

auth\require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = db\get_db();
$stmt = $pdo->prepare('SELECT * FROM links WHERE id = ?');
$stmt->execute([$id]);
$link = $stmt->fetch();
if (!$link) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $target_url = trim($_POST['target_url'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $redirect_type = (int) ($_POST['redirect_type'] ?? $link['redirect_type']);
        $active = isset($_POST['active']) ? 1 : 0;

        if (!\App\validate_url($target_url)) {
            $error = 'Geçerli bir URL giriniz.';
        } elseif (!\App\validate_url_length($target_url)) {
            $error = 'URL çok uzun. Maksimum ' . \App\MAX_URL_LENGTH . ' karakter.';
        } elseif ($slug === '') {
            $error = 'Slug boş bırakılamaz.';
        } elseif (!\App\validate_slug($slug)) {
            $error = 'Slug yalnızca harf, sayı, tire ve alt çizgi içerebilir.';
        } elseif (!\App\validate_slug_length($slug)) {
            $error = 'Slug ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter olmalı.';
        } elseif (\App\is_reserved_slug($slug)) {
            $error = 'Bu slug sistem tarafından ayrılmış.';
        } else {
            $existing = db\get_link_by_slug($slug);
            if ($existing && (int) $existing['id'] !== $id) {
                $error = 'Bu slug başka bir link tarafından kullanılıyor.';
            }
        }

        if (!$error) {
            try {
                db\update_link($id, [
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'title' => $title,
                    'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                    'active' => $active,
                ]);
                $success = 'Kayıt güncellendi.';
                $link = array_merge($link, ['slug' => $slug, 'target_url' => $target_url, 'title' => $title, 'redirect_type' => $redirect_type, 'active' => $active]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug başka bir link tarafından kullanılıyor.';
                } else {
                    $error = 'Bir hata oluştu.';
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Düzenle</title>
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
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: #fff;
            padding: 24px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }

        h1 {
            margin: 0 0 20px 0;
            font-size: 22px;
        }

        .back {
            margin-bottom: 16px;
        }

        .back a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
        }

        .back a:hover {
            text-decoration: underline;
        }

        .msg {
            padding: 10px 14px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .error {
            background: #ffebee;
            color: #c62828;
        }

        .success {
            background: #e8f5e9;
            color: #2e7d32;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 14px;
        }

        input[type="text"],
        select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        input[type="text"]:focus,
        select:focus {
            outline: none;
            border-color: #333;
        }

        .checkbox-row {
            margin-top: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .checkbox-row input {
            width: auto;
        }

        button {
            margin-top: 20px;
            padding: 12px 24px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }

        button:hover {
            background: #555;
        }

        .info {
            font-size: 13px;
            color: #666;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #eee;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="back"><a href="index.php">← Geri Dön</a></div>
        <div class="card">
            <h1>Link Düzenle</h1>
            <?php if ($error): ?>
                <div class="msg error"><?php echo \App\e($error); ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="msg success"><?php echo \App\e($success); ?></div><?php endif; ?>
            <form method="post" action="">
                <?php echo \App\csrf_input(); ?>
                <label for="target_url">Hedef URL *</label>
                <input type="text" name="target_url" id="target_url" required
                    value="<?php echo \App\e($link['target_url']); ?>">

                <label for="slug">Kısa Kod *</label>
                <input type="text" name="slug" id="slug" required value="<?php echo \App\e($link['slug']); ?>">

                <label for="title">Not</label>
                <input type="text" name="title" id="title" value="<?php echo \App\e($link['title'] ?? ''); ?>">

                <label>Yönlendirme Tipi</label>
                <select name="redirect_type">
                    <option value="301" <?php echo $link['redirect_type'] == 301 ? 'selected' : ''; ?>>301 (Kalıcı)
                    </option>
                    <option value="302" <?php echo $link['redirect_type'] == 302 ? 'selected' : ''; ?>>302 (Geçici)
                    </option>
                </select>

                <div class="checkbox-row">
                    <input type="checkbox" name="active" id="active" value="1" <?php echo $link['active'] ? 'checked' : ''; ?>>
                    <label for="active" style="margin:0;font-weight:normal;">Aktif</label>
                </div>

                <button type="submit">Güncelle</button>
            </form>
            <div class="info">
                <strong>ID:</strong> <?php echo \App\e($link['id']); ?> &nbsp;|&nbsp;
                <strong>Tıklama:</strong> <?php echo \App\e($link['click_count']); ?> &nbsp;|&nbsp;
                <strong>Oluşturulma:</strong> <?php echo \App\e($link['created_at']); ?>
            </div>
        </div>
    </div>
</body>

</html>