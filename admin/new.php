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

$error = '';
$success = '';
$target_url = '';
$slug = '';
$title = '';
$redirect_type = 302;
$active = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $target_url = trim($_POST['target_url'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $redirect_type = (int) ($_POST['redirect_type'] ?? 302);
        $active = isset($_POST['active']) ? 1 : 0;

        if (!\App\validate_url($target_url)) {
            $error = 'Geçerli bir URL giriniz (http veya https ile başlamalı).';
        } elseif (!\App\validate_url_length($target_url)) {
            $error = 'URL çok uzun. Maksimum ' . \App\MAX_URL_LENGTH . ' karakter olabilir.';
        } else {
            if ($slug === '') {
                $slug = \App\generate_random_slug(random_int(6, 8));
            } elseif (!\App\validate_slug($slug)) {
                $error = 'Slug yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } elseif (!\App\validate_slug_length($slug)) {
                $error = 'Slug ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter arasında olmalıdır.';
            } elseif (\App\is_reserved_slug($slug)) {
                $error = 'Bu slug sistem tarafından ayrılmış.';
            } elseif (db\get_link_by_slug($slug) !== null) {
                $error = 'Bu slug zaten kullanılıyor.';
            }
        }

        if (!$error) {
            try {
                db\insert_link([
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'title' => $title,
                    'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                    'created_at' => date('Y-m-d H:i:s'),
                    'active' => $active,
                ]);
                $success = 'Link başarıyla oluşturuldu.';
                $target_url = '';
                $slug = '';
                $title = '';
                $redirect_type = 302;
                $active = 1;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug zaten kullanılıyor.';
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
    <title>Yeni Link Ekle</title>
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

        .hint {
            font-size: 12px;
            color: #888;
            margin-top: 4px;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="back"><a href="index.php">← Geri Dön</a></div>
        <div class="card">
            <h1>Yeni Link Ekle</h1>
            <?php if ($error): ?>
                <div class="msg error"><?php echo \App\e($error); ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="msg success"><?php echo \App\e($success); ?></div><?php endif; ?>
            <form method="post" action="">
                <?php echo \App\csrf_input(); ?>
                <label for="target_url">Hedef URL *</label>
                <input type="text" name="target_url" id="target_url" required placeholder="https://ornek.com/sayfa"
                    value="<?php echo \App\e($target_url); ?>">

                <label for="slug">Kısa Kod</label>
                <input type="text" name="slug" id="slug" placeholder="ornek123" value="<?php echo \App\e($slug); ?>">
                <div class="hint">Boş bırakılırsa rastgele üretilir</div>

                <label for="title">Not</label>
                <input type="text" name="title" id="title" placeholder="Açıklama (isteğe bağlı)"
                    value="<?php echo \App\e($title); ?>">

                <label>Yönlendirme Tipi</label>
                <select name="redirect_type">
                    <option value="301" <?php echo $redirect_type == 301 ? 'selected' : ''; ?>>301 (Kalıcı)</option>
                    <option value="302" <?php echo $redirect_type == 302 ? 'selected' : ''; ?>>302 (Geçici)</option>
                </select>

                <div class="checkbox-row">
                    <input type="checkbox" name="active" id="active" value="1" <?php echo $active ? 'checked' : ''; ?>>
                    <label for="active" style="margin:0;font-weight:normal;">Aktif</label>
                </div>

                <button type="submit">Kaydet</button>
            </form>
        </div>
    </div>
</body>

</html>