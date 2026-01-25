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
$countryStats = [];
$refererStats = [];

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
    $countryStats = db\get_note_distribution_stats('country_code', $id, 0); // Limit 0 for Map
    $refererStats = db\get_note_distribution_stats('referer', $id);

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
    $countryStats = db\get_distribution_stats('country_code', $id, 0); // Limit 0 for Map
    $refererStats = db\get_distribution_stats('referer', $id);
}

// Prepare data for Charts
$dates = array_column($dailyStats, 'date');
$clicks = array_column($dailyStats, 'count');
$totalClicks = array_sum($clicks);

require_once __DIR__ . '/layout/header.php';

// Prepare country data for Grid (Top 10)
$countryStatsTop10 = array_slice($countryStats, 0, 10);

?>

<div class="d-flex justify-between" style="margin-bottom: 20px; align-items: center; flex-wrap: wrap; gap: 10px;">
    <div>
        <h2>İstatistikler: <span style="color: var(--primary-color);"><?php echo \App\e($itemSlug); ?></span></h2>
        <p class="text-muted" style="margin: 0;">
            <?php echo $type === 'note' ? \App\e($itemTitle) : \App\e($itemTarget); ?>
        </p>
    </div>
    <div style="display: flex; align-items: center; gap: 15px;">
        <div class="card" style="padding: 10px 20px; text-align: center; margin: 0;">
            <div style="font-size: 0.8em; color: var(--text-muted);">Toplam Etkileşim</div>
            <div style="font-size: 1.5em; font-weight: bold; color: var(--primary-color);">
                <?php echo number_format($totalClicks); ?>
            </div>
        </div>
        <a href="<?php echo $type === 'note' ? 'notes.php' : 'index.php'; ?>" class="btn btn-outline">← Geri</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(12, 1fr); gap: 20px; margin-bottom: 20px;">

    <!-- Line Chart: Daily Interactions (Full Width) -->
    <div class="card" style="grid-column: span 12;">
        <h3>Son 30 Gün <?php echo $type === 'note' ? 'Görüntülenme' : 'Tıklama'; ?></h3>
        <div style="position: relative; height: 350px; width: 100%;">
            <canvas id="clicksChart"></canvas>
        </div>
    </div>

    <!-- World Map Container -->
    <div class="card" style="grid-column: span 12;">
        <h3>Etkileşim Haritası</h3>
        <div id="world-map" style="width: 100%; height: 400px;"></div>
    </div>

    <!-- Referer Chart (Half Width on Desktop) -->
    <div class="card" style="grid-column: span 12;">
        <h3>En Çok Yönlendiren Kaynaklar</h3>
        <div style="position: relative; height: 300px;">
            <canvas id="refererChart"></canvas>
        </div>
        <?php if (empty($refererStats)): ?>
            <p class="text-muted text-center" style="margin-top: 20px;">Henüz yeterli veri yok.</p>
        <?php endif; ?>
    </div>

    <!-- Doughnut: Browser (4 cols) -->
    <div class="card" style="grid-column: span 12; @media (min-width: 768px) { grid-column: span 4; }">
        <h3>Tarayıcı</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="browserChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: OS (4 cols) -->
    <div class="card" style="grid-column: span 12; @media (min-width: 768px) { grid-column: span 4; }">
        <h3>İşletim Sistemi</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="osChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: Device (4 cols) -->
    <div class="card" style="grid-column: span 12; @media (min-width: 768px) { grid-column: span 4; }">
        <h3>Cihaz</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="deviceChart"></canvas>
        </div>
    </div>

    <!-- Doughnut: Country (3 cols) -->
    <div class="card" style="grid-column: span 12; @media (min-width: 768px) { grid-column: span 3; }">
        <h3>Ülke</h3>
        <div style="position: relative; height: 250px;">
            <canvas id="countryChart"></canvas>
        </div>
    </div>

</div>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/jsvectormap/dist/css/jsvectormap.min.css" />
<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/js/jsvectormap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/jsvectormap/dist/maps/world.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Theme Colors
        const style = getComputedStyle(document.body);
        const primaryColor = style.getPropertyValue('--primary-color').trim() || '#4f46e5';
        const borderColor = style.getPropertyValue('--border-color').trim() || '#e5e7eb';
        const textColor = style.getPropertyValue('--text-color').trim() || '#1f2937';
        const gridColor = html.classList.contains('dark-mode') ? '#374151' : '#e5e7eb'; // Better grid visibility

        // Detect screen width for responsive grid
        const isMobile = window.innerWidth < 768;

        // Apply grid column classes dynamically if inline styles fail (backup)
        if (!isMobile) {
            const smallCards = document.querySelectorAll('.card[style*="grid-column: span 12"]');
            // Logic handled by CSS media queries in style attribute or CSS file is better, 
            // but here we used inline styles with media query syntax which doesn't work in inline style attribute directly.
            // Wait, inline style media queries don't work. I need to fix the HTML above.
            // I will fix it by adding a simple style block.
        }

        // Common Chart Options
        Chart.defaults.color = textColor;
        Chart.defaults.font.family = "'Inter', sans-serif";

        const commonOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: textColor,
                        usePointStyle: true,
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    padding: 10,
                    cornerRadius: 8,
                    titleFont: { size: 13, weight: 600 },
                    bodyFont: { size: 12 }
                }
            },
            interaction: {
                mode: 'index',
                intersect: false,
            },
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

        // Use Top 10 for Charts
        const countryLabels = <?php echo json_encode(array_column($countryStatsTop10, 'name')); ?>;
        const countryData = <?php echo json_encode(array_column($countryStatsTop10, 'count')); ?>;

        // Full data for Map
        const fullCountryData = <?php echo json_encode($countryStats); ?>;

        const refererLabels = <?php echo json_encode(array_column($refererStats, 'name')); ?>;
        const refererData = <?php echo json_encode(array_column($refererStats, 'count')); ?>;

        // Colors Palette
        const palette = [
            '#4f46e5', '#8b5cf6', '#ec4899', '#f43f5e', '#f97316',
            '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1'
        ];

        function getColors(count) {
            return Array.from({ length: count }, (_, i) => palette[i % palette.length]);
        }

        // 1. Clicks/Views Area Chart
        const ctxClicks = document.getElementById('clicksChart').getContext('2d');
        const gradient = ctxClicks.createLinearGradient(0, 0, 0, 300);
        gradient.addColorStop(0, primaryColor + '50'); // 50% opacity
        gradient.addColorStop(1, primaryColor + '00'); // 0% opacity

        new Chart(ctxClicks, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: interactionLabel,
                    data: dailyData,
                    borderColor: primaryColor,
                    backgroundColor: gradient,
                    borderWidth: 2,
                    tension: 0.4, // Smooth curves
                    fill: true,
                    pointBackgroundColor: '#ffffff',
                    pointBorderColor: primaryColor,
                    pointBorderWidth: 2,
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
                        grid: { color: borderColor + '40', borderDash: [5, 5] }, // Dashed grid
                        border: { display: false }
                    },
                    x: {
                        ticks: { color: textColor, maxRotation: 45, minRotation: 0 },
                        grid: { display: false },
                        border: { display: false }
                    }
                }
            }
        });

        // 2. Referer Bar Chart (Horizontal)
        new Chart(document.getElementById('refererChart'), {
            type: 'bar',
            data: {
                labels: refererLabels.map(l => l ? l.substring(0, 40) + (l.length > 40 ? '...' : '') : 'Doğrudan/Bilinmiyor'),
                datasets: [{
                    label: 'Tıklama',
                    data: refererData,
                    backgroundColor: primaryColor,
                    borderRadius: 4,
                }]
            },
            options: {
                ...commonOptions,
                indexAxis: 'y', // Horizontal
                plugins: {
                    ...commonOptions.plugins,
                    legend: { display: false } // No legend needed for single dataset
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0 },
                        grid: { color: borderColor + '40' }
                    },
                    y: {
                        grid: { display: false }
                    }
                }
            }
        });

        // 3. Doughnut Charts Configuration
        const doughnutConfig = {
            ...commonOptions,
            scales: { x: { display: false }, y: { display: false } },
            cutout: '65%'
        };

        // Browser
        new Chart(document.getElementById('browserChart'), {
            type: 'doughnut',
            data: {
                labels: browserLabels,
                datasets: [{
                    data: browserData,
                    backgroundColor: getColors(browserData.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: doughnutConfig
        });

        // OS
        new Chart(document.getElementById('osChart'), {
            type: 'doughnut',
            data: {
                labels: osLabels,
                datasets: [{
                    data: osData,
                    backgroundColor: getColors(osData.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: doughnutConfig
        });

        // Device
        new Chart(document.getElementById('deviceChart'), {
            type: 'doughnut',
            data: {
                labels: deviceLabels,
                datasets: [{
                    data: deviceData,
                    backgroundColor: getColors(deviceData.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: doughnutConfig
        });

        // Country
        new Chart(document.getElementById('countryChart'), {
            type: 'doughnut',
            data: {
                labels: countryLabels.map(l => l || 'Bilinmiyor'),
                datasets: [{
                    data: countryData,
                    backgroundColor: getColors(countryData.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: doughnutConfig
        });


    // 4. World Map
    // Convert array [{name: "US", count: 5}, ...] to object { "US": 5, ... }
    const mapData = {};
    fullCountryData.forEach(item => {
        if (item.name) {
            mapData[item.name.toUpperCase()] = item.count;
        }
    });

    // Determine min/max for coloring
    const values = Object.values(mapData);
    const minVal = values.length ? Math.min(...values) : 0;
    const maxVal = values.length ? Math.max(...values) : 0;

    try {
        const map = new jsVectorMap({
            selector: "#world-map",
            map: "world",
            backgroundColor: "transparent",
            draggable: true,
            zoomButtons: true,
            zoomOnScroll: false,
            regionStyle: {
                initial: {
                    fill: html.classList.contains('dark-mode') ? '#374151' : '#e5e7eb',
                    stroke: borderColor,
                    strokeWidth: 0.15,
                    fillOpacity: 1
                },
                hover: {
                    fillOpacity: 0.7,
                    cursor: 'pointer'
                }
            },
            series: {
                regions: [{
                    scale: {
                        min: primaryColor + '40', // Light opacity
                        max: primaryColor         // Full opacity
                    },
                    attribute: 'fill',
                    values: mapData,
                    min: minVal,
                    max: maxVal,
                    normalizeFunction: 'polynomial'
                }]
            },
            onRegionTooltipShow(event, tooltip, code) {
                const count = mapData[code] || 0;
                tooltip.text(
                    `<h5 class="mb-0">${tooltip.text()}</h5>` +
                    `<small class="text-muted">Etkileşim: <b class="text-white">${count}</b></small>`,
                    true // enable HTML
                );
            }
        });

        // Re-render map on theme change if needed (reload page is easier, but simple background update might work)
        // Just basic setup for now.

    } catch (e) {
        console.error("Map Error:", e);
        document.getElementById('world-map').innerHTML = '<p class="text-center text-muted">Harita yüklenemedi.</p>';
    }

    });
</script>

<style>
    /* Responsive Grid Helper */
    @media (min-width: 900px) {
        .card[style*="grid-column: span 12;"] {
            /* Reset specific inline overrides if needed, but the HTML logic handles it mostly via span 4 vs span 12 */
        }

        /* Target the last 4 cards specifically (Browser, OS, Device, Country) */
        .card:nth-last-child(1),
        .card:nth-last-child(2),
        .card:nth-last-child(3),
        .card:nth-last-child(4) {
            grid-column: span 3 !important;
        }
    }

    @media (max-width: 899px) {
        .card {
            grid-column: span 12 !important;
        }
    }
</style>

<?php require_once __DIR__ . '/layout/footer.php'; ?>