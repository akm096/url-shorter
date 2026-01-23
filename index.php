<?php
declare(strict_types=1);

// Ana yönlendirici dosya. Bu dosya kök dizinde bulunur ve gelen istekleri karşılar.

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/functions.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\db;

// Konfigürasyon
$config = db\get_config();
$baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $baseUrl = $host ? ($scheme . '://' . $host) : '';
}

// Eğer query string'de slug varsa, yönlendirme yapılacak
$slug = $_GET['slug'] ?? null;
if ($slug) {
    $link = db\get_link_by_slug((string) $slug);
    if ($link && (int) $link['active'] === 1) {
        // Tıklama sayısını arttır
        db\increment_click_count((int) $link['id']);
        // 301 veya 302 yönlendirme
        $status = (int) $link['redirect_type'] === 301 ? 301 : 302;
        header('Location: ' . $link['target_url'], true, $status);
        exit;
    }
    // Aktif olmayan veya bulunamayan slug için 404
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>Bu kısa bağlantı bulunamadı veya pasif durumda.</p>";
    exit;
}

// Ana sayfa: URL kısaltma formu
$error = '';
$success = '';
$createdShortUrl = '';
$target_url = '';
$custom_slug = '';
$title = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $target_url = trim((string) ($_POST['target_url'] ?? ''));
        $custom_slug = trim((string) ($_POST['slug'] ?? ''));
        $title = trim((string) ($_POST['title'] ?? ''));

        // URL validation
        if (!\App\validate_url($target_url)) {
            $error = 'Geçerli bir URL giriniz (http veya https ile başlamalı).';
        } elseif (!\App\validate_url_length($target_url)) {
            $error = 'URL çok uzun. Maksimum ' . \App\MAX_URL_LENGTH . ' karakter olabilir.';
        } else {
            // Slug validation
            if ($custom_slug === '') {
                $len = random_int(6, 8);
                $custom_slug = \App\generate_random_slug($len);
            } elseif (!\App\validate_slug($custom_slug)) {
                $error = 'Kısa kod yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } elseif (!\App\validate_slug_length($custom_slug)) {
                $error = 'Kısa kod ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter arasında olmalıdır.';
            } elseif (\App\is_reserved_slug($custom_slug)) {
                $error = 'Bu kısa kod sistem tarafından ayrılmış. Farklı bir kısa kod deneyin.';
            } elseif (db\get_link_by_slug($custom_slug) !== null) {
                $error = 'Bu kısa kod zaten kullanılıyor. Farklı bir kısa kod deneyin.';
            }
        }

        if (!$error) {
            $redirect_type = (int) ($config['redirect_default'] ?? 302);
            if (!in_array($redirect_type, [301, 302], true)) {
                $redirect_type = 302;
            }

            $data = [
                'slug' => $custom_slug,
                'target_url' => $target_url,
                'title' => $title,
                'redirect_type' => $redirect_type,
                'created_at' => date('Y-m-d H:i:s'),
                'active' => 1,
            ];
            
            // Try to insert, handle potential race condition with unique constraint
            try {
                db\insert_link($data);
                $createdShortUrl = $baseUrl ? ($baseUrl . '/' . $custom_slug) : ('/' . $custom_slug);
                $success = 'Kısa bağlantınız hazır.';
                // formu sıfırla
                $target_url = '';
                $custom_slug = '';
                $title = '';
            } catch (\PDOException $e) {
                // Check if it's a duplicate key error
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error = 'Bu kısa kod zaten kullanılıyor. Farklı bir kısa kod deneyin.';
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Kısaltma</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 30px;
        }

        .wrap {
            max-width: 720px;
            margin: 0 auto;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 22px;
        }

        h1 {
            margin: 0 0 10px 0;
        }

        .muted {
            color: #666;
            margin: 0 0 18px 0;
        }

        label {
            display: block;
            margin-top: 12px;
            font-weight: 600;
        }

        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            box-sizing: border-box;
        }

        button {
            margin-top: 16px;
            padding: 10px 14px;
            border: 1px solid #333;
            background: #333;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            opacity: .9;
        }

        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .top a {
            text-decoration: none;
            color: #0b57d0;
        }

        .msg {
            margin-top: 14px;
            padding: 12px;
            border-radius: 6px;
        }

        .err {
            background: #ffecec;
            border: 1px solid #f5a3a3;
            color: #a40000;
        }

        .ok {
            background: #ecfff1;
            border: 1px solid #9fe0b2;
            color: #0a5a22;
        }

        .short-box {
            margin-top: 10px;
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .short-box input {
            flex: 1 1 320px;
        }

        .ghost {
            background: #fff;
            color: #333;
        }

        .ghost:hover {
            background: #f2f2f2;
        }

        .note {
            font-size: 12px;
            color: #777;
            margin-top: 10px;
        }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="top">
            <h1>URL Kısaltma</h1>
            <a href="/admin/index.php">Düzenle / Yönetim Paneli</a>
        </div>
        <p class="muted">Uzun linki yapıştırın, kısa linki hemen oluşturun.</p>

        <?php if ($error): ?>
            <div class="msg err"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="msg ok">
                <div><?php echo \App\e($success); ?></div>
                <div class="short-box">
                    <input type="text" id="shortUrl" value="<?php echo \App\e($createdShortUrl); ?>" readonly>
                    <button type="button" class="ghost" onclick="copyShortUrl()">Kopyala</button>
                    <a href="<?php echo \App\e($createdShortUrl); ?>" target="_blank" rel="noopener noreferrer">Aç</a>
                </div>
            </div>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>

            <label for="target_url">Hedef URL *</label>
            <input type="text" name="target_url" id="target_url" required placeholder="https://ornek.com/uzun-link"
                value="<?php echo \App\e($target_url); ?>">

            <label for="slug">Kısa Kod (opsiyonel)</label>
            <input type="text" name="slug" id="slug" placeholder="ornek123" value="<?php echo \App\e($custom_slug); ?>">

            <label for="title">Not (opsiyonel)</label>
            <input type="text" name="title" id="title" placeholder="Açıklama" value="<?php echo \App\e($title); ?>">

            <button type="submit">Kısalt</button>
        </form>
    </div>

    <script>
        function copyShortUrl() {
            var el = document.getElementById('shortUrl');
            if (!el) return;
            el.select();
            el.setSelectionRange(0, 99999);
            try { document.execCommand('copy'); } catch (e) { }
        }
    </script>
</body>

</html>