<?php
/**
 * Basit kimlik doğrulama ve oturum işlemleri.
 */

namespace App\auth;

use function App\db\get_config;

/**
 * PHP oturumunu başlatır. Aynı fonksiyon birden fazla kez çağrıldığında herhangi bir sorun çıkarmaz.
 * Güvenlik için HTTPOnly ve SameSite ayarları uygulanır.
 *
 * @return void
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Yönetici girişi yapılmış mı kontrol eder.
 *
 * @return bool
 */
function is_logged_in(): bool
{
    start_session();
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Oturumu sonlandırır ve kullanıcıyı çıkarır.
 *
 * @return void
 */
function logout(): void
{
    start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

/**
 * Kullanıcının kimlik doğrulamasını yapar. Başarılı olursa oturumu ayarlar.
 * Aynı zamanda brute force saldırılarına karşı basit bir rate-limit uygular.
 *
 * @param string $password
 * @return bool Başarılı ise true
 */
function login(string $password): bool
{
    start_session();
    // Brute force koruması
    if (!check_rate_limit()) {
        return false;
    }
    $config = get_config();
    $hash = $config['admin']['password_hash'] ?? '';
    // Parola doğrulama
    if (password_verify($password, $hash)) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $config['admin']['username'];
        // Oturum kimliğini yenileyerek oturum sabitleme saldırılarını önleyelim
        session_regenerate_id(true);
        // Başarılı girişte IP giriş sayacı sıfırlanabilir
        reset_rate_limit();
        return true;
    }
    // Başarısız giriş log'la
    log_failed_attempt();
    return false;
}

/**
 * Giriş yapılmadıysa kullanıcıyı login sayfasına yönlendirir.
 *
 * @return void
 */
function require_login(): void
{
    if (!is_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}

/**
 * Login deneme sayısını dosyada tutarak rate limit uygular.
 * Maksimum 5 deneme, süre 15 dakika.
 *
 * @return bool İzin veriliyorsa true, aksi halde false
 */
function check_rate_limit(): bool
{
    $file = __DIR__ . '/../storage/login_attempts.json';
    $maxAttempts = 5;
    $window = 15 * 60; // seconds
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsData = [];

    if (file_exists($file)) {
        // Use file locking to prevent race conditions
        $handle = fopen($file, 'r');
        if ($handle) {
            flock($handle, LOCK_SH); // Shared lock for reading
            $json = fread($handle, filesize($file) ?: 1);
            flock($handle, LOCK_UN);
            fclose($handle);
            $attemptsData = json_decode($json, true) ?: [];
        }
    }

    $now = time();
    // Clean old entries
    foreach ($attemptsData as $ipKey => $records) {
        $attemptsData[$ipKey] = array_filter($records, static function ($timestamp) use ($now, $window) {
            return $timestamp > $now - $window;
        });
    }

    $currentAttempts = $attemptsData[$ip] ?? [];
    if (count($currentAttempts) >= $maxAttempts) {
        return false;
    }
    return true;
}

/**
 * Başarısız denemeyi kaydeder.
 *
 * @return void
 */
function log_failed_attempt(): void
{
    $file = __DIR__ . '/../storage/login_attempts.json';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsData = [];
    $window = 15 * 60;
    $now = time();

    // Ensure storage directory exists
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // Use exclusive file locking
    $handle = fopen($file, 'c+');
    if (!$handle) {
        return;
    }

    flock($handle, LOCK_EX); // Exclusive lock for read+write

    $size = filesize($file);
    if ($size > 0) {
        $json = fread($handle, $size);
        $attemptsData = json_decode($json, true) ?: [];
    }

    // Clean old entries while we're at it
    foreach ($attemptsData as $ipKey => $records) {
        $attemptsData[$ipKey] = array_values(array_filter($records, static function ($timestamp) use ($now, $window) {
            return $timestamp > $now - $window;
        }));
        if (empty($attemptsData[$ipKey])) {
            unset($attemptsData[$ipKey]);
        }
    }

    $attemptsData[$ip][] = $now;

    // Write back
    fseek($handle, 0);
    ftruncate($handle, 0);
    fwrite($handle, json_encode($attemptsData, JSON_PRETTY_PRINT));

    flock($handle, LOCK_UN);
    fclose($handle);
}

/**
 * Başarılı girişten sonra aynı IP için failed counter'ı sıfırlar.
 *
 * @return void
 */
function reset_rate_limit(): void
{
    $file = __DIR__ . '/../storage/login_attempts.json';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    if (!file_exists($file)) {
        return;
    }

    $handle = fopen($file, 'c+');
    if (!$handle) {
        return;
    }

    flock($handle, LOCK_EX);

    $size = filesize($file);
    if ($size > 0) {
        $json = fread($handle, $size);
        $attemptsData = json_decode($json, true) ?: [];

        if (isset($attemptsData[$ip])) {
            unset($attemptsData[$ip]);

            fseek($handle, 0);
            ftruncate($handle, 0);
            fwrite($handle, json_encode($attemptsData, JSON_PRETTY_PRINT));
        }
    }

    flock($handle, LOCK_UN);
    fclose($handle);
}