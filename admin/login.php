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


// Eğer giriş yapılmışsa doğrudan panel ana sayfasına yönlendir
if (auth\is_logged_in()) {
    header('Location: index.php');
    exit;
}

$error = '';
// Form gönderimi
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
            $error = 'Giriş başarısız. Parola hatalı veya çok fazla deneme yaptınız.';
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <title>Yönetici Girişi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            padding: 40px;
        }

        .login-box {
            background-color: white;
            padding: 20px;
            margin: 0 auto;
            max-width: 400px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }

        .login-box h1 {
            margin-top: 0;
        }

        .error {
            color: red;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h1>Yönetici Girişi</h1>
        <?php if ($error): ?>
            <p class="error"><?php echo \App\e($error); ?></p>
        <?php endif; ?>
        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>
            <div>
                <label for="password">Parola:</label><br>
                <input type="password" name="password" id="password" required>
            </div>
            <br>
            <button type="submit">Giriş Yap</button>
        </form>
    </div>
</body>

</html>