<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';

use App\auth;
use App\db;
// Fonksiyonları tam nitelikli olarak çağıracağız.

auth\require_login();

// ID parametresi
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$id) {
    header('Location: index.php');
    exit;
}

// Kayıt getir
$pdo = db\get_db();
$stmt = $pdo->prepare('SELECT * FROM links WHERE id = ?');
$stmt->execute([$id]);
$link = $stmt->fetch();
if (!$link) {
    echo 'Kayıt bulunamadı.';
    exit;
}

$error = '';
$success = '';

// Form gönderimi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $target_url = trim($_POST['target_url'] ?? '');
        $slug       = trim($_POST['slug'] ?? '');
        $title      = trim($_POST['title'] ?? '');
        $redirect_type = (int)($_POST['redirect_type'] ?? $link['redirect_type']);
        $active     = isset($_POST['active']) ? 1 : 0;
        // URL kontrolü
        if (!\App\validate_url($target_url)) {
            $error = 'Geçerli bir URL giriniz.';
        } else {
            if ($slug === '') {
                $error = 'Slug boş bırakılamaz. Değiştirmek istemiyorsanız mevcut slugı yazın.';
            } elseif (!\App\validate_slug($slug)) {
                $error = 'Slug yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } else {
                // slug başka kayıt tarafından kullanılıyor mu?
                $existing = db\get_link_by_slug($slug);
                if ($existing && (int)$existing['id'] !== $id) {
                    $error = 'Bu slug başka bir link tarafından kullanılıyor.';
                }
            }
        }
        if (!$error) {
            $updateData = [
                'slug'          => $slug,
                'target_url'    => $target_url,
                'title'         => $title,
                'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                'active'        => $active,
            ];
            db\update_link($id, $updateData);
            $success = 'Kayıt güncellendi.';
            // Formu güncel değerlerle doldurmak için link değişkenini de güncelle
            $link = array_merge($link, $updateData);
        }
    }
}

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Link Düzenle</title>
    <style>
        body {font-family: Arial, sans-serif; padding: 20px;}
        label {display: block; margin-top: 10px;}
        input[type="text"], textarea {width: 100%; padding: 8px;}
        .error {color: red;}
        .success {color: green;}
    </style>
</head>
<body>
    <h1>Link Düzenle</h1>
    <p><a href="index.php">&laquo; Geri Dön</a></p>
    <?php if ($error): ?><p class="error"><?php echo \App\e($error); ?></p><?php endif; ?>
    <?php if ($success): ?><p class="success"><?php echo \App\e($success); ?></p><?php endif; ?>
    <form method="post" action="">
        <?php echo \App\csrf_input(); ?>
        <label for="target_url">Hedef URL *</label>
        <input type="text" name="target_url" id="target_url" required value="<?php echo \App\e($link['target_url']); ?>">

        <label for="slug">Kısa Kod *</label>
        <input type="text" name="slug" id="slug" required value="<?php echo \App\e($link['slug']); ?>">

        <label for="title">Başlık/Not</label>
        <input type="text" name="title" id="title" value="<?php echo \App\e($link['title']); ?>">

        <label>Yönlendirme Tipi</label>
        <select name="redirect_type">
            <option value="301" <?php echo ($link['redirect_type'] == 301) ? 'selected' : ''; ?>>301 (kalıcı)</option>
            <option value="302" <?php echo ($link['redirect_type'] == 302) ? 'selected' : ''; ?>>302 (geçici)</option>
        </select>

        <label>
            <input type="checkbox" name="active" value="1" <?php echo ($link['active']) ? 'checked' : ''; ?>> Aktif
        </label>

        <br>
        <button type="submit">Güncelle</button>
    </form>
</body>
</html>