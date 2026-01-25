<?php
/**
 * Application Configuration
 * 
 * Copy this file to config.php and fill in your credentials.
 * NEVER commit config.php to version control!
 * 
 * To generate a password hash, use:
 * php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
 */
return [
  // Database configuration
  'db' => [
    // Driver: 'mysql' or 'sqlite'
    'driver' => 'mysql',

    // MySQL settings (InfinityFree)
    'host' => 'sql100.infinityfree.com',
    'dbname' => 'if0_XXXXXXX_short',
    'username' => 'if0_XXXXXXX',
    'password' => 'PUT_YOUR_PASSWORD_HERE',
    'charset' => 'utf8mb4',

    // SQLite settings (alternative for local development)
    'sqlite_path' => __DIR__ . '/../storage/database.sqlite',
  ],

  // Admin panel authentication
  'admin' => [
    'username' => 'admin',
    // Generate with: php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
    'password_hash' => 'PUT_HASH_HERE',
  ],

  // Optional API Key for external access to stats (X-API-KEY header)
  // 'api_key' => 'YOUR_SECRET_API_KEY',

  // Default redirect type: 301 (permanent) or 302 (temporary)
  'redirect_default' => 302,

  // Base URL of your site (without trailing slash)
  'base_url' => 'https://your-domain.com',

  // Timezone for date/time functions
  'timezone' => 'UTC',
];
