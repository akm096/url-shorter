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
        $existingLink = db\get_link_by_slug($slug);
        $existingNote = db\get_note_by_slug($slug);
    } while ($existingLink !== null || $existingNote !== null);
    return $slug;
}

/**
 * Get country code from IP address using ip-api.com (free for development/hobby).
 * 
 * @param string $ip
 * @return string|null Two letter country code or NULL
 */
function get_ip_country(string $ip): ?string
{
    if (in_array($ip, ['127.0.0.1', '::1'])) {
        return 'TR'; // Local test default
    }

    $url = "http://ip-api.com/json/{$ip}?fields=status,countryCode";

    // Try curl first
    if (function_exists('curl_init')) {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response && !$error) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    return $data['countryCode'] ?? null;
                }
            }
        } catch (\Exception $e) {
            // Fall through to file_get_contents
        }
    }

    // Fallback to file_get_contents (works on many shared hosts)
    if (ini_get('allow_url_fopen')) {
        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 2,
                    'ignore_errors' => true
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response) {
                $data = json_decode($response, true);
                if ($data && ($data['status'] ?? '') === 'success') {
                    return $data['countryCode'] ?? null;
                }
            }
        } catch (\Exception $e) {
            // Ignore
        }
    }

    return null;
}