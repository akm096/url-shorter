<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/csrf.php';

use App\auth;
use App\db;
use App\logger;

auth\require_login();

$error = '';
$success = '';

// Handle Delete
if (isset($_GET['delete']) && isset($_GET['token'])) {
    if (\App\verify_csrf($_GET['token'])) {
        $id = (int) $_GET['delete'];
        db\delete_collection($id);
        $success = 'Koleksiyon silindi.';
    } else {
        $error = 'Güvenlik hatası (CSRF).';
    }
}

// Check if creating new
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create') {
    if (\App\verify_csrf($_POST['csrf_token'] ?? '')) {
        $title = trim($_POST['title']);
        $slug = trim($_POST['slug']);

        if (empty($slug))
            $slug = \App\generate_random_slug();

        // Check availability
        if (db\get_collection_by_slug($slug)) {
            $error = 'Bu slug zaten kullanılıyor.';
        } else {
            $id = db\insert_collection([
                'title' => $title,
                'slug' => $slug,
                'description' => trim($_POST['description']),
                'theme_color' => $_POST['theme_color'] ?? '#ffffff',
                'active' => 1
            ]);
            header("Location: collection_edit.php?id=$id");
            exit;
        }
    } else {
        $error = 'Güvenlik hatası.';
    }
}

$collections = db\get_all_collections();

require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex justify-between" style="align-items: center; margin-bottom: 20px;">
    <h2>Link Koleksiyonları</h2>
    <button onclick="document.getElementById('newCollectionModal').style.display='flex'" class="btn btn-primary">
        + Yeni Koleksiyon
    </button>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo \App\e($error); ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success">
        <?php echo \App\e($success); ?>
    </div>
<?php endif; ?>

<div class="row">
    <?php foreach ($collections as $col): ?>
        <div class="card" style="margin-bottom: 20px;">
            <div class="d-flex justify-between">
                <div>
                    <h3 style="margin: 0;">
                        <span
                            style="display:inline-block; width:15px; height:15px; background:<?php echo \App\e($col['theme_color']); ?>; border-radius:50%; border:1px solid #ccc; margin-right:5px;"></span>
                        <?php echo \App\e($col['title'] ?: 'İsimsiz'); ?>
                    </h3>
                    <small class="text-muted">/
                        <?php echo \App\e($col['slug']); ?>
                    </small>
                </div>
                <div>
                    <a href="../<?php echo \App\e($col['slug']); ?>" target="_blank"
                        class="btn btn-outline btn-sm">Görüntüle</a>
                    <a href="collection_edit.php?id=<?php echo $col['id']; ?>" class="btn btn-primary btn-sm">Düzenle</a>
                    <a href="?delete=<?php echo $col['id']; ?>&token=<?php echo \App\csrf_token(); ?>"
                        class="btn btn-danger btn-sm" onclick="return confirm('Silmek istediğinize emin misiniz?')">Sil</a>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if (empty($collections)): ?>
        <div class="card text-center" style="padding: 40px;">
            <p class="text-muted">Henüz hiç koleksiyon oluşturulmamış.</p>
        </div>
    <?php endif; ?>
</div>

<!-- New Collection Modal -->
<div id="newCollectionModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div class="card" style="width: 400px; max-width: 90%;">
        <h3>Yeni Koleksiyon</h3>
        <form method="post">
            <?php echo \App\csrf_input(); ?>
            <input type="hidden" name="action" value="create">

            <div class="form-group">
                <label>Başlık</label>
                <input type="text" name="title" required placeholder="Örn: Sosyal Medya">
            </div>

            <div class="form-group">
                <label>Slug (İsteğe bağlı)</label>
                <input type="text" name="slug" placeholder="ornek-sayfa">
            </div>

            <div class="form-group">
                <label>Açıklama</label>
                <textarea name="description" placeholder="Kısa bir açıklama..." rows="2"></textarea>
            </div>

            <div class="form-group">
                <label>Tema Rengi</label>
                <input type="color" name="theme_color" value="#ffffff" style="width: 100%; height: 40px;">
            </div>

            <div class="d-flex" style="gap: 10px; margin-top: 20px;">
                <button type="submit" class="btn btn-primary" style="flex:1">Oluştur</button>
                <button type="button" class="btn btn-outline" style="flex:1"
                    onclick="document.getElementById('newCollectionModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>