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
    header('Location: notes.php');
    exit;
}

$pdo = db\get_db();
$stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
$stmt->execute([$id]);
$note = $stmt->fetch();
if (!$note) {
    header('Location: notes.php');
    exit;
}

$error = '';
$success = '';

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
        } elseif ($slug === '') {
            $error = 'Slug boş bırakılamaz.';
        } elseif (!\App\validate_slug($slug)) {
            $error = 'Slug yalnızca harf, sayı, tire ve alt çizgi içerebilir.';
        } elseif (!\App\validate_slug_length($slug)) {
            $error = 'Slug ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter olmalı.';
        } elseif (\App\is_reserved_slug($slug)) {
            $error = 'Bu slug sistem tarafından ayrılmış.';
        } else {
            $existing = db\get_note_by_slug($slug);
            if ($existing && (int) $existing['id'] !== $id) {
                $error = 'Bu slug başka bir not tarafından kullanılıyor.';
            }
        }

        if (!$error) {
            // Security options
            $password = trim($_POST['password'] ?? '');
            $is_burn_after_read = isset($_POST['is_burn_after_read']) ? 1 : 0;

            // Only update password if a new one is provided
            $password_hash = $note['password_hash'] ?? null;
            if ($password !== '') {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
            }
            // Allow clearing password
            if (isset($_POST['clear_password']) && $_POST['clear_password'] === '1') {
                $password_hash = null;
            }

            try {
                db\update_note($id, [
                    'slug' => $slug,
                    'content' => $content,
                    'title' => $title,
                    'active' => $active,
                    'password_hash' => $password_hash,
                    'is_burn_after_read' => $is_burn_after_read,
                ]);

                logger\log_system_action('UPDATE_NOTE', "Note ID: $id | Slug: $slug");

                $success = 'Kayıt güncellendi.';
                $note = array_merge($note, [
                    'slug' => $slug,
                    'content' => $content,
                    'title' => $title,
                    'active' => $active,
                    'password_hash' => $password_hash,
                ]);
            } catch (\PDOException $e) {
                if ($e->getCode() == 23000 || strpos($e->getMessage(), 'Duplicate') !== false) {
                    $error = 'Bu slug başka bir not tarafından kullanılıyor.';
                } else {
                    $error = 'Bir hata oluştu.';
                }
                logger\log_system_action('UPDATE_NOTE_ERROR', "ID: $id | Error: " . $e->getMessage());
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
        <h2 style="margin-top:0;">Not Düzenle</h2>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo \App\e($success); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>

            <div class="form-group">
                <label for="title">Başlık <span class="text-muted">(Opsiyonel)</span></label>
                <input type="text" name="title" id="title" value="<?php echo \App\e($note['title'] ?? ''); ?>">
            </div>

            <div class="form-group">
                <label for="slug">Kısa Kod (Slug) <span class="text-muted">*</span></label>
                <input type="text" name="slug" id="slug" required value="<?php echo \App\e($note['slug']); ?>">
            </div>

            <div class="form-group">
                <label for="content">İçerik <span class="text-muted">*</span></label>
                <textarea name="content" id="content" required
                    style="min-height: 200px;"><?php echo \App\e($note['content']); ?></textarea>
                <script>
                    const easyMDE = new EasyMDE({
                        element: document.getElementById('content'),
                        spellChecker: false,
                        status: false,
                    });
                </script>
            </div>

            <div class="form-group d-flex" style="margin-top: 20px;">
                <input type="checkbox" name="active" id="active" value="1" <?php echo $note['active'] ? 'checked' : ''; ?> style="width: auto; margin: 0;">
                <label for="active" style="margin:0; font-weight: normal; cursor: pointer;">Bu not aktif olsun</label>
            </div>

            <div class="form-group d-flex" style="margin-top: 20px;">
                <input type="checkbox" name="is_burn_after_read" id="is_burn_after_read" value="1" <?php echo ($note['is_burn_after_read'] ?? 0) ? 'checked' : ''; ?> style="width: auto; margin: 0;">
                <label for="is_burn_after_read" style="margin:0; font-weight: normal; cursor: pointer;">🔥 Görüldükten sonra sil (Burn After Read)</label>
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
            <?php if (!empty($note['password_hash'])): ?>
                <div class="d-flex" style="margin-top: 5px;">
                    <input type="checkbox" name="clear_password" value="1" id="clear_password"
                        style="width: auto; margin: 0;">
                    <label for="clear_password"
                        style="margin: 0; font-weight: normal; cursor: pointer; font-size: 0.85rem;">Şifreyi
                        kaldır</label>
                </div>
            <?php endif; ?>
    </div>
</div>
</details>

<div style="margin-top: 30px;">
    <button type="submit" class="btn btn-primary">Değişiklikleri Kaydet</button>
</div>
</form>

<div
    style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 0.9rem; color: var(--text-muted);">
    <div><strong>ID:</strong> <?php echo \App\e($note['id']); ?></div>
    <div style="margin-top:5px;"><strong>Görüntülenme:</strong> <?php echo \App\e($note['view_count']); ?></div>
    <div style="margin-top:5px;"><strong>Oluşturulma Tarihi:</strong> <?php echo \App\e($note['created_at']); ?>
    </div>
</div>
</div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>