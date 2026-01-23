<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/app/db.php';

use App\db;

header('Content-Type: application/json; charset=utf-8');

$links = db\get_all_links(null);

echo json_encode([
  'count' => is_array($links) ? count($links) : -1,
  'links' => $links,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
