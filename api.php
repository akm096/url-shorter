<?php
declare(strict_types=1);

/**
 * API Endpoint for URL Shortener
 * 
 * Supports:
 * - POST /api.php?action=shorten
 * - POST /api.php?action=note
 * - GET  /api.php?action=stats&code=<code> (Requires Auth or API Key)
 */

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/functions.php';
require_once __DIR__ . '/app/auth.php';

use App\db;

// Set headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type, X-API-KEY');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$config = db\get_config();

// Helper to send JSON response
function send_json($data, $code = 200)
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// Helper to check authentication
function check_auth($config)
{
    // 1. Check Session
    if (\App\auth\is_logged_in()) {
        return true;
    }

    // 2. Check API Key
    $headers = getallheaders();
    $apiKey = $headers['X-API-KEY'] ?? $_GET['api_key'] ?? null;
    $configuredKey = $config['api_key'] ?? null;

    if ($configuredKey && $apiKey === $configuredKey) {
        return true;
    }

    return false;
}

// Parse input
$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $_GET['action'] ?? $input['action'] ?? null;

if (!$action) {
    send_json(['error' => 'Action required (shorten, note, stats)'], 400);
}

// --- Action: Shorten URL ---
if ($action === 'shorten') {
    if (!check_auth($config)) {
        send_json(['error' => 'Unauthorized'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['error' => 'Method not allowed'], 405);
    }

    $target_url = trim($input['target_url'] ?? '');

    // Telegram'dan gelen komutları (/link https://...) temizle
    $target_url = preg_replace('/^\/(link|shorten)\s+/', '', $target_url);

    $custom_slug = trim($input['slug'] ?? '');
    $title = trim($input['title'] ?? '');

    // Validation
    if (!\App\validate_url($target_url)) {
        send_json(['error' => 'Invalid URL'], 400);
    }
    if (!\App\validate_url_length($target_url)) {
        send_json(['error' => 'URL too long'], 400);
    }

    // Slug logic
    if ($custom_slug) {
        if (!\App\validate_slug($custom_slug)) {
            send_json(['error' => 'Invalid slug format'], 400);
        }
        if (!\App\validate_slug_length($custom_slug)) {
            send_json(['error' => 'Slug length invalid'], 400);
        }
        if (\App\is_reserved_slug($custom_slug)) {
            send_json(['error' => 'Slug is reserved'], 400);
        }
        if (db\get_link_by_slug($custom_slug) || db\get_note_by_slug($custom_slug)) {
            send_json(['error' => 'Slug already in use'], 409);
        }
    } else {
        $custom_slug = \App\generate_random_slug(random_int(6, 8));
    }

    // Optional params
    $password = $input['password'] ?? null;
    $expires_at = $input['expires_at'] ?? null;
    $click_limit = $input['click_limit'] ?? null;

    $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;
    // Basic date validation could be added here

    try {
        db\insert_link([
            'slug' => $custom_slug,
            'target_url' => $target_url,
            'title' => $title,
            'redirect_type' => (int) ($config['redirect_default'] ?? 302),
            'created_at' => date('Y-m-d H:i:s'),
            'active' => 1,
            'password_hash' => $password_hash,
            'expires_at' => $expires_at,
            'click_limit' => $click_limit ? (int) $click_limit : null,
        ]);

        $baseUrl = rtrim($config['base_url'] ?? 'http://' . $_SERVER['HTTP_HOST'], '/');
        $shortUrl = $baseUrl . '/' . $custom_slug;

        send_json([
            'success' => true,
            'slug' => $custom_slug,
            'short_url' => $shortUrl,
            'target_url' => $target_url
        ], 201);

    } catch (\PDOException $e) {
        if ($e->getCode() == 23000) {
            send_json(['error' => 'Slug collision, please try again'], 409);
        }
        send_json(['error' => 'Database error'], 500);
    }
}

// --- Action: Create Note ---
if ($action === 'note') {
    if (!check_auth($config)) {
        send_json(['error' => 'Unauthorized'], 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        send_json(['error' => 'Method not allowed'], 405);
    }

    $content = trim($input['content'] ?? '');
    if (!$content) {
        send_json(['error' => 'Content cannot be empty'], 400);
    }

    $title = trim($input['title'] ?? '');
    $custom_slug = trim($input['slug'] ?? '');

    // Slug logic
    if ($custom_slug) {
        if (!\App\validate_slug($custom_slug)) {
            send_json(['error' => 'Invalid slug format'], 400);
        }
        if (db\get_link_by_slug($custom_slug) || db\get_note_by_slug($custom_slug)) {
            send_json(['error' => 'Slug already in use'], 409);
        }
    } else {
        $custom_slug = \App\generate_random_slug(random_int(6, 8));
    }

    $password = $input['password'] ?? null;
    $password_hash = $password ? password_hash($password, PASSWORD_DEFAULT) : null;

    try {
        db\insert_note([
            'slug' => $custom_slug,
            'content' => $content,
            'title' => $title,
            'created_at' => date('Y-m-d H:i:s'),
            'active' => 1,
            'password_hash' => $password_hash,
        ]);

        $baseUrl = rtrim($config['base_url'] ?? 'http://' . $_SERVER['HTTP_HOST'], '/');
        $shortUrl = $baseUrl . '/' . $custom_slug;

        send_json([
            'success' => true,
            'slug' => $custom_slug,
            'short_url' => $shortUrl,
            'type' => 'note'
        ], 201);

    } catch (\PDOException $e) {
        send_json(['error' => 'Database error'], 500);
    }
}

// --- Action: Stats (Protected) ---
if ($action === 'stats') {
    if (!check_auth($config)) {
        send_json(['error' => 'Unauthorized'], 401);
    }

    $slug = $_GET['slug'] ?? $input['slug'] ?? null;
    if (!$slug) {
        send_json(['error' => 'Slug required'], 400);
    }

    // Try link first
    $link = db\get_link_by_slug($slug);
    if ($link) {
        $stats = db\get_stats(); // Global stats, maybe not what we want.
        // We want specific link stats
        $daily = db\get_daily_click_stats(30, (int) $link['id']);
        send_json([
            'type' => 'link',
            'data' => $link,
            'stats' => $daily
        ]);
    }

    $note = db\get_note_by_slug($slug);
    if ($note) {
        $daily = db\get_daily_note_view_stats(30, (int) $note['id']);
        send_json([
            'type' => 'note',
            'data' => $note,
            'stats' => $daily
        ]);
    }

    send_json(['error' => 'Not found'], 404);
}

send_json(['error' => 'Invalid action'], 400);
