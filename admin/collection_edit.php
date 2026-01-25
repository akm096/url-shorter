<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/csrf.php';

use App\auth;
use App\db;

auth\require_login();

$id = (int) ($_GET['id'] ?? 0);
$collection = db\get_collection_by_id($id);

if (!$collection) {
    header("Location: collections.php");
    exit;
}

$error = '';
$success = '';

// Handle Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!\App\verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Güvenlik hatası.';
    } else {
        if (isset($_POST['action']) && $_POST['action'] === 'update_info') {
            // Update Info
            db\update_collection($id, [
                'slug' => $_POST['slug'],
                'title' => $_POST['title'],
                'description' => $_POST['description'],
                'theme_color' => $_POST['theme_color'],
                'active' => isset($_POST['active']) ? 1 : 0
            ]);
            $success = 'Bilgiler güncellendi.';
            $collection = db\get_collection_by_id($id); // Refresh
        } elseif (isset($_POST['action']) && $_POST['action'] === 'add_links_bulk') {
            // Add Bulk Links
            if (!empty($_POST['link_ids']) && is_array($_POST['link_ids'])) {
                $count = 0;
                foreach ($_POST['link_ids'] as $linkId) {
                    db\add_link_to_collection($id, (int) $linkId);
                    $count++;
                }
                $success = "$count link eklendi.";
            } else {
                $error = 'Hiçbir link seçilmedi.';
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_link') {
            // Remove Link
            $linkId = (int) $_POST['link_id'];
            db\remove_link_from_collection($id, $linkId);
            $success = 'Link kaldırıldı.';
        } elseif (isset($_POST['action']) && $_POST['action'] === 'add_notes_bulk') {
            // Add Bulk Notes
            if (!empty($_POST['note_ids']) && is_array($_POST['note_ids'])) {
                $count = 0;
                foreach ($_POST['note_ids'] as $noteId) {
                    db\add_note_to_collection($id, (int) $noteId);
                    $count++;
                }
                $success = "$count not eklendi.";
            } else {
                $error = 'Hiçbir not seçilmedi.';
            }
        } elseif (isset($_POST['action']) && $_POST['action'] === 'remove_note') {
            // Remove Note
            $noteId = (int) $_POST['note_id']; // This is junction ID actually? No, wait. 
            // In remove_link, we passed hidden field. 
            // Let's standardise: db function expects noteId (pk of note) AND collectionId to find junction? 
            // OR db function expects junction ID?
            // db function `remove_note_from_collection`: `DELETE FROM collection_notes WHERE collection_id = ? AND note_id = ?`
            // So it expects the NOTE ID, not the junction ID.
            // Let's verify `remove_link_from_collection` in DB.
            // My previous thought process for `remove_link` was about junction ID, but I checked DB and it was `DELETE FROM collection_links WHERE id = ?`.
            // WAIT. The last edit to `db.php` for `remove_link` used `id = ?`.
            // BUT `remove_note_from_collection` I added uses `WHERE collection_id = ? AND note_id = ?`.
            // So for notes, I must pass the NOTE ID.

            db\remove_note_from_collection($id, $noteId);
            $success = 'Not kaldırıldı.';
        }
    }
}

$collectionLinks = db\get_collection_links($id);
$collectionNotes = db\get_collection_notes($id);

require_once __DIR__ . '/layout/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="collections.php" class="btn btn-outline">← Koleksiyonlara Dön</a>
</div>

<div class="row">
    <!-- Left: Preview / Info -->
    <div style="flex: 1; min-width: 300px; padding: 10px;">
        <div class="card">
            <h3>Ayarlar</h3>
            <form method="post">
                <?php echo \App\csrf_input(); ?>
                <input type="hidden" name="action" value="update_info">

                <div class="form-group">
                    <label>Başlık</label>
                    <input type="text" name="title" value="<?php echo \App\e($collection['title']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Slug (URL)</label>
                    <input type="text" name="slug" value="<?php echo \App\e($collection['slug']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea name="description" rows="3"><?php echo \App\e($collection['description']); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Arkaplan Rengi</label>
                    <input type="color" name="theme_color" value="<?php echo \App\e($collection['theme_color']); ?>"
                        style="width:100%; height:40px;">
                </div>
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="active" value="1" <?php echo $collection['active'] ? 'checked' : ''; ?>>
                        Yayında
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">Kaydet</button>
            </form>
        </div>
    </div>

    <!-- Right: Links -->
    <div style="flex: 2; min-width: 300px; padding: 10px;">
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Linkler</h3>
                <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('addLinksModal').style.display='flex'">
                    + Link Seç ve Ekle
                </button>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo \App\e($error); ?></div> <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo \App\e($success); ?></div> <?php endif; ?>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="text-align: left; padding: 10px;">Başlık</th>
                        <th style="text-align: left; padding: 10px;">URL</th>
                        <th style="padding: 10px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collectionLinks as $link): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px;">
                                <?php echo \App\e($link['title'] ?: $link['slug']); ?>
                                <?php if ((int) $link['active'] === 0): ?>
                                    <span
                                        style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; margin-left: 5px;">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php
                                $config = db\get_config();
                                $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
                                if ($baseUrl === '') {
                                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'] ?? '';
                                    $baseUrl = $host ? ($scheme . '://' . $host) : '';
                                }
                                $shortUrl = $baseUrl . '/' . $link['slug'];
                                ?>
                                <a href="<?php echo \App\e($shortUrl); ?>" target="_blank"
                                    style="font-weight:bold;"><?php echo \App\e($shortUrl); ?></a>
                                <br>
                                <small class="text-muted">Hedef: <?php echo \App\e($link['target_url']); ?></small>
                            </td>
                            <td style="padding: 10px;">
                                <form method="post" style="display:inline;">
                                    <?php echo \App\csrf_input(); ?>
                                    <input type="hidden" name="action" value="remove_link">
                                    <input type="hidden" name="link_id" value="<?php echo $link['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Kaldırmak istediğinize emin misiniz?')">Kaldır</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($collectionLinks)): ?>
                <p class="text-muted text-center" style="margin-top: 20px;">Bu koleksiyonda henüz link yok.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="margin-top: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Notlar</h3>
                <button type="button" class="btn btn-primary"
                    onclick="document.getElementById('addNotesModal').style.display='flex'">
                    + Not Seç ve Ekle
                </button>
            </div>

            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border-color);">
                        <th style="text-align: left; padding: 10px;">Başlık</th>
                        <th style="text-align: left; padding: 10px;">URL</th>
                        <th style="padding: 10px;">İşlem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($collectionNotes as $note): ?>
                        <tr style="border-bottom: 1px solid var(--border-color);">
                            <td style="padding: 10px;">
                                <?php echo \App\e($note['title'] ?: $note['slug']); ?>
                                <?php if ((int) $note['active'] === 0): ?>
                                    <span
                                        style="background: #e74c3c; color: white; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; margin-left: 5px;">Pasif</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 10px;">
                                <?php
                                $config = db\get_config();
                                $baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
                                if ($baseUrl === '') {
                                    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                                    $host = $_SERVER['HTTP_HOST'] ?? '';
                                    $baseUrl = $host ? ($scheme . '://' . $host) : '';
                                }
                                $shortUrl = $baseUrl . '/' . $note['slug'];
                                ?>
                                <a href="<?php echo \App\e($shortUrl); ?>" target="_blank"
                                    style="font-weight:bold;"><?php echo \App\e($shortUrl); ?></a>
                            </td>
                            <td style="padding: 10px;">
                                <form method="post" style="display:inline;">
                                    <?php echo \App\csrf_input(); ?>
                                    <input type="hidden" name="action" value="remove_note">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Kaldırmak istediğinize emin misiniz?')">Kaldır</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if (empty($collectionNotes)): ?>
                <p class="text-muted text-center" style="margin-top: 20px;">Bu koleksiyonda henüz not yok.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Links Modal -->
<?php
// Fetch available links for the modal (limit 100 for performance)
$availableLinks = db\get_all_links(null, 100);
?>
<div id="addLinksModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div class="card" style="width: 600px; max-width: 90%; max-height: 80vh; display: flex; flex-direction: column;">
        <h3>Link Seç</h3>
        <p class="text-muted">Koleksiyona eklemek istediğiniz linkleri seçin.</p>

        <form method="post" style="display: flex; flex-direction: column; overflow: hidden;">
            <?php echo \App\csrf_input(); ?>
            <input type="hidden" name="action" value="add_links_bulk">

            <div
                style="flex: 1; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; margin-bottom: 20px;">
                <?php foreach ($availableLinks as $al): ?>
                    <?php
                    // Check if already in collection
                    $isInCollection = false;
                    foreach ($collectionLinks as $cl) {
                        if ($cl['id'] === $al['id']) {
                            $isInCollection = true;
                            break;
                        }
                    }
                    if ($isInCollection)
                        continue;
                    ?>
                    <label
                        style="display: flex; align-items: center; padding: 8px; border-bottom: 1px solid var(--border-color); cursor: pointer;">
                        <input type="checkbox" name="link_ids[]" value="<?php echo $al['id']; ?>"
                            style="margin-right: 10px; width: 18px; height: 18px;">
                        <div>
                            <div style="font-weight: 500;">
                                <?php echo \App\e($al['title'] ?: $al['slug']); ?>
                                <?php if ((int) $al['active'] === 0): ?>
                                    <span
                                        style="background: #e74c3c; color: white; padding: 1px 5px; border-radius: 3px; font-size: 0.7rem; margin-left: 5px;">Pasif</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo \App\e($al['target_url']); ?></small>
                        </div>
                    </label>
                <?php endforeach; ?>

                <?php if (empty($availableLinks)): ?>
                    <p class="text-center text-muted">Gösterilecek link bulunamadı.</p>
                <?php endif; ?>
            </div>

            <div class="d-flex" style="gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex:1">Seçilenleri Ekle</button>
                <button type="button" class="btn btn-outline" style="flex:1"
                    onclick="document.getElementById('addLinksModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div>
</div>

<!-- Add Notes Modal -->
<?php
// Fetch available notes
$availableNotes = db\get_all_notes(null); // Assuming get_all_notes supports null search, if not checks needed but db.php showed it does.
// Although `get_all_notes` creates a big query if many notes, for now it's fine.
?>
<div id="addNotesModal"
    style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:999;">
    <div class="card" style="width: 600px; max-width: 90%; max-height: 80vh; display: flex; flex-direction: column;">
        <h3>Not Seç</h3>
        <p class="text-muted">Koleksiyona eklemek istediğiniz notları seçin.</p>

        <form method="post" style="display: flex; flex-direction: column; overflow: hidden;">
            <?php echo \App\csrf_input(); ?>
            <input type="hidden" name="action" value="add_notes_bulk">

            <div
                style="flex: 1; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 4px; padding: 10px; margin-bottom: 20px;">
                <?php foreach ($availableNotes as $an): ?>
                    <?php
                    // Check if already in collection
                    $isInCollection = false;
                    foreach ($collectionNotes as $cn) {
                        if ($cn['id'] === $an['id']) {
                            $isInCollection = true;
                            break;
                        }
                    }
                    if ($isInCollection)
                        continue;
                    ?>
                    <label
                        style="display: flex; align-items: center; padding: 8px; border-bottom: 1px solid var(--border-color); cursor: pointer;">
                        <input type="checkbox" name="note_ids[]" value="<?php echo $an['id']; ?>"
                            style="margin-right: 10px; width: 18px; height: 18px;">
                        <div>
                            <div style="font-weight: 500;">
                                <?php echo \App\e($an['title'] ?: $an['slug']); ?>
                                <?php if ((int) $an['active'] === 0): ?>
                                    <span
                                        style="background: #e74c3c; color: white; padding: 1px 5px; border-radius: 3px; font-size: 0.7rem; margin-left: 5px;">Pasif</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted"><?php echo \App\e(substr($an['content'], 0, 50)); ?>...</small>
                        </div>
                    </label>
                <?php endforeach; ?>

                <?php if (empty($availableNotes)): ?>
                    <p class="text-center text-muted">Gösterilecek not bulunamadı.</p>
                <?php endif; ?>
            </div>

            <div class="d-flex" style="gap: 10px;">
                <button type="submit" class="btn btn-primary" style="flex:1">Seçilenleri Ekle</button>
                <button type="button" class="btn btn-outline" style="flex:1"
                    onclick="document.getElementById('addNotesModal').style.display='none'">İptal</button>
            </div>
        </form>
    </div>
</div><?php require_once __DIR__ . '/layout/footer.php'; ?>