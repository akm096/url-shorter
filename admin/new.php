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

$error = '';
$success = '';
$createdUrl = '';
$target_url = '';
$slug = '';
$title = '';
$redirect_type = 302;
$active = 1;

// Check for flash messages (PRG pattern)
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    $createdUrl = $_SESSION['flash_created_url'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_created_url']);
}

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
            // Security options
            $password = trim($_POST['password'] ?? '');
            $expires_at = trim($_POST['expires_at'] ?? '');
            $click_limit = trim($_POST['click_limit'] ?? '');

            $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
            $expires_at_val = $expires_at !== '' ? $expires_at : null;
            $click_limit_val = $click_limit !== '' ? (int) $click_limit : null;

            // Open Graph
            $og_title = trim($_POST['og_title'] ?? '');
            $og_description = trim($_POST['og_description'] ?? '');
            $og_image = trim($_POST['og_image'] ?? '');

            try {
                db\insert_link([
                    'slug' => $slug,
                    'target_url' => $target_url,
                    'title' => $title,
                    'redirect_type' => in_array($redirect_type, [301, 302]) ? $redirect_type : 302,
                    'created_at' => date('Y-m-d H:i:s'),
                    'active' => $active,
                    'password_hash' => $password_hash,
                    'expires_at' => $expires_at_val,
                    'click_limit' => $click_limit_val,
                    'og_title' => $og_title !== '' ? $og_title : null,
                    'og_description' => $og_description !== '' ? $og_description : null,
                    'og_image' => $og_image !== '' ? $og_image : null,
                ]);

                logger\log_system_action('CREATE_LINK', "Slug: $slug | Target: $target_url");

                // Get base URL
                $config = require __DIR__ . '/../app/config.php';
                $baseUrl = rtrim($config['base_url'] ?? '', '/');
                $createdUrl = $baseUrl ? ($baseUrl . '/' . $slug) : ('/' . $slug);

                // PRG: Store in session and redirect to prevent F5 duplicates
                $_SESSION['flash_success'] = 'Link başarıyla oluşturuldu: <a href="' . \App\e($createdUrl) . '" target="_blank">' . \App\e($createdUrl) . '</a>';
                $_SESSION['flash_created_url'] = $createdUrl;
                header('Location: new.php');
                exit;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug zaten kullanılıyor.';
                } else {
                    $error = 'Bir hata oluştu.';
                }
                logger\log_system_action('CREATE_LINK_ERROR', "Error: " . $e->getMessage());
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
        <h2 style="margin-top:0;">Yeni Link Ekle</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo $success; ?>
                <div id="qrcode" style="margin-top: 15px; display: flex; justify-content: center;"></div>
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    if (typeof QRCode !== 'undefined') {
                        new QRCode(document.getElementById("qrcode"), {
                            text: "<?php echo \App\e($createdUrl ?? ''); ?>",
                            width: 128,
                            height: 128,
                            colorDark: "#000000",
                            colorLight: "#ffffff",
                            correctLevel: QRCode.CorrectLevel.H
                        });
                    }
                });
            </script>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>

            <div class="form-group">
                <label for="target_url">Hedef URL <span class="text-muted">*</span></label>
                <input type="text" name="target_url" id="target_url" required placeholder="https://ornek.com/sayfa"
                    value="<?php echo \App\e($target_url); ?>">
            </div>

            <div class="form-group">
                <label for="slug">Kısa Kod <span class="text-muted">(İsteğe bağlı)</span></label>
                <input type="text" name="slug" id="slug" placeholder="ornek123" value="<?php echo \App\e($slug); ?>">
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Boş bırakılırsa rastgele
                    üretilir.</div>
            </div>

            <div class="form-group">
                <label for="title">Not <span class="text-muted">(İsteğe bağlı)</span></label>
                <input type="text" name="title" id="title" placeholder="Link hakkında kısa açıklama..."
                    value="<?php echo \App\e($title); ?>">
            </div>

            <div class="form-group">
                <label>Yönlendirme Tipi</label>
                <select name="redirect_type">
                    <option value="301" <?php echo $redirect_type == 301 ? 'selected' : ''; ?>>301 (Kalıcı - SEO uyumlu)
                    </option>
                    <option value="302" <?php echo $redirect_type == 302 ? 'selected' : ''; ?>>302 (Geçici - Varsayılan)
                    </option>
                </select>
            </div>

            <div class="form-group d-flex" style="margin-top: 20px;">
                <input type="checkbox" name="active" id="active" value="1" <?php echo $active ? 'checked' : ''; ?>
                    style="width: auto; margin: 0;">
                <label for="active" style="margin:0; font-weight: normal; cursor: pointer;">Link hemen aktif
                    olsun</label>
            </div>

            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; color: var(--primary-color); font-weight: 500;">🔒 Güvenlik Seçenekleri
                </summary>
                <div
                    style="padding: 1rem; background: var(--bg-color); border-radius: var(--radius); margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="password">Şifre Koruması <span class="text-muted">(opsiyonel)</span></label>
                        <input type="password" name="password" id="password" placeholder="Bağlantıyı şifrele...">
                    </div>
                    <div class="form-group">
                        <label for="expires_at">Son Kullanma Tarihi <span class="text-muted">(opsiyonel)</span></label>
                        <input type="datetime-local" name="expires_at" id="expires_at">
                    </div>
                    <div class="form-group">
                        <label for="click_limit">Tıklama Limiti <span class="text-muted">(opsiyonel)</span></label>
                        <input type="number" name="click_limit" id="click_limit" placeholder="Maksimum tıklama sayısı"
                            min="1">
                    </div>
                </div>
            </details>

            <!-- OG Ayarları Kaldırıldı -->

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">Link Oluştur</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>