<?php
declare(strict_types=1);

// Ana yönlendirici dosya. Bu dosya kök dizinde bulunur ve gelen istekleri karşılar.

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/functions.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/csrf.php';
require_once __DIR__ . '/app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\db;

// Konfigürasyon
$config = db\get_config();
$baseUrl = rtrim((string) ($config['base_url'] ?? ''), '/');
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $baseUrl = $host ? ($scheme . '://' . $host) : '';
}

// Eğer query string'de slug varsa, yönlendirme veya not görüntüleme yapılacak
$slug = $_GET['slug'] ?? null;
if ($slug) {
    // Start session for password unlock check
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // 1. Link kontrolü
    $link = db\get_link_by_slug((string) $slug);
    if ($link && (int) $link['active'] === 1) {
        // Check expiration
        if (!empty($link['expires_at'])) {
            $expiresAt = strtotime($link['expires_at']);
            if ($expiresAt !== false && time() > $expiresAt) {
                // Expired
                include __DIR__ . '/expired.php';
                exit;
            }
        }

        // Check click limit
        if (!empty($link['click_limit'])) {
            if ((int) $link['click_count'] >= (int) $link['click_limit']) {
                // Limit reached
                include __DIR__ . '/expired.php';
                exit;
            }
        }

        // Check password protection
        if (!empty($link['password_hash'])) {
            $sessionKey = 'unlocked_link_' . $slug;
            if (!isset($_SESSION[$sessionKey]) || $_SESSION[$sessionKey] !== true) {
                // Redirect to unlock page
                header('Location: /unlock/' . urlencode($slug) . '/link');
                exit;
            }
        }

        // Tıklama sayısını arttır
        db\increment_click_count((int) $link['id']);
        // Analitik verisi kaydet
        db\log_link_click((int) $link['id']);

        // OPEN GRAPH (Sosyal Medya Önizleme)
        // OPEN GRAPH ve BOT KONTROLÜ İPTAL EDİLDİ
        // Kullanıcı isteği üzerine botlar için özel sayfa gösterimi kaldırıldı.
        // Artık herkes (botlar dahil) doğrudan yönlendirilecek.

        // 301 veya 302 yönlendirme
        $status = (int) $link['redirect_type'] === 301 ? 301 : 302;
        header('Location: ' . $link['target_url'], true, $status);
        exit;
    }

    // 2. Not kontrolü
    $note = db\get_note_by_slug((string) $slug);
    if ($note && (int) $note['active'] === 1) {
        // Check password protection
        if (!empty($note['password_hash'])) {
            $sessionKey = 'unlocked_note_' . $slug;
            if (!isset($_SESSION[$sessionKey]) || $_SESSION[$sessionKey] !== true) {
                // Redirect to unlock page
                header('Location: /unlock/' . urlencode($slug) . '/note');
                exit;
            }
        }

        // Görüntüleme sayısını arttır
        db\increment_note_view((int) $note['id']);
        // Analitik verisini kaydet
        db\log_note_view((int) $note['id']);

        // Burn After Read Logic
        if ((int) ($note['is_burn_after_read'] ?? 0) === 1) {
            // Check if user confirmed the burn
            if (isset($_POST['confirm_burn']) && $_POST['confirm_burn'] === '1') {
                // User confirmed: Deactivate the note (Soft Delete as requested)
                db\toggle_note_status((int) $note['id'], 0);

                // Continue to show the note this one last time
                // Add a visual indicator
                echo '<div style="background: #e74c3c; color: white; text-align: center; padding: 10px; font-weight: bold;">⚠️ Bu not imha edildi. Sayfayı yenilerseniz ulaşamazsınız.</div>';
            } else {
                // Show Warning Page
                ?>
                <!DOCTYPE html>
                <html lang="tr">

                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Kendini İmha Eden Not</title>
                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
                    <style>
                        body {
                            font-family: 'Inter', sans-serif;
                            display: flex;
                            align-items: center;
                            justify-content: center;
                            height: 100vh;
                            background: #f8f9fa;
                            margin: 0;
                        }

                        .card {
                            background: white;
                            padding: 2rem;
                            border-radius: 12px;
                            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
                            max-width: 400px;
                            text-align: center;
                        }

                        .btn {
                            background: #e74c3c;
                            color: white;
                            border: none;
                            padding: 10px 20px;
                            border-radius: 6px;
                            cursor: pointer;
                            font-size: 1rem;
                            margin-top: 1rem;
                        }

                        .btn:hover {
                            background: #c0392b;
                        }
                    </style>
                </head>

                <body>
                    <div class="card">
                        <h1 style="color: #e74c3c;">⚠️ Dikkat</h1>
                        <p>Bu not <strong>"Görüldükten Sonra Sil"</strong> modunda paylaşılmıştır.</p>
                        <p>Aşağıdaki butona tıkladığınızda not görüntülenecek ve ardından <strong>erişime kapatılacaktır (sunucuda pasif
                                hale gelecektir).</strong></p>
                        <form method="post">
                            <input type="hidden" name="confirm_burn" value="1">
                            <button type="submit" class="btn">Görüntüle ve Yok Et</button>
                        </form>
                    </div>
                </body>

                </html>
                <?php
                exit;
            }
        }

        // Not görüntüleme sayfası
        $noteTitle = $note['title'] ? \App\e($note['title']) : 'Not';
        $noteContent = \App\e($note['content']);
        $noteDate = \App\e(substr($note['created_at'], 0, 10));

        ?>
        <!DOCTYPE html>
        <html lang="tr">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $noteTitle; ?> - Not Görüntüle</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
            <?php
            $cssPath = __DIR__ . '/assets/css/admin.css';
            $cssVer = file_exists($cssPath) ? filemtime($cssPath) : '1.0';
            ?>
            <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo $cssVer; ?>">
            <!-- Markdown Parser & Sanitizer -->
            <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/dompurify/dist/purify.min.js"></script>
            <script>
                // Init Dark Mode
                (function () {
                    const savedTheme = localStorage.getItem('theme');
                    if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                        document.documentElement.classList.add('dark-mode');
                        document.documentElement.setAttribute('data-theme', 'dark');
                    }
                })();
            </script>
            <style>
                /* Markdown Content Base Styles */
                .markdown-body {
                    line-height: 1.6;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
                }

                .markdown-body h1,
                .markdown-body h2,
                .markdown-body h3 {
                    margin-top: 24px;
                    margin-bottom: 16px;
                    font-weight: 600;
                    line-height: 1.25;
                }

                .markdown-body h1 {
                    font-size: 2em;
                    border-bottom: 1px solid var(--border-color);
                    padding-bottom: .3em;
                }

                .markdown-body h2 {
                    font-size: 1.5em;
                    border-bottom: 1px solid var(--border-color);
                    padding-bottom: .3em;
                }

                .markdown-body p {
                    margin-top: 0;
                    margin-bottom: 16px;
                }

                .markdown-body code {
                    background-color: rgba(175, 184, 193, 0.2);
                    padding: .2em .4em;
                    border-radius: 6px;
                    font-family: ui-monospace, SFMono-Regular, SF Mono, Menlo, Consolas, Liberation Mono, monospace;
                }

                .markdown-body pre {
                    background-color: var(--bg-color-alt, #f6f8fa);
                    padding: 16px;
                    overflow: auto;
                    border-radius: 6px;
                }

                .markdown-body pre code {
                    background-color: transparent;
                    padding: 0;
                }

                .markdown-body blockquote {
                    padding: 0 1em;
                    color: var(--text-muted);
                    border-left: .25em solid var(--border-color);
                    margin: 0 0 16px 0;
                }

                .markdown-body ul,
                .markdown-body ol {
                    padding-left: 2em;
                    margin-bottom: 16px;
                }

                .markdown-body img {
                    max-width: 100%;
                    box-sizing: content-box;
                    background-color: #fff;
                }

                .markdown-body a {
                    color: var(--primary-color);
                    text-decoration: none;
                }

                .markdown-body a:hover {
                    text-decoration: underline;
                }

                .dark-mode .markdown-body pre {
                    background-color: #161b22;
                }
            </style>
        </head>

        <body>
            <div class="container" style="max-width: 800px; padding-top: 40px;">
                <div class="top-bar">
                    <h1>Not Görüntüleyici</h1>
                    <nav class="nav-links d-flex">
                        <button id="themeToggle" class="theme-toggle" title="Tema Değiştir">🌙</button>
                        <a href="/" class="btn btn-primary" style="color: #fff; text-decoration: none;">Yeni Oluştur</a>
                    </nav>
                </div>

                <div class="card">
                    <h1 style="font-size: 1.5rem; margin-bottom: 0.5rem;"><?php echo $noteTitle; ?></h1>
                    <div class="text-muted"
                        style="font-size: 0.85rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem;">
                        Oluşturulma: <?php echo $noteDate; ?> | Görüntülenme: <?php echo $note['view_count']; ?>
                    </div>
                    <!-- Raw content hidden, will be rendered via JS -->
                    <div id="raw-content" style="display:none;"><?php echo $noteContent; ?></div>
                    <div id="render-content" class="markdown-body" style="padding: 1rem;"></div>
                </div>
            </div>
            <script>
                // Markdown Rendering Logic
                document.addEventListener('DOMContentLoaded', () => {
                    const rawContent = document.getElementById('raw-content').textContent;
                    const renderContainer = document.getElementById('render-content');

                    // Parse and Sanitize
                    const cleanHtml = DOMPurify.sanitize(marked.parse(rawContent));
                    renderContainer.innerHTML = cleanHtml;
                });

                // Dark Mode Logic
                const toggleBtn = document.getElementById('themeToggle');
                const html = document.documentElement;
                if (html.classList.contains('dark-mode')) { toggleBtn.textContent = '☀️'; } else { toggleBtn.textContent = '🌙'; }
                toggleBtn.addEventListener('click', () => {
                    html.classList.toggle('dark-mode');
                    if (html.classList.contains('dark-mode')) {
                        localStorage.setItem('theme', 'dark');
                        html.setAttribute('data-theme', 'dark');
                        toggleBtn.textContent = '☀️';
                    } else {
                        localStorage.setItem('theme', 'light');
                        html.removeAttribute('data-theme');
                        toggleBtn.textContent = '🌙';
                    }
                });
            </script>
        </body>

        </html>
        <?php
        exit;
    }

    // 3. Koleksiyon kontrolü
    $collection = db\get_collection_by_slug((string) $slug);
    if ($collection && (int) $collection['active'] === 1) {
        $colLinks = db\get_collection_links((int) $collection['id']);
        $colNotes = db\get_collection_notes((int) $collection['id']);
        $themeColor = $collection['theme_color'] ?: '#ffffff';

        // Determine text color based on background (simple contrast check)
        // Convert hex to rgb
        $hex = ltrim($themeColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        $textColor = ($yiq >= 128) ? '#000000' : '#ffffff';
        $btnBg = ($yiq >= 128) ? '#000000' : '#ffffff';
        $btnText = ($yiq >= 128) ? '#ffffff' : '#000000';

        ?>
        <!DOCTYPE html>
        <html lang="tr">

        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo \App\e($collection['title']); ?> | Linkler</title>
            <link rel="preconnect" href="https://fonts.googleapis.com">
            <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
            <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
            <style>
                body {
                    margin: 0;
                    padding: 0;
                    font-family: 'Inter', sans-serif;
                    background-color:
                        <?php echo $themeColor; ?>
                    ;
                    color:
                        <?php echo $textColor; ?>
                    ;
                    min-height: 100vh;
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    justify-content: center;
                }

                .container {
                    width: 90%;
                    max-width: 400px;
                    text-align: center;
                    padding: 40px 0;
                }

                .profile-title {
                    font-size: 1.5rem;
                    font-weight: 700;
                    margin-bottom: 0.5rem;
                }

                .profile-bio {
                    font-size: 1rem;
                    opacity: 0.9;
                    margin-bottom: 2rem;
                    line-height: 1.5;
                }

                .link-btn {
                    display: block;
                    width: 100%;
                    padding: 16px;
                    margin-bottom: 16px;
                    border-radius: 50px;
                    /* Pill shape */
                    background-color:
                        <?php echo $btnBg; ?>
                    ;
                    color:
                        <?php echo $btnText; ?>
                    ;
                    text-decoration: none;
                    font-weight: 600;
                    transition: transform 0.2s, opacity 0.2s;
                    border: 2px solid
                        <?php echo $btnBg; ?>
                    ;
                    box-sizing: border-box;
                }

                .link-btn:hover {
                    transform: scale(1.02);
                    opacity: 0.9;
                }

                /* Footer / Branding if needed */
                .branding {
                    margin-top: 30px;
                    font-size: 0.8rem;
                    opacity: 0.7;
                }

                .branding a {
                    color: inherit;
                }
            </style>
        </head>

        <body>
            <div class="container">
                <h1 class="profile-title"><?php echo \App\e($collection['title']); ?></h1>
                <?php if ($collection['description']): ?>
                    <p class="profile-bio"><?php echo nl2br(\App\e($collection['description'])); ?></p>
                <?php endif; ?>

                <div class="links">
                    <?php foreach ($colLinks as $link): ?>
                        <?php
                        // Skip inactive links
                        if ((int) $link['active'] !== 1)
                            continue;

                        // Construct Short URL
                        $shortUrl = $baseUrl ? ($baseUrl . '/' . $link['slug']) : ('/' . $link['slug']);
                        ?>
                        <a href="<?php echo \App\e($shortUrl); ?>" target="_blank" class="link-btn">
                            <?php echo \App\e($link['title'] ?: $link['target_url']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="notes" style="margin-top: 2rem;">
                    <?php foreach ($colNotes as $note): ?>
                        <?php
                        // Skip inactive notes
                        if ((int) $note['active'] !== 1)
                            continue;

                        // Construct Slug
                        $noteUrl = $baseUrl ? ($baseUrl . '/' . $note['slug']) : ('/' . $note['slug']);
                        ?>
                        <a href="<?php echo \App\e($noteUrl); ?>" target="_blank" class="link-btn"
                            style="border-style: dashed; background-color: transparent; color: <?php echo $textColor; ?>; border-color: <?php echo $textColor; ?>;">
                            <span style="font-size: 1.2em; display: inline-block; margin-right: 5px;">📝</span>
                            <?php echo \App\e($note['title'] ?: 'Not Görüntüle'); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <div class="branding">
                    <a href="/">URL Kısaltıcı ile oluşturuldu</a>
                </div>
            </div>
        </body>

        </html>
        <?php
        exit;
    }

    // Aktif olmayan veya bulunamayan slug için 404
    include __DIR__ . '/404.php';
    exit;
}

// Ana sayfa: Form işlemleri
// Start session for PRG pattern
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';
$createdUrl = '';

// Check for flash messages from previous request (PRG pattern)
if (isset($_SESSION['flash_success'])) {
    $success = $_SESSION['flash_success'];
    $createdUrl = $_SESSION['flash_url'] ?? '';
    unset($_SESSION['flash_success'], $_SESSION['flash_url']);
}
if (isset($_SESSION['flash_error'])) {
    $error = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}

// Form state preservation
$target_url = '';
$custom_slug = '';
$title = '';
$note_title = '';
$note_content = '';
$active_tab = $_SESSION['flash_tab'] ?? 'url'; // 'url' or 'note'
unset($_SESSION['flash_tab']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    $type = $_POST['type'] ?? 'url'; // url or note
    $active_tab = $type;

    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği. Lütfen sayfayı yenileyip tekrar deneyin.';
    } else {
        $custom_slug = trim((string) ($_POST['slug'] ?? ''));

        // Ortak slug validasyonu
        if ($custom_slug !== '') {
            if (!\App\validate_slug($custom_slug)) {
                $error = 'Kısa kod yalnızca harf, sayı, tire (-) ve alt çizgi (_) içerebilir.';
            } elseif (!\App\validate_slug_length($custom_slug)) {
                $error = 'Kısa kod ' . \App\MIN_SLUG_LENGTH . '-' . \App\MAX_SLUG_LENGTH . ' karakter arasında olmalıdır.';
            } elseif (\App\is_reserved_slug($custom_slug)) {
                $error = 'Bu kısa kod sistem tarafından ayrılmış.';
            } elseif (db\get_link_by_slug($custom_slug) !== null || db\get_note_by_slug($custom_slug) !== null) {
                $error = 'Bu kısa kod zaten kullanılıyor.';
            }
        } else {
            // slug boş ise oluştur
            $len = random_int(6, 8);
            $custom_slug = \App\generate_random_slug($len);
        }

        if (!$error) {
            // Rate Limiting Check
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $rateLimit = (int) ($config['rate_limit'] ?? 60);
            $rateLimitTime = (int) ($config['rate_limit_time'] ?? 60);

            if (!\App\check_rate_limit($clientIp, 'create_content', $rateLimit, $rateLimitTime)) {
                $error = 'Çok fazla istek gönderdiniz. Lütfen biraz bekleyip tekrar deneyin.';
            }
        }

        if (!$error) {
            if ($type === 'url') {
                $target_url = trim((string) ($_POST['target_url'] ?? ''));
                $title = trim((string) ($_POST['title'] ?? ''));

                // URL validation
                if (!\App\validate_url($target_url)) {
                    $error = 'Geçerli bir URL giriniz.';
                } elseif (!\App\validate_url_length($target_url)) {
                    $error = 'URL çok uzun.';
                } else {
                    $redirect_type = (int) ($config['redirect_default'] ?? 302);

                    // Security options
                    $password = trim((string) ($_POST['password'] ?? ''));
                    $expires_at = trim((string) ($_POST['expires_at'] ?? ''));
                    $click_limit = trim((string) ($_POST['click_limit'] ?? ''));

                    $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $expires_at_val = $expires_at !== '' ? $expires_at : null;
                    $click_limit_val = $click_limit !== '' ? (int) $click_limit : null;

                    // Open Graph
                    $og_title = trim($_POST['og_title'] ?? '');
                    $og_description = trim($_POST['og_description'] ?? '');
                    $og_image = trim($_POST['og_image'] ?? '');

                    // Safe Browsing Check
                    $safeBrowsingKey = $config['safe_browsing_api_key'] ?? '';
                    if (!empty($safeBrowsingKey) && !\App\is_url_safe($target_url, $safeBrowsingKey)) {
                        $error = 'Bu URL zararlı veya tehlikeli olarak işaretlenmiş. Kısaltılamaz.';
                    }
                }

                // DB Insert Link (only if no error)
                if (!$error) {
                    try {
                        db\insert_link([
                            'slug' => $custom_slug,
                            'target_url' => $target_url,
                            'title' => $title,
                            'redirect_type' => $redirect_type,
                            'created_at' => date('Y-m-d H:i:s'),
                            'active' => 1,
                            'password_hash' => $password_hash,
                            'expires_at' => $expires_at_val,
                            'click_limit' => $click_limit_val,
                            'og_title' => $og_title !== '' ? $og_title : null,
                            'og_description' => $og_description !== '' ? $og_description : null,
                            'og_image' => $og_image !== '' ? $og_image : null,
                        ]);
                        $createdUrl = $baseUrl ? ($baseUrl . '/' . $custom_slug) : ('/' . $custom_slug);
                        // PRG: Store in session and redirect
                        $_SESSION['flash_success'] = 'Kısa bağlantınız hazır.';
                        $_SESSION['flash_url'] = $createdUrl;
                        $_SESSION['flash_tab'] = 'url';
                        header('Location: ' . $_SERVER['REQUEST_URI']);
                        exit;
                    } catch (\PDOException $e) {
                        if ($e->getCode() == 23000)
                            $error = 'Bu kısa kod kullanımda.';
                        else
                            $error = 'Veritabanı hatası.';
                    }
                }
            } elseif ($type === 'note') {
                $note_title = trim((string) ($_POST['note_title'] ?? ''));
                $note_content = trim((string) ($_POST['note_content'] ?? ''));

                if ($note_content === '') {
                    $error = 'Not içeriği boş olamaz.';
                } elseif (mb_strlen($note_title) > 200) {
                    $error = 'Not başlığı 200 karakterden uzun olamaz.';
                } else {
                    // Security options
                    $password = trim((string) ($_POST['note_password'] ?? ''));
                    $password_hash = $password !== '' ? password_hash($password, PASSWORD_DEFAULT) : null;
                    $is_burn = isset($_POST['is_burn_after_read']) ? 1 : 0;

                    // DB Insert Note
                    try {
                        db\insert_note([
                            'slug' => $custom_slug,
                            'content' => $note_content,
                            'title' => $note_title,
                            'created_at' => date('Y-m-d H:i:s'),
                            'active' => 1,
                            'password_hash' => $password_hash,
                            'is_burn_after_read' => $is_burn,
                        ]);
                        $createdUrl = $baseUrl ? ($baseUrl . '/' . $custom_slug) : ('/' . $custom_slug);
                        // PRG: Store in session and redirect
                        $_SESSION['flash_success'] = 'Notunuz oluşturuldu.';
                        $_SESSION['flash_url'] = $createdUrl;
                        $_SESSION['flash_tab'] = 'note';
                        header('Location: ' . $_SERVER['REQUEST_URI']);
                        exit;
                    } catch (\PDOException $e) {
                        if ($e->getCode() == 23000)
                            $error = 'Bu kısa kod kullanımda.';
                        else
                            $error = 'Veritabanı hatası.';
                    }
                }
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>URL Kısaltma & Not Paylaşımı</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="assets/js/qrcode.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.css">
    <script src="https://cdn.jsdelivr.net/npm/easymde/dist/easymde.min.js"></script>
    <style>
        .nav-tabs {
            display: flex;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .nav-tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            font-weight: 600;
            color: var(--text-muted);
            margin-bottom: -1px;
            transition: all 0.2s;
        }

        .nav-tab:hover {
            color: var(--primary-color);
        }

        .nav-tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }

        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .copy-box {
            display: flex;
            gap: 10px;
            margin-top: 10px;
            align-items: center;
        }

        .copy-box input {
            flex: 1;
            background: var(--bg-color);
        }

        #qrcode {
            margin-top: 15px;
            display: flex;
            justify-content: center;
        }
    </style>
    <script>
        // Init Dark Mode
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
</head>

<body>
    <div class="container" style="max-width: 700px; padding-top: 40px;">
        <div class="top-bar">
            <h1>Kısalt & Paylaş</h1>
            <nav class="nav-links d-flex">
                <button id="themeToggle" class="theme-toggle" title="Tema Değiştir">🌙</button>
                <a href="admin/index.php">Yönetim Paneli</a>
            </nav>
        </div>

        <div class="card">
            <div class="text-muted" style="margin-bottom: 20px;">Hızlıca link kısaltın veya not paylaşın.</div>

            <div class="nav-tabs">
                <div id="tab-url" class="nav-tab <?php echo $active_tab === 'url' ? 'active' : ''; ?>"
                    onclick="switchTab('url')">Link Kısalt</div>
                <div id="tab-note" class="nav-tab <?php echo $active_tab === 'note' ? 'active' : ''; ?>"
                    onclick="switchTab('note')">Not Oluştur</div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <div style="margin-bottom: 10px; font-weight: bold;"><?php echo \App\e($success); ?></div>
                    <div class="copy-box">
                        <input type="text" id="shortUrl" value="<?php echo \App\e($createdUrl); ?>" readonly>
                        <button type="button" class="btn btn-primary" onclick="copyShortUrl(this)">Kopyala</button>
                        <a href="<?php echo \App\e($createdUrl); ?>" target="_blank" class="btn btn-outline"
                            style="text-decoration:none;">Aç</a>
                    </div>
                    <div id="qrcode"></div>
                    <script>
                        setTimeout(function () {
                            new QRCode(document.getElementById("qrcode"), {
                                text: "<?php echo \App\e($createdUrl); ?>",
                                width: 128,
                                height: 128,
                                colorDark: "#000000",
                                colorLight: "#ffffff",
                                correctLevel: QRCode.CorrectLevel.H
                            });
                        }, 100);
                    </script>
                </div>
            <?php endif; ?>

            <!-- URL Form -->
            <div id="form-url" class="form-section <?php echo $active_tab === 'url' ? 'active' : ''; ?>">
                <form method="post" action="">
                    <?php echo \App\csrf_input(); ?>
                    <input type="hidden" name="type" value="url">

                    <div class="form-group">
                        <label for="target_url">Hedef URL <span class="text-muted">*</span></label>
                        <input type="text" name="target_url" id="target_url" placeholder="https://ornek.com/uzun-link"
                            value="<?php echo \App\e($target_url); ?>">
                    </div>

                    <div class="form-group">
                        <label for="slug_url">Kısa Kod <span class="text-muted">(opsiyonel)</span></label>
                        <input type="text" name="slug" id="slug_url" placeholder="ornek-kod"
                            value="<?php echo \App\e($custom_slug); ?>">
                    </div>

                    <div class="form-group">
                        <label for="title_url">Başlık <span class="text-muted">(opsiyonel, kendiniz için)</span></label>
                        <input type="text" name="title" id="title_url" placeholder="Referans notu"
                            value="<?php echo \App\e($title); ?>">
                    </div>

                    <details style="margin-bottom: 1rem;">
                        <summary
                            style="cursor: pointer; color: var(--primary-color); font-weight: 500; margin-bottom: 0.5rem;">
                            🔒 Güvenlik Seçenekleri</summary>
                        <div
                            style="padding: 1rem; background: var(--bg-color); border-radius: var(--radius); margin-top: 0.5rem;">
                            <div class="form-group">
                                <label for="password">Şifre Koruması <span class="text-muted">(opsiyonel)</span></label>
                                <input type="password" name="password" id="password"
                                    placeholder="Bağlantıyı şifrele...">
                            </div>
                            <div class="form-group">
                                <label for="expires_at">Son Kullanma Tarihi <span
                                        class="text-muted">(opsiyonel)</span></label>
                                <input type="datetime-local" name="expires_at" id="expires_at">
                            </div>
                            <div class="form-group">
                                <label for="click_limit">Tıklama Limiti <span
                                        class="text-muted">(opsiyonel)</span></label>
                                <input type="number" name="click_limit" id="click_limit"
                                    placeholder="Maksimum tıklama sayısı" min="1">
                            </div>
                        </div>
                    </details>

                    <!-- OG Ayarları Kaldırıldı -->

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Kısalt</button>
                </form>
            </div>

            <!-- Note Form -->
            <div id="form-note" class="form-section <?php echo $active_tab === 'note' ? 'active' : ''; ?>">
                <form method="post" action="">
                    <?php echo \App\csrf_input(); ?>
                    <input type="hidden" name="type" value="note">

                    <div class="form-group">
                        <label for="note_title">Başlık <span class="text-muted">(opsiyonel)</span></label>
                        <input type="text" name="note_title" id="note_title" placeholder="Not Başlığı"
                            value="<?php echo \App\e($note_title); ?>">
                    </div>

                    <div class="form-group" style="margin-bottom: 20px;">
                        <label class="d-flex align-items-center" style="cursor: pointer; gap: 8px;">
                            <input type="checkbox" name="is_burn_after_read" value="1"
                                style="width: auto; height: auto;">
                            <span>🔥 Görüldükten sonra sil (Burn After Read)</span>
                        </label>
                        <small class="text-muted" style="display: block; margin-top: 4px;">Aktif edilirse, not bir kez
                            görüntülendikten sonra erişime kapatılır (pasif olur).</small>
                    </div>

                    <div class="form-group">
                        <label for="note_content">Not İçeriği <span class="text-muted">*</span></label>
                        <textarea name="note_content" id="note_content" placeholder="Buraya notunuzu yazın..."
                            style="min-height: 150px;"><?php echo \App\e($note_content); ?></textarea>
                        <script>
                            document.addEventListener("DOMContentLoaded", function () {
                                if (typeof EasyMDE !== 'undefined') {
                                    new EasyMDE({
                                        element: document.getElementById('note_content'),
                                        spellChecker: false,
                                        status: false,
                                        placeholder: "Notunuzu buraya yazın (Markdown desteklenir)...",
                                        minHeight: "150px"
                                    });
                                }
                            });
                        </script>
                    </div>

                    <div class="form-group">
                        <label for="slug_note">Kısa Kod <span class="text-muted">(opsiyonel)</span></label>
                        <input type="text" name="slug" id="slug_note" placeholder="ozel-not-linki"
                            value="<?php echo \App\e($custom_slug); ?>">
                    </div>

                    <details style="margin-bottom: 1rem;">
                        <summary
                            style="cursor: pointer; color: var(--primary-color); font-weight: 500; margin-bottom: 0.5rem;">
                            🔒 Güvenlik Seçenekleri</summary>
                        <div
                            style="padding: 1rem; background: var(--bg-color); border-radius: var(--radius); margin-top: 0.5rem;">
                            <div class="form-group">
                                <label for="note_password">Şifre Koruması <span
                                        class="text-muted">(opsiyonel)</span></label>
                                <input type="password" name="note_password" id="note_password"
                                    placeholder="Notu şifrele...">
                            </div>
                        </div>
                    </details>

                    <button type="submit" class="btn btn-primary" style="width: 100%;">Not Oluştur</button>
                </form>
            </div>
        </div>

        <footer style="margin-top: 40px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
            &copy; <?php echo date('Y'); ?> URL Kısaltıcı & Not Paylaşımı
        </footer>
    </div>

    <script>
        function switchTab(type) {
            document.querySelectorAll('.nav-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.form-section').forEach(f => f.classList.remove('active'));
            document.getElementById('tab-' + type).classList.add('active');
            document.getElementById('form-' + type).classList.add('active');
        }

        function copyShortUrl(btn) {
            var el = document.getElementById('shortUrl');
            if (!el) return;
            if (navigator.clipboard) {
                navigator.clipboard.writeText(el.value).then(function () { showCopied(btn); });
            } else {
                el.select();
                try { document.execCommand('copy'); showCopied(btn); } catch (e) { }
            }
        }

        function showCopied(btn) {
            var orig = btn.textContent;
            btn.textContent = '✓';
            btn.classList.add('btn-success');
            setTimeout(function () {
                btn.textContent = orig;
                btn.classList.remove('btn-success');
            }, 1200);
        }

        // Dark Mode Logic
        const toggleBtn = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Set icon based on current state (script in head already set class)
        if (html.classList.contains('dark-mode')) {
            toggleBtn.textContent = '☀️';
        } else {
            toggleBtn.textContent = '🌙';
        }

        toggleBtn.addEventListener('click', () => {
            html.classList.toggle('dark-mode');
            if (html.classList.contains('dark-mode')) {
                localStorage.setItem('theme', 'dark');
                html.setAttribute('data-theme', 'dark');
                toggleBtn.textContent = '☀️';
            } else {
                localStorage.setItem('theme', 'light');
                html.removeAttribute('data-theme');
                toggleBtn.textContent = '🌙';
            }
        });
    </script>
</body>

</html>