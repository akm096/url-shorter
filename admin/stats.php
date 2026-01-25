<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\auth;
use App\db;

// Login check
auth\require_login();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$type = $_GET['type'] ?? 'link'; // 'link' or 'note'

if (!$id) {
    header('Location: index.php');
    exit;
}

$pdo = db\get_db();
$item = null;
$itemSlug = '';
$itemTarget = '';
$itemTitle = '';
$dailyStats = [];
$browserStats = [];
$osStats = [];
$deviceStats = [];

if ($type === 'note') {
    // Note Stats
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        die('Not bulunamadı.');
    }

    $itemSlug = $item['slug'];
    $itemTarget = 'Not İçeriği'; // No target URL suitable, maybe snippet
    $itemTitle = $item['title'] ?? 'Başlıksız Not';

    $dailyStats = db\get_daily_note_view_stats(30, $id);
    $browserStats = db\get_note_distribution_stats('browser', $id);
    $osStats = db\get_note_distribution_stats('os', $id);
    $deviceStats = db\get_note_distribution_stats('device_type', $id);

} else {
    // Link Stats (Default)
    $stmt = $pdo->prepare('SELECT * FROM links WHERE id = ?');
    $stmt->execute([$id]);
    $item = $stmt->fetch();

    if (!$item) {
        die('Link bulunamadı.');
    }

    $itemSlug = $item['slug'];
    $itemTarget = $item['target_url'];
    $itemTitle = 'Kısa Link';

    $dailyStats = db\get_daily_click_stats(30, $id);
    $browserStats = db\get_distribution_stats('browser', $id);
    $osStats = db\get_distribution_stats('os', $id);
    $deviceStats = db\get_distribution_stats('device_type', $id);
}

// Prepare data for Charts
$dates = array_column($dailyStats, 'date');
$clicks = array_column($dailyStats, 'count');

require_once __DIR__ . '/layout/header.php';
?>

<div class="d-flex justify-between" style="margin-bottom: 20px; align-items: center;">
    <div>
        <h2>İstatistikler: <span style="color: var(--primary-color);"><?php echo \App\e($itemSlug); ?></span></h2>
        <p class="text-muted" style="margin: 0;">
            <?php echo $type === 'note' ? \App\e($itemTitle) : \App\e($itemTarget); ?>
        </p>
    </div>
    <a href="<?php echo $type === 'note' ? 'notes.php' : 'index.php'; ?>" class="btn btn-outline">← Geri</a>
</div>

<div
    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
    <!-- Line Chart: Daily Interactions -->
    <div class="card" style="grid-column: 1 / -1;">
        <h3>Son 30 Gün <?php echo $type === 'note' ? 'Görüntülenme' : 'Tıklama'; ?></h3>
        <div style="position: relative; height: 300px; width: 100%;">
            <canvas id="clicksChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: Browser -->
    <div class="card">
        <h3>Tarayıcı Dağılımı</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="browserChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: OS -->
    <div class="card">
        <h3>İşletim Sistemi</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="osChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: Device -->
    <div class="card">
        <h3>Cihaz Tipi</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="deviceChart"></canvas>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Theme Colors
        const style = getComputedStyle(document.body);
        const primaryColor = style.getPropertyValue('--primary-color').trim() || '#4f46e5';
        const borderColor = style.getPropertyValue('--border-color').trim() || '#e5e7eb';
        const textColor = style.getPropertyValue('--text-color').trim() || '#1f2937';

        // Common Chart Options
        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        font: { family: "'Inter', sans-serif" }
                    }
                }
            },
            scales: {
                // will be overridden for doughnuts
            }
        };

        // Data Injection
        const dailyLabels = <?php echo json_encode($dates); ?>;
        const dailyData = <?php echo json_encode($clicks); ?>;
        const interactionLabel = "<?php echo $type === 'note' ? 'Görüntülenme' : 'Tıklama'; ?>";

        const browserLabels = <?php echo json_encode(array_column($browserStats, 'name')); ?>;
        const browserData = <?php echo json_encode(array_column($browserStats, 'count')); ?>;

        const osLabels = <?php echo json_encode(array_column($osStats, 'name')); ?>;
        const osData = <?php echo json_encode(array_column($osStats, 'count')); ?>;

        const deviceLabels = <?php echo json_encode(array_column($deviceStats, 'name')); ?>;
        const deviceData = <?php echo json_encode(array_column($deviceStats, 'count')); ?>;

        // Helper to generate colors
        function generateColors(count) {
            const colors = [
                '#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6',
                '#ec4899', '#6366f1', '#14b8a6', '#f97316', '#06b6d4'
            ];
            // Cycle through colors if count > colors.length
            return Array.from({ length: count }, (_, i) => colors[i % colors.length]);
        }

        // 1. Clicks/Views Line Chart
        new Chart(document.getElementById('clicksChart'), {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: interactionLabel,
                    data: dailyData,
                    borderColor: primaryColor,
                    backgroundColor: primaryColor + '20', // transparent
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4,
                    pointHoverRadius: 6
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { color: textColor, precision: 0 },
                        grid: { color: borderColor }
                    },
                    x: {
                        ticks: { color: textColor },
                        grid: { display: false }
                    }
                }
            }
        });

        // 2. Browser Chart
        new Chart(document.getElementById('browserChart'), {
            type: 'doughnut',
            data: {
                labels: browserLabels,
                datasets: [{
                    data: browserData,
                    backgroundColor: generateColors(browserData.length),
                    borderWidth: 0
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });

        // 3. OS Chart
        new Chart(document.getElementById('osChart'), {
            type: 'doughnut',
            data: {
                labels: osLabels,
                datasets: [{
                    data: osData,
                    backgroundColor: generateColors(osData.length),
                    borderWidth: 0
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });

        // 4. Device Chart
        new Chart(document.getElementById('deviceChart'), {
            type: 'pie',
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceData,
                    backgroundColor: generateColors(deviceData.length),
                    borderWidth: 0
                }]
            },
            options: {
                ...commonOptions,
                scales: {
                    x: { display: false },
                    y: { display: false }
                }
            }
        });
    });
</script>

<?php require_once __DIR__ . '/layout/footer.php'; ?>