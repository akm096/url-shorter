<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/db.php';
require_once __DIR__ . '/../app/auth.php';
require_once __DIR__ . '/../app/csrf.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/security.php';

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
            $error = 'Giriş başarısız. Parola hatalı veya çok fazla deneme yaptınız.';
        }
    }
}
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Girişi</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 40px 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-box {
            background: #fff;
            padding: 30px;
            width: 100%;
            max-width: 380px;
            border: 1px solid #ddd;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .login-box h1 {
            margin: 0 0 20px 0;
            font-size: 24px;
            text-align: center;
        }

        .error {
            background: #ffebee;
            color: #c62828;
            padding: 10px 14px;
            border-radius: 4px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 14px;
        }

        input[type="password"] {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
        }

        input[type="password"]:focus {
            outline: none;
            border-color: #333;
        }

        button {
            width: 100%;
            margin-top: 16px;
            padding: 12px;
            background: #333;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }

        button:hover {
            background: #555;
        }

        .back {
            text-align: center;
            margin-top: 16px;
        }

        .back a {
            color: #666;
            font-size: 13px;
            text-decoration: none;
        }

        .back a:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-box">
        <h1>Yönetici Girişi</h1>
        <?php if ($error): ?>
            <div class="error"><?php echo \App\e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="">
            <?php echo \App\csrf_input(); ?>
            <label for="password">Parola</label>
            <input type="password" name="password" id="password" required autofocus>
            <button type="submit">Giriş Yap</button>
        </form>
        <div class="back"><a href="/">← Ana Sayfa</a></div>
    </div>
</body>

</html>