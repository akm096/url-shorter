<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentInfo = pathinfo($_SERVER['PHP_SELF']);
$currentPage = $currentInfo['basename'];
?><!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - URL Kısaltıcı</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../assets/css/admin.css?v=<?php echo time(); ?>">
    <script src="../assets/js/qrcode.js"></script>

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
    <div class="container">
        <header class="top-bar">
            <div class="d-flex">
                <h1>Admin Panel</h1>
            </div>
            <nav class="nav-links d-flex">
                <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Linkler</a>
                <a href="notes.php" class="<?php echo $currentPage === 'notes.php' ? 'active' : ''; ?>">Notlar</a>
                <button id="themeToggle" class="theme-toggle" title="Karanlık Mod">🌙</button>
                <a href="logout.php" class="logout">Çıkış</a>
            </nav>
        </header>

        <main>