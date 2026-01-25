<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';
require_once __DIR__ . '/../app/csrf.php';

use App\auth;
use App\db;

// Login check
auth\require_login();

$importResult = null;
$error = null;

// Handle Export Action
if (isset($_GET['action']) && $_GET['action'] === 'export') {
    // Prevent output buffering issues
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'links_export_' . date('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($output, ['ID', 'Slug', 'Target URL', 'Title', 'Clicks', 'Created At']);

    // Stream Data
    $pdo = db\get_db();
    $stmt = $pdo->query("SELECT id, slug, target_url, title, click_count, created_at FROM links ORDER BY id DESC"); // Stream all rows

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['slug'],
            $row['target_url'],
            $row['title'],
            $row['click_count'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Handle Export Notes Action
if (isset($_GET['action']) && $_GET['action'] === 'export_notes') {
    // Prevent output buffering issues
    while (ob_get_level()) {
        ob_end_clean();
    }

    $filename = 'notes_export_' . date('Y-m-d_H-i') . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');

    // Add BOM for Excel compatibility
    fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

    // Headers
    fputcsv($output, ['ID', 'Slug', 'Title', 'Views', 'Created At']);

    // Stream Data
    $pdo = db\get_db();
    $stmt = $pdo->query("SELECT id, slug, title, view_count, created_at FROM notes ORDER BY id DESC");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $row['id'],
            $row['slug'],
            $row['title'],
            $row['view_count'],
            $row['created_at']
        ]);
    }

    fclose($output);
    exit;
}

// Handle Import Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!\App\verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Güvenlik doğrulaması başarısız (CSRF).";
    } elseif ($_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error = "Dosya yükleme hatası.";
    } else {
        $file = $_FILES['csv_file']['tmp_name'];
        $handle = fopen($file, 'r');

        if ($handle) {
            $successCount = 0;
            $failCount = 0;
            $errors = [];
            $rowNum = 0;

            while (($data = fgetcsv($handle, 2000, ",")) !== false) {
                $rowNum++;

                // Skip header row if it looks like a header
                if ($rowNum === 1 && (strtolower($data[0] ?? '') === 'target url' || strtolower($data[0] ?? '') === 'hedef url')) {
                    continue;
                }

                // Expected format: Target URL, Slug (Opt), Title (Opt)
                // Adjust index based on your CSV structure preference. 
                // Let's assume standard: URL, Slug, Title
                $targetUrl = trim($data[0] ?? '');
                $customSlug = trim($data[1] ?? '');
                $title = trim($data[2] ?? '');

                if (empty($targetUrl)) {
                    continue; // Skip empty rows
                }

                if (!\App\validate_url($targetUrl) && !\App\validate_url('http://' . $targetUrl)) {
                    // Try adding http if missing, for validation
                    if (\App\validate_url('http://' . $targetUrl)) {
                        $targetUrl = 'http://' . $targetUrl;
                    } else {
                        $failCount++;
                        $errors[] = "Satır $rowNum: Geçersiz URL ($targetUrl)";
                        continue;
                    }
                }

                // Slug Handling
                $slug = '';
                if (!empty($customSlug)) {
                    // Check if valid format
                    if (!\App\validate_slug($customSlug) || \App\is_reserved_slug($customSlug)) {
                        $failCount++;
                        $errors[] = "Satır $rowNum: Geçersiz Slug ($customSlug)";
                        continue;
                    }
                    // Check if exists
                    if (db\get_link_by_slug($customSlug) || db\get_note_by_slug($customSlug)) {
                        $failCount++;
                        $errors[] = "Satır $rowNum: Slug kullanımda ($customSlug)";
                        continue;
                    }
                    $slug = $customSlug;
                } else {
                    $slug = \App\generate_random_slug();
                }

                // Insert
                try {
                    db\insert_link([
                        'slug' => $slug,
                        'target_url' => $targetUrl,
                        'title' => $title ?: null,
                        'redirect_type' => 302,
                        'created_at' => date('Y-m-d H:i:s'),
                        'active' => 1
                    ]);
                    $successCount++;
                } catch (Exception $e) {
                    $failCount++;
                    $errors[] = "Satır $rowNum: Veritabanı hatası";
                }
            }
            fclose($handle);

            $importResult = [
                'success' => $successCount,
                'fail' => $failCount,
                'errors' => array_slice($errors, 0, 50) // Limit error display
            ];

        } else {
            $error = "Dosya okunamadı.";
        }
    }
}

require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex justify-between" style="align-items: center; margin-bottom: 20px;">
    <h2>Toplu İçe/Dışa Aktar</h2>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <?php echo \App\e($error); ?>
    </div>
<?php endif; ?>

<?php if ($importResult): ?>
    <div class="card mb-4" style="border-left: 5px solid var(--primary-color);">
        <h3>İçe Aktarma Sonucu</h3>
        <p>
            <span class="text-success" style="font-weight: bold; font-size: 1.2em;">✅
                <?php echo $importResult['success']; ?> Başarılı
            </span>
            <span class="text-danger" style="margin-left: 15px; font-weight: bold; font-size: 1.2em;">❌
                <?php echo $importResult['fail']; ?> Hatalı
            </span>
        </p>
        <?php if (!empty($importResult['errors'])): ?>
            <div
                style="background: var(--bg-color); padding: 10px; border-radius: 4px; max-height: 200px; overflow-y: auto; border: 1px solid var(--border-color);">
                <ul style="margin: 0; padding-left: 20px; color: var(--danger-color);">
                    <?php foreach ($importResult['errors'] as $err): ?>
                        <li>
                            <?php echo \App\e($err); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="row" style="display: flex; gap: 20px; flex-wrap: wrap;">

    <!-- Import Card -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <h3>📥 İçe Aktar (Import)</h3>
        <p class="text-muted">CSV dosyasından toplu link ekleyin.</p>

        <div
            style="background: var(--bg-color); padding: 15px; border-radius: var(--radius); border: 1px solid var(--border-color); margin-bottom: 20px;">
            <strong>CSV Formatı:</strong><br>
            <code>Hedef URL, Özel Slug (Opsiyonel), Başlık (Opsiyonel)</code>
            <br><small class="text-muted">Örnek: https://google.com, , Google Arama</small>
        </div>

        <form action="" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label for="csv_file">CSV Dosyası Seçin</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required
                    style="width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 4px;">
            </div>

            <button type="submit" class="btn btn-primary" style="width: 100%;">📤 Yükle ve İşle</button>
        </form>
    </div>

    <!-- Export Card -->
    <div class="card" style="flex: 1; min-width: 300px;">
        <h3>📤 Dışa Aktar (Export)</h3>
        <p class="text-muted">Veritabanı kayıtlarını CSV olarak indirin.</p>

        <div style="padding: 20px 0;">
            <p>Toplam Link Sayısı: <strong><?php echo number_format(\App\db\get_links_count()); ?></strong></p>
            <p>Toplam Not Sayısı: <strong><?php echo number_format(\App\db\get_note_stats()['count']); ?></strong></p>
            <p class="text-muted"><small>Çıktı formatı Excel uyumludur (UTF-8 BOM).</small></p>
        </div>

        <div style="display: flex; gap: 10px; flex-direction: column;">
            <a href="?action=export" class="btn btn-outline" style="display: block; text-align: center; width: 100%;">
                ⬇️ Linkleri İndir (.csv)
            </a>
            <a href="?action=export_notes" class="btn btn-outline"
                style="display: block; text-align: center; width: 100%;">
                ⬇️ Notları İndir (.csv)
            </a>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/layout/footer.php'; ?>