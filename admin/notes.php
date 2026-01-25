<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/logger.php';

// Send security headers
\App\security\send_security_headers();

use App\auth;
use App\db;
use App\logger;

// Login check
auth\require_login();

// CSRF token
$csrfToken = \App\csrf_token();

// Handle POST actions (delete/toggle/bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    if (\App\verify_csrf($_POST['csrf_token'])) {
        // Single Action
        if (isset($_POST['action'], $_POST['id'])) {
            $action = $_POST['action'];
            $id = (int) $_POST['id'];

            if ($action === 'delete') {
                db\delete_note($id);
                logger\log_system_action('DELETE_NOTE', "Note ID: $id");
                header('Location: notes.php?msg=deleted');
                exit;
            } elseif ($action === 'toggle') {
                $pdo = db\get_db();
                $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
                $stmt->execute([$id]);
                $note = $stmt->fetch();
                if ($note) {
                    $newStatus = $note['active'] ? 0 : 1;
                    db\toggle_note_status($id, $newStatus);
                }
                header('Location: notes.php?msg=updated');
                exit;
            }
        }
        // Bulk Action
        elseif (isset($_POST['bulk_action'], $_POST['selected_ids']) && is_array($_POST['selected_ids'])) {
            $ids = array_map('intval', $_POST['selected_ids']);
            $bulkAction = $_POST['bulk_action'];

            if ($bulkAction === 'delete') {
                foreach ($ids as $id)
                    db\delete_note($id);
                logger\log_system_action('BULK_DELETE_NOTE', "Count: " . count($ids));
            } elseif ($bulkAction === 'activate') {
                foreach ($ids as $id)
                    db\toggle_note_status($id, 1);
                logger\log_system_action('BULK_ACTIVATE_NOTE', "Count: " . count($ids));
            } elseif ($bulkAction === 'passivate') {
                foreach ($ids as $id)
                    db\toggle_note_status($id, 0);
                logger\log_system_action('BULK_PASSIVATE_NOTE', "Count: " . count($ids));
            }
            header('Location: notes.php?msg=bulk_updated');
            exit;
        }
    } else {
        // CSRF verification failed
        header('Location: notes.php?msg=csrf_error');
        exit;
    }
}

// Search & Pagination
$search = $_GET['search'] ?? null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;

$allNotes = db\get_all_notes($search);
$totalNotes = count($allNotes);
$totalPages = max(1, (int) ceil($totalNotes / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$notes = array_slice($allNotes, $offset, $perPage);

$stats = db\get_note_stats();

// Base URL for short links
$config = db\get_config();
$baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $baseUrl = $host ? ($scheme . '://' . $host) : '';
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex justify-between" style="margin-bottom: 20px;">
    <h2>Not Listesi</h2>
    <a href="new_note.php" class="btn btn-primary">+ Yeni Not Ekle</a>
</div>

<form method="get" action="" class="card d-flex" style="padding: 1rem;">
    <input type="text" name="search" placeholder="Başlık, içerik veya slug ara..."
        value="<?php echo \App\e($search); ?>" style="flex: 1;">
    <button type="submit" class="btn btn-primary">Ara</button>
    <?php if ($search): ?>
        <a href="notes.php" class="btn btn-outline">Temizle</a>
    <?php endif; ?>
</form>

<!-- Bulk Action Form Wrapper -->
<form method="post" action="" id="bulkForm">
    <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">

    <div class="d-flex" style="margin-bottom: 10px; gap: 10px; align-items: center;">
        <span class="text-muted" style="font-size: 0.9rem;">Seçili olanları:</span>
        <select name="bulk_action" class="form-control" style="width: auto; padding: 5px;" required>
            <option value="" disabled selected>İşlem Seç...</option>
            <option value="activate">Aktif Yap</option>
            <option value="passivate">Pasif Yap</option>
            <option value="delete">Sil</option>
        </select>
        <button type="submit" class="btn btn-outline">Uygula</button>
    </div>

    <div class="card" style="padding: 0;">
        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="selectAll"></th>
                        <th>ID</th>
                        <th>Kısa Link</th>
                        <th>Başlık</th>
                        <th>İçerik Özeti</th>
                        <th>Görüntüleme</th>
                        <th>Tarih</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($notes)): ?>
                        <?php foreach ($notes as $note): ?>
                            <?php $shortUrl = $baseUrl . '/' . $note['slug']; ?>
                            <tr>
                                <td><input type="checkbox" name="selected_ids[]" value="<?php echo \App\e($note['id']); ?>"
                                        class="row-checkbox"></td>
                                <td><?php echo \App\e($note['id']); ?></td>
                                <td>
                                    <div class="d-flex">
                                        <a href="<?php echo \App\e($shortUrl); ?>" target="_blank"
                                            style="font-weight: 500; color: var(--primary-color);">/<?php echo \App\e($note['slug']); ?></a>
                                        <button type="button" class="btn btn-outline"
                                            style="padding: 2px 6px; font-size: 0.75rem;"
                                            onclick="copyUrl('<?php echo \App\e($shortUrl); ?>', this)">Kopyala</button>
                                        <button type="button" class="btn btn-outline"
                                            style="padding: 2px 6px; font-size: 0.75rem;"
                                            onclick="openQrModal('<?php echo \App\e($shortUrl); ?>')">QR</button>
                                    </div>
                                </td>
                                <td><?php echo $note['title'] ? \App\e($note['title']) : '<em class="text-muted">-</em>'; ?>
                                </td>
                                <td class="limit-cell" title="<?php echo \App\e($note['content']); ?>"
                                    style="max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo \App\e(mb_substr($note['content'], 0, 50) . (mb_strlen($note['content']) > 50 ? '...' : '')); ?>
                                </td>
                                <td><strong><?php echo \App\e($note['view_count']); ?></strong></td>
                                <td><?php echo \App\e(substr($note['created_at'], 0, 10)); ?></td>
                                <td>
                                    <?php if (!empty($note['active'])): ?>
                                        <span class="badge badge-success">Aktif</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger">Pasif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <div class="d-flex">
                                        <a href="stats.php?type=note&id=<?php echo \App\e($note['id']); ?>"
                                            class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;">Analiz</a>
                                        <a href="edit_note.php?id=<?php echo \App\e($note['id']); ?>" class="btn btn-outline"
                                            style="padding: 4px 8px; font-size: 12px;">Düzenle</a>

                                        <!-- JS Actions via Single Form -->
                                        <button type="button" class="btn btn-outline" style="padding: 4px 8px; font-size: 12px;"
                                            onclick="submitAction('toggle', <?php echo \App\e($note['id']); ?>)">
                                            <?php echo !empty($note['active']) ? 'Pasif' : 'Aktif'; ?>
                                        </button>

                                        <button type="button" class="btn btn-danger" style="padding: 4px 8px; font-size: 12px;"
                                            onclick="confirmDelete(<?php echo \App\e($note['id']); ?>)">
                                            Sil
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                                <?php echo $search ? 'Arama sonucu bulunamadı.' : 'Henüz not yok.'; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<!-- Single Action Form -->
<form id="singleActionForm" method="post" action="" style="display: none;">
    <input type="hidden" name="csrf_token" value="<?php echo \App\e($csrfToken); ?>">
    <input type="hidden" name="action" id="singleActionInput">
    <input type="hidden" name="id" id="singleIdInput">
</form>

<script>
    // Message Handling
    const urlParams = new URLSearchParams(window.location.search);
    const msg = urlParams.get('msg');
    if (msg) {
        if (msg === 'deleted') alert('Kayıt başarıyla silindi.');
        else if (msg === 'bulk_updated') alert('Toplu işlem başarıyla tamamlandı.');
        else if (msg === 'csrf_error') alert('Güvenlik hatası (CSRF). Lütfen sayfayı yenileyip tekrar deneyin.');

        // Clean URL
        window.history.replaceState({}, document.title, window.location.pathname);
    }

    // Toggle Select All
    const selectAllCheckbox = document.getElementById('selectAll');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function () {
            var checkboxes = document.querySelectorAll('.row-checkbox');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = this.checked;
            }
        });
    }

    // Bulk Form Validation
    const bulkForm = document.getElementById('bulkForm');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            const bulkAction = this.elements['bulk_action'];
            if (!bulkAction || bulkAction.value === '') {
                // Removed alert
                e.preventDefault();
                return false;
            }

            var checkboxes = document.querySelectorAll('.row-checkbox:checked');
            if (checkboxes.length === 0) {
                // Removed alert
                e.preventDefault();
                return false;
            }

            // REMOVED CONFIRMATION
            // Proceed directly
        });
    }

    // Submit Single Action
    function submitAction(action, id) {
        const form = document.getElementById('singleActionForm');
        document.getElementById('singleActionInput').value = action;
        document.getElementById('singleIdInput').value = id;
        form.submit();
    }

    // Confirm and Delete (Wrapper)
    function confirmDelete(id) {
        // Direct delete without confirmation
        submitAction('delete', id);
    }
</script>

<?php if ($totalPages > 1): ?>
    <div style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-outline">«</a>
            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-outline">‹</a>
        <?php endif; ?>

        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <?php if ($i === $page): ?>
                <span class="btn btn-primary" style="cursor: default;"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                    class="btn btn-outline"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-outline">›</a>
            <a href="?page=<?php echo $totalPages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"
                class="btn btn-outline">»</a>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px;">
    <div class="card" style="text-align: center; margin-bottom: 0;">
        <div style="font-size: 2rem; font-weight: bold; color: var(--primary-color);">
            <?php echo \App\e($stats['count']); ?>
        </div>
        <div class="text-muted">Toplam Not</div>
    </div>
    <div class="card" style="text-align: center; margin-bottom: 0;">
        <div style="font-size: 2rem; font-weight: bold; color: var(--success-color);">
            <?php echo \App\e($stats['views']); ?>
        </div>
        <div class="text-muted">Toplam Görüntülenme</div>
    </div>
</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>