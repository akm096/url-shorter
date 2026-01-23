<?php
/**
 * Basit CSRF token üreteci ve doğrulayıcı fonksiyonlar.
 */

namespace App;

use function App\auth\start_session;

/**
 * CSRF token oluşturur (oturumda yoksa) ve döndürür.
 *
 * @return string
 */
function csrf_token(): string
{
    start_session();
    if (!isset($_SESSION['csrf_token'])) {
        // 32 bayt uzunluğunda rastgele token
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verilen token'ı doğrular. Eşleşme yoksa false döner.
 * @param string|null $token
 * @return bool
 */
function verify_csrf(?string $token): bool
{
    start_session();
    if (!$token || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Formlara eklemek için gizli bir CSRF input'u döndürür.
 * @return string
 */
function csrf_input(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}