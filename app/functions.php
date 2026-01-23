<?php
/**
 * Genel yardımcı fonksiyonlar.
 */

namespace App;

use App\db;

/**
 * Reserved slugs that cannot be used for short URLs.
 * These are system paths that could cause routing conflicts.
 */
const RESERVED_SLUGS = [
    'admin',
    'app',
    'storage',
    'go',
    'api',
    'login',
    'logout',
    'register',
    'dashboard',
    'assets',
    'static',
    'css',
    'js',
    'images',
    'img',
    'fonts',
    'favicon.ico',
    'robots.txt',
    'sitemap.xml',
    '.htaccess',
    '.git',
];

/**
 * Maximum allowed slug length.
 */
const MAX_SLUG_LENGTH = 50;

/**
 * Minimum allowed slug length.
 */
const MIN_SLUG_LENGTH = 1;

/**
 * Maximum allowed URL length.
 */
const MAX_URL_LENGTH = 2048;

/**
 * XSS önleme amaçlı çıktı kaçış fonksiyonu.
 *
 * @param string|null $string
 * @return string
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Checks if slug is in the reserved list.
 *
 * @param string $slug
 * @return bool True if reserved (not allowed)
 */
function is_reserved_slug(string $slug): bool
{
    return in_array(strtolower($slug), array_map('strtolower', RESERVED_SLUGS), true);
}

/**
 * Validates slug length.
 *
 * @param string $slug
 * @return bool True if valid length
 */
function validate_slug_length(string $slug): bool
{
    $len = strlen($slug);
    return $len >= MIN_SLUG_LENGTH && $len <= MAX_SLUG_LENGTH;
}

/**
 * Validates URL length.
 *
 * @param string $url
 * @return bool True if valid length
 */
function validate_url_length(string $url): bool
{
    return strlen($url) <= MAX_URL_LENGTH;
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
    return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $slug);
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