<?php
/**
 * Veritabanı bağlantı ve hazırlık fonksiyonları.
 *
 * Bu dosya PDO üzerinden hem MySQL hem de SQLite bağlantılarını destekler.
 * Sürücü seçimi app/config.php dosyasındaki 'db.driver' anahtarı ile yapılır.
 */

namespace App\db;

use PDO;
use PDOException;

/**
 * Uygulama konfigürasyonunu yükler.
 *
 * @return array
 */
function get_config(): array
{
    static $config;
    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }
    return $config;
}

/**
 * PDO nesnesi oluşturur ve döndürür. Bağlantı bir kere oluşturulur, sonraki çağrılarda aynı nesne döner.
 *
 * @return PDO
 */
function get_db(): PDO
{
    static $pdo;
    if ($pdo !== null) {
        return $pdo;
    }

    $config = get_config();
    $dbConf = $config['db'];

    $driver = $dbConf['driver'] ?? 'mysql';
    try {
        if ($driver === 'sqlite') {
            $dsn = 'sqlite:' . $dbConf['sqlite_path'];
            $pdo = new PDO($dsn);
            // SQLite'da yabancı anahtar desteğini açalım
            $pdo->exec('PRAGMA foreign_keys = ON');
        } elseif ($driver === 'mysql') {
            $host = $dbConf['host'];
            $dbname = $dbConf['dbname'];
            $charset = $dbConf['charset'] ?? 'utf8mb4';
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $dbname, $charset);
            $username = $dbConf['username'];
            $password = $dbConf['password'];
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, $username, $password, $options);
        } else {
            throw new \RuntimeException('Desteklenmeyen veritabanı sürücüsü: ' . $driver);
        }
        // Veritabanı tablolarını hazırlayalım
        initialize_database($pdo, $driver);
        return $pdo;
    } catch (PDOException $e) {
        // Bağlantı hatası durumunda kullanıcıya temiz bir mesaj vermek için özel hata yakalayabiliriz
        die('Veritabanı bağlantısı başarısız: ' . htmlspecialchars($e->getMessage()));
    }
}

/**
 * Veritabanı tablolarını oluşturur (varsa yoksa). Her iki sürücü için farklı SQL cümleleri kullanılır.
 *
 * @param PDO    $pdo
 * @param string $driver
 * @return void
 */
function initialize_database(PDO $pdo, string $driver): void
{
    if ($driver === 'sqlite') {
        // SQLite için tablo oluşturma
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                target_url TEXT NOT NULL,
                title TEXT,
                redirect_type INTEGER NOT NULL DEFAULT 302,
                click_count INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1
            )'
        );
    } elseif ($driver === 'mysql') {
        // MySQL için tablo oluşturma
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL UNIQUE,
                target_url TEXT NOT NULL,
                title TEXT DEFAULT NULL,
                redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 302,
                click_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }
}

/**
 * Belirli bir slug için link kaydını getirir.
 * @param string $slug
 * @return array|null
 */
function get_link_by_slug(string $slug): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM links WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Yeni bir link ekler ve id'sini döndürür.
 * @param array $data
 * @return int inserted ID
 */
function insert_link(array $data): int
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO links (slug, target_url, title, redirect_type, click_count, created_at, active)
         VALUES (:slug, :target_url, :title, :redirect_type, 0, :created_at, :active)'
    );
    $stmt->execute([
        ':slug'          => $data['slug'],
        ':target_url'    => $data['target_url'],
        ':title'         => $data['title'] ?? null,
        ':redirect_type' => $data['redirect_type'],
        ':created_at'    => $data['created_at'],
        ':active'        => $data['active'] ?? 1,
    ]);
    return (int)$pdo->lastInsertId();
}

/**
 * Mevcut linki günceller.
 * @param int   $id
 * @param array $data
 * @return void
 */
function update_link(int $id, array $data): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'UPDATE links SET slug = :slug, target_url = :target_url, title = :title, redirect_type = :redirect_type, active = :active
         WHERE id = :id'
    );
    $stmt->execute([
        ':slug'          => $data['slug'],
        ':target_url'    => $data['target_url'],
        ':title'         => $data['title'] ?? null,
        ':redirect_type' => $data['redirect_type'],
        ':active'        => $data['active'],
        ':id'            => $id,
    ]);
}

/**
 * ID ile link kaydını siler.
 * @param int $id
 * @return void
 */
function delete_link(int $id): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Tüm linkleri getirir. Opsiyonel arama parametresi ile slug veya URL'de filtreleme yapar.
 * @param string|null $search
 * @return array
 */
function get_all_links(?string $search = null): array
{
    $pdo = get_db();

    if ($search !== null && trim($search) !== '') {
        $like = '%' . trim($search) . '%';
        $stmt = $pdo->prepare(
            'SELECT * FROM links WHERE slug LIKE ? OR target_url LIKE ? ORDER BY id DESC'
        );
        $stmt->execute([$like, $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->query('SELECT * FROM links ORDER BY id DESC');
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Link tıklama sayısını bir arttırır.
 * @param int $id
 * @return void
 */
function increment_click_count(int $id): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE links SET click_count = click_count + 1 WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Toplam link sayısı ve toplam tıklama sayısını verir.
 * @return array [linkCount, clickCount]
 */
function get_stats(): array
{
    $pdo = get_db();
    $row = $pdo->query('SELECT COUNT(*) as count, SUM(click_count) as clicks FROM links')->fetch();
    return [
        'count'  => (int)($row['count'] ?? 0),
        'clicks' => (int)($row['clicks'] ?? 0),
    ];
}