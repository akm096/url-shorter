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
$content = '';
$slug = '';
$title = '';
$active = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $content = trim($_POST['content'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $active = isset($_POST['active']) ? 1 : 0;

        if ($content === '') {
            $error = 'İçerik boş bırakılamaz.';
        } else {
            if ($slug === '') {
                $slug = \App\generate_random_slug(random_int(6, 8));
            } elseif (!\App\validate_slug($slug)) {
                $error = 'Slug yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } elseif (!\App\validate_slug_length($slug)) {
                $error = 'Slug ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter arasında olmalıdır.';
            } elseif (\App\is_reserved_slug($slug)) {
                $error = 'Bu slug sistem tarafından ayrılmış.';
            } elseif (db\get_note_by_slug($slug) !== null) {
                $error = 'Bu slug zaten kullanılıyor.';
            }
        }

        if (!$error) {
            // Security options
            $password = trim($_POST['password'] ?? '');
            $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;

            try {
                db\insert_note([
                    'slug' => $slug,
                    'content' => $content,
                    'title' => $title,
                    'created_at' => date('Y-m-d H:i:s'),
                    'active' => $active,
                    'password_hash' => $password_hash,
                    'is_burn_after_read' => isset($_POST['is_burn_after_read']) ? 1 : 0,
                ]);

                logger\log_system_action('CREATE_NOTE', "Slug: $slug | Title: $title");

                // Get base URL
                $config = require __DIR__ . '/../app/config.php';
                $baseUrl = rtrim($config['base_url'] ?? '', '/');
                $createdUrl = $baseUrl ? ($baseUrl . '/' . $slug) : ('/' . $slug);

                $success = 'Not başarıyla oluşturuldu: <a href="' . \App\e($createdUrl) . '" target="_blank">' . \App\e($createdUrl) . '</a>';
                $content = '';
                $slug = '';
                $title = '';
                $active = 1;
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug zaten kullanılıyor.';
                } else {
                    $error = 'Bir hata oluştu.';
                }
                logger\log_system_action('CREATE_NOTE_ERROR', "Error: " . $e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <a href="notes.php" class="btn btn-outline">← Geri Dön</a>
    </div>

    <div class="card">
        <h2 style="margin-top:0;">Yeni Not Ekle</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <?php echo \App\e($error); ?>
            </div>
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
                <label for="title">Başlık <span class="text-muted">(İsteğe bağlı)</span></label>
                <input type="text" name="title" id="title" placeholder="Not başlığı..."
                    value="<?php echo \App\e($title); ?>">
            </div>

            <div class="form-group">
                <label for="slug">Kısa Kod <span class="text-muted">(İsteğe bağlı)</span></label>
                <input type="text" name="slug" id="slug" placeholder="ornek-not" value="<?php echo \App\e($slug); ?>">
                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 4px;">Boş bırakılırsa rastgele
                    üretilir.</div>
            </div>

            <div class="form-group d-flex" style="margin-bottom: 20px;">
                <input type="checkbox" name="is_burn_after_read" id="is_burn_after_read" value="1"
                    style="width: auto; margin: 0;">
                <label for="is_burn_after_read" style="margin:0; font-weight: normal; cursor: pointer;">🔥 Görüldükten
                    sonra sil (Burn After Read)</label>
            </div>

            <div class="form-group">
                <label for="content">İçerik <span class="text-muted">*</span></label>
                <textarea name="content" id="content" required placeholder="Not içeriğinizi buraya yazın..."
                    style="min-height: 200px;"><?php echo \App\e($content); ?></textarea>
                <script>
                    const easyMDE = new EasyMDE({
                        element: document.getElementById('content'),
                        spellChecker: false,
                        status: false,
                        placeholder: "Not içeriğinizi buraya yazın (Markdown desteklenir)..."
                    });
                </script>
            </div>

            <div class="form-group d-flex" style="margin-top: 20px;">
                <input type="checkbox" name="active" id="active" value="1" <?php echo $active ? 'checked' : ''; ?>
                    style="width: auto; margin: 0;">
                <label for="active" style="margin:0; font-weight: normal; cursor: pointer;">Not hemen aktif
                    olsun</label>
            </div>

            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; color: var(--primary-color); font-weight: 500;">🔒 Güvenlik Seçenekleri
                </summary>
                <div
                    style="padding: 1rem; background: var(--bg-color); border-radius: var(--radius); margin-top: 0.5rem;">
                    <div class="form-group">
                        <label for="password">Şifre Koruması <span class="text-muted">(opsiyonel)</span></label>
                        <input type="password" name="password" id="password" placeholder="Notu şifrele...">
                    </div>
                </div>
            </details>

            <div style="margin-top: 30px;">
                <button type="submit" class="btn btn-primary">Not Oluştur</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>