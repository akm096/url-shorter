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
use App\logger;

require_once __DIR__ . '/../app/logger.php';

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
            // Security options
            $password = trim($_POST['password'] ?? '');
            $expires_at = trim($_POST['expires_at'] ?? '');
            $click_limit = trim($_POST['click_limit'] ?? '');

            // Only update password if a new one is provided
            $password_hash = $link['password_hash'] ?? null;
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            // Allow clearing password
            if (isset($_POST['clear_password']) && $_POST['clear_password'] === '1') {
                $password_hash = null;
            }

            $expires_at_val = $expires_at !== '' ? $expires_at : null;
            $click_limit_val = $click_limit !== '' ? (int) $click_limit : null;

            // Open Graph
            $og_title = trim($_POST['og_title'] ?? '');
            $og_description = trim($_POST['og_description'] ?? '');
            $og_image = trim($_POST['og_image'] ?? '');

            try {
                db\update_link($id, [
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'title' => $title,
                    'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                    'active' => $active,
                    'password_hash' => $password_hash,
                    'expires_at' => $expires_at_val,
                    'click_limit' => $click_limit_val,
                    'og_title' => $og_title !== '' ? $og_title : null,
                    'og_description' => $og_description !== '' ? $og_description : null,
                    'og_image' => $og_image !== '' ? $og_image : null,
                ]);

                logger\log_system_action('UPDATE_LINK', "Link ID: $id | Slug: $slug");

                $success = 'Kayıt güncellendi.';
                $link = array_merge($link, [
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'title' => $title,
                    'redirect_type' => $redirect_type,
                    'active' => $active,
                    'password_hash' => $password_hash,
                    'expires_at' => $expires_at_val,
                    'click_limit' => $click_limit_val,
                    'og_title' => $og_title !== '' ? $og_title : null,
                    'og_description' => $og_description !== '' ? $og_description : null,
                    'og_image' => $og_image !== '' ? $og_image : null,
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug başka bir link tarafından kullanılıyor.';
                } else {
                    $error = 'Bir hata oluştu.';
                }
                logger\log_system_action('UPDATE_LINK_ERROR', "ID: $id | Error: " . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="index.php" class="btn btn-outline">← Geri Dön</a>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Link Düzenle</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo \App\e($success); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>

            <div class="form-group">
                <label for="target_url">Hedef URL <span class="text-muted">*</span></label>
                <input type="text" name="target_url" id="target_url" required
                    value="<?php echo \App\e($link['target_url']); ?>">
            </div>

            <div class="form-group">
                <label for="slug">Kısa Kod <span class="text-muted">*</span></label>
                <input type="text" name="slug" id="slug" required value="<?php echo \App\e($link['slug']); ?>">
            </div>

            <div class="form-group">
                <label for="title">Not <span class="text-muted">(Opsiyonel)</span></label>
                <input type="text" name="title" id="title" value="<?php echo \App\e($link['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label>Yönlendirme Tipi</label>
                <select name="redirect_type">
                    <option value="301" <?php echo $link['redirect_type'] == 301 ? 'selected' : ''; ?>>301 (Kalıcı - SEO
                        için iyi)</option>
                    <option value="302" <?php echo $link['redirect_type'] == 302 ? 'selected' : ''; ?>>302 (Geçici -
                        Varsayılan)</option>
                </select>
            </div>

            <div class="form-group d-flex" style="margin-top: 20px;">
                <input type="checkbox" name="active" id="active" value="1" <?php echo $link['active'] ? 'checked' : ''; ?> style="width: auto; margin: 0;">
                <label for="active" style="margin:0; font-weight: normal; cursor: pointer;">Bu link aktif olsun</label>
            </div>

            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; color: var(--primary-color); font-weight: 500;">🔒 Güvenlik Seçenekleri
                </summary>
                <div
                    style="padding: 1rem; background: var(--bg-color); border-radius: var(--radius); margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="password">Yeni Şifre <span class="text-muted">(boş bırakırsan mevcut şifre
                                kalır)</span></label>
                        <input type="password" name="password" id="password" placeholder="Yeni şifre belirle...">
                        <?php if (!empty($link['password_hash'])): ?>
                            <div class="d-flex" style="margin-top: 5px;">
                                <input type="checkbox" name="clear_password" value="1" id="clear_password"
                                    style="width: auto; margin: 0;">
                                <label for="clear_password"
                                    style="margin: 0; font-weight: normal; cursor: pointer; font-size: 0.85rem;">Şifreyi
                                    kaldır</label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Son Kullanma Tarihi</label>
                        <input type="datetime-local" name="expires_at" id="expires_at"
                            value="<?php echo $link['expires_at'] ? substr(\App\e($link['expires_at']), 0, 16) : ''; ?>">
                    </div>
                    <div class="form-group">
                        <label for="click_limit">Tıklama Limiti</label>
                        <input type="number" name="click_limit" id="click_limit" placeholder="Maksimum tıklama sayısı"
                            min="1" value="<?php echo $link['click_limit'] ? \App\e($link['click_limit']) : ''; ?>">
                    </div>
                </div>
            </details>

            <!-- OG Ayarları Kaldırıldı -->

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
            </div>
        </form>

        <div
            style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-muted);">
            <div><strong>ID:</strong> <?php echo \App\e($link['id']); ?></div>
            <div style="margin-top:5px;"><strong>Toplam Tıklama:</strong> <?php echo \App\e($link['click_count']); ?>
            </div>
            <div style="margin-top:5px;"><strong>Oluşturulma Tarihi:</strong> <?php echo \App\e($link['created_at']); ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>