<?php
/**
 * Genel yardımcı fonksiyonlar.
 */

namespace App;

use App\db;

/**
 * XSS önleme amaçlı çıktı kaçış fonksiyonu.
 *
 * @param string|null $string
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Kullanıcı tarafından girilen veya otomatik üretilen slug'ı doğrular.
 * Sadece a-z, A-Z, 0-9, tire ve alt çizgiye izin verir.
 *
 * @param string $slug
 * @return bool
 */
function validate_slug(string $slug): bool
{
    return (bool)preg_match('/^[A-Za-z0-9_-]+$/', $slug);
}

/**
 * URL'nin geçerli olup olmadığını kontrol eder (http veya https).
 *
 * @param string $url
 * @return bool
 */
function validate_url(string $url): bool
{
    $filtered = filter_var($url, FILTER_VALIDATE_URL);
    if (!$filtered) {
        return false;
    }
    return (str_starts_with(strtolower($filtered), 'http://') || str_starts_with(strtolower($filtered), 'https://'));
}

/**
 * Rastgele slug üretir ve varolan slug ile çakışmadığından emin olur.
 *
 * @param int $length
 * @return string
 */
function generate_random_slug(int $length = 6): string
{
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $maxIndex = strlen($characters) - 1;
    $pdo = db\get_db();
    do {
        $slug = '';
        for ($i = 0; $i < $length; $i++) {
            $slug .= $characters[random_int(0, $maxIndex)];
        }
        // slug kullanılıyor mu? varsa yeniden üret
        $existing = db\get_link_by_slug($slug);
    } while ($existing !== null);
    return $slug;
}