<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/auth.php';

// Oturumu sonlandır
\App\auth\logout();

// Giriş sayfasına yönlendir
header('Location: /');
exit;