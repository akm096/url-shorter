<?php
return [
  'db' => [
    'driver' => 'mysql',
    'host' => 'sql100.infinityfree.com',
    'dbname' => 'if0_XXXXXXX_short',
    'username' => 'if0_XXXXXXX',
    'password' => 'PUT_YOUR_PASSWORD_HERE',
    'charset' => 'utf8mb4',
    'sqlite_path' => __DIR__ . '/../storage/database.sqlite',
  ],
  'admin' => [
    'username' => 'admin',
    'password_hash' => 'PUT_HASH_HERE',
  ],
  'redirect_default' => 302,
  'base_url' => 'http://wojo.indevs.in',
];
