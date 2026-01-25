<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';

// Send security headers
\App\security\send_security_headers();

use App\auth;

if (auth\is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!\App\verify_csrf($token)) {
        $error = 'Geçersiz form isteği.';
    } else {
        $password = $_POST['password'] ?? '';
        if (auth\login($password)) {
            header('Location: index.php');
            exit;
        } else {
            sleep(1); // Brute-force önleme
            $error = 'Giriş başarısız. Parola hatalı.';
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">

    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--bg-color);
            padding: 20px;
        }

        .login-box {
            width: 100%;
            max-width: 400px;
            background: var(--card-bg);
            padding: 2.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
        }

        .login-box h1 {
            text-align: center;
            margin-bottom: 1.5rem;
            color: var(--text-color);
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
    <div class="login-box">
        <h1>Yönetici Girişi</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo \App\e($error); ?></div>
        <?php endif; ?>

        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>
            <div class="form-group">
                <label for="password">Parola</label>
                <input type="password" name="password" id="password" required autofocus placeholder="Yönetici parolası">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem;">Giriş Yap</button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem;">
            <a href="/" class="btn btn-outline" style="font-size: 0.85rem;">← Ana Sayfa</a>
        </div>
    </div>
</body>

</html>