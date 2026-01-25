<?php
declare(strict_types=1);

/**
 * Expired or limit reached page
 */

require_once __DIR__ . '/app/functions.php';
require_once __DIR__ . '/app/security.php';

\App\security\send_security_headers();
?>
<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İçerik Kullanılamıyor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/admin.css?v=<?php echo time(); ?>">
    <script>
        (function () {
            const savedTheme = localStorage.getItem('theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark-mode');
                document.documentElement.setAttribute('data-theme', 'dark');
            }
        })();
    </script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .expired-box {
            width: 100%;
            max-width: 400px;
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .expired-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .expired-box h1 {
            margin-bottom: 0.5rem;
            font-size: 1.25rem;
        }

        .expired-box p {
            color: var(--text-muted);
            margin-bottom: 1.5rem;
        }
    </style>
</head>

<body>
    <div class="expired-box">
        <div class="expired-icon">⏰</div>
        <h1>İçerik Kullanılamıyor</h1>
        <p>Bu bağlantının süresi dolmuş veya kullanım limitine ulaşılmış.</p>

        <div style="margin-top: 1.5rem;">
            <a href="/" class="btn btn-primary" style="font-size: 0.9rem;">Yeni Link Oluştur</a>
        </div>
    </div>
</body>

</html>