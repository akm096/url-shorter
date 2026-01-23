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
// Fonksiyonlar tam nitelikli olarak çağrılacak.

auth\require_login();

$error = '';
$success = '';
// Varsayılan değerler
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

        // URL validation
        if (!\App\validate_url($target_url)) {
            $error = 'Geçerli bir URL giriniz (http veya https ile başlamalı).';
        } elseif (!\App\validate_url_length($target_url)) {
            $error = 'URL çok uzun. Maksimum ' . \App\MAX_URL_LENGTH . ' karakter olabilir.';
        } else {
            // Slug validation
            if ($slug === '') {
                $len = random_int(6, 8);
                $slug = \App\generate_random_slug($len);
            } elseif (!\App\validate_slug($slug)) {
                $error = 'Slug yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } elseif (!\App\validate_slug_length($slug)) {
                $error = 'Slug ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter arasında olmalıdır.';
            } elseif (\App\is_reserved_slug($slug)) {
                $error = 'Bu slug sistem tarafından ayrılmış. Farklı bir slug deneyin.';
            } elseif (db\get_link_by_slug($slug) !== null) {
                $error = 'Bu slug zaten kullanılıyor, farklı bir slug deneyin.';
            }
        }

        if (!$error) {
            $data = [
                'slug' => $slug,
                'target_url' => $target_url,
                'title' => $title,
                'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                'created_at' => date('Y-m-d H:i:s'),
                'active' => $active,
            ];

            // Try to insert, handle potential race condition
            try {
                db\insert_link($data);
                $success = 'Link başarıyla oluşturuldu.';
                $target_url = '';
                $slug = '';
                $title = '';
                $redirect_type = 302;
                $active = 1;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error = 'Bu slug zaten kullanılıyor, farklı bir slug deneyin.';
                } else {
                    $error = 'Bir hata oluştu. Lütfen tekrar deneyin.';
                }
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Yeni Link Ekle</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
        }

        label {
            display: block;
            margin-top: 10px;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 8px;
        }

        .error {
            color: red;
        }

        .success {
            color: green;
        }
    </style>
</head>

<body>
    <h1>Yeni Link Ekle</h1>
    <p><a href="index.php">&laquo; Geri Dön</a></p>
    <?php if ($error): ?>
        <p class="error"><?php echo \App\e($error); ?></p><?php endif; ?>
    <?php if ($success): ?>
        <p class="success"><?php echo \App\e($success); ?></p><?php endif; ?>
    <form method="post" action="">
        <?php echo \App\csrf_input(); ?>
        <label for="target_url">Hedef URL *</label>
        <input type="text" name="target_url" id="target_url" required value="<?php echo \App\e($target_url); ?>">

        <label for="slug">Kısa Kod (boş bırakılırsa rastgele üretilir)</label>
        <input type="text" name="slug" id="slug" value="<?php echo \App\e($slug); ?>">

        <label for="title">Başlık/Not (isteğe bağlı)</label>
        <input type="text" name="title" id="title" value="<?php echo \App\e($title); ?>">

        <label>Yönlendirme Tipi</label>
        <select name="redirect_type">
            <option value="301" <?php echo $redirect_type == 301 ? 'selected' : ''; ?>>301 (kalıcı)</option>
            <option value="302" <?php echo $redirect_type == 302 ? 'selected' : ''; ?>>302 (geçici)</option>
        </select>

        <label>
            <input type="checkbox" name="active" value="1" <?php echo $active ? 'checked' : ''; ?>> Aktif
        </label>

        <br>
        <button type="submit">Kaydet</button>
    </form>
</body>

</html>