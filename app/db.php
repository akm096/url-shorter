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
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
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
                active INTEGER NOT NULL DEFAULT 1,
                password_hash TEXT DEFAULT NULL,
                expires_at TEXT DEFAULT NULL,
                click_limit INTEGER DEFAULT NULL
            )'
        );
        // Migration: Add new columns if they don't exist
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN password_hash TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN expires_at TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN click_limit INTEGER DEFAULT NULL');
        } catch (\Exception $e) {
        }
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
                active TINYINT(1) NOT NULL DEFAULT 1,
                password_hash VARCHAR(255) DEFAULT NULL,
                expires_at DATETIME DEFAULT NULL,
                click_limit INT UNSIGNED DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Migration: Add new columns if they don't exist (MySQL ignores errors for duplicate column)
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN expires_at DATETIME DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN click_limit INT UNSIGNED DEFAULT NULL');
        } catch (\Exception $e) {
        }

        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL UNIQUE,
                content TEXT NOT NULL,
                title TEXT DEFAULT NULL,
                view_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                password_hash VARCHAR(255) DEFAULT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Migration for notes
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL');
        } catch (\Exception $e) {
        }

        // İstatistik tablosu (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS link_stats (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                link_id INT UNSIGNED NOT NULL,
                browser VARCHAR(50) DEFAULT NULL,
                os VARCHAR(50) DEFAULT NULL,
                device_type VARCHAR(20) DEFAULT NULL,
                referer VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Not İstatistik tablosu (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS note_stats (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                note_id INT UNSIGNED NOT NULL,
                browser VARCHAR(50) DEFAULT NULL,
                os VARCHAR(50) DEFAULT NULL,
                device_type VARCHAR(20) DEFAULT NULL,
                referer VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
    }

    // SQLite için de notes tablosu ekleyelim (yukarıdaki if bloğuna ek olarak)
    if ($driver === 'sqlite') {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                content TEXT NOT NULL,
                title TEXT,
                view_count INTEGER NOT NULL DEFAULT 0,
                created_at TEXT NOT NULL,
                active INTEGER NOT NULL DEFAULT 1,
                password_hash TEXT DEFAULT NULL
            )'
        );
        // Migration for notes
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN password_hash TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }

        // İstatistik tablosu (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS link_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                link_id INTEGER NOT NULL,
                browser TEXT,
                os TEXT,
                device_type TEXT,
                referer TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
            )'
        );

        // Not İstatistik tablosu (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS note_stats (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                note_id INTEGER NOT NULL,
                browser TEXT,
                os TEXT,
                device_type TEXT,
                referer TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            )'
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
        'INSERT INTO links (slug, target_url, title, redirect_type, click_count, created_at, active, password_hash, expires_at, click_limit)
         VALUES (:slug, :target_url, :title, :redirect_type, 0, :created_at, :active, :password_hash, :expires_at, :click_limit)'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':target_url' => $data['target_url'],
        ':title' => $data['title'] ?? null,
        ':redirect_type' => $data['redirect_type'],
        ':created_at' => $data['created_at'],
        ':active' => $data['active'] ?? 1,
        ':password_hash' => $data['password_hash'] ?? null,
        ':expires_at' => $data['expires_at'] ?? null,
        ':click_limit' => $data['click_limit'] ?? null,
    ]);
    return (int) $pdo->lastInsertId();
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
        'UPDATE links SET slug = :slug, target_url = :target_url, title = :title, redirect_type = :redirect_type, active = :active, password_hash = :password_hash, expires_at = :expires_at, click_limit = :click_limit
         WHERE id = :id'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':target_url' => $data['target_url'],
        ':title' => $data['title'] ?? null,
        ':redirect_type' => $data['redirect_type'],
        ':active' => $data['active'],
        ':password_hash' => $data['password_hash'] ?? null,
        ':expires_at' => $data['expires_at'] ?? null,
        ':click_limit' => $data['click_limit'] ?? null,
        ':id' => $id,
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
        'count' => (int) ($row['count'] ?? 0),
        'clicks' => (int) ($row['clicks'] ?? 0),
    ];
}

/**
 * Belirli bir slug için not kaydını getirir.
 * @param string $slug
 * @return array|null
 */
function get_note_by_slug(string $slug): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM notes WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Yeni bir not ekler ve id'sini döndürür.
 * @param array $data
 * @return int inserted ID
 */
function insert_note(array $data): int
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO notes (slug, content, title, view_count, created_at, active, password_hash)
         VALUES (:slug, :content, :title, 0, :created_at, :active, :password_hash)'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':content' => $data['content'],
        ':title' => $data['title'] ?? null,
        ':created_at' => $data['created_at'],
        ':active' => $data['active'] ?? 1,
        ':password_hash' => $data['password_hash'] ?? null,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * ID ile not kaydını siler.
 * @param int $id
 * @return void
 */
function delete_note(int $id): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('DELETE FROM notes WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Notun durumunu (aktif/pasif) değiştirir.
 * @param int $id
 * @param int $status
 * @return void
 */
function toggle_note_status(int $id, int $status): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE notes SET active = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

/**
 * Mevcut notu günceller.
 * @param int   $id
 * @param array $data
 * @return void
 */
function update_note(int $id, array $data): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'UPDATE notes SET slug = :slug, content = :content, title = :title, active = :active, password_hash = :password_hash
         WHERE id = :id'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':content' => $data['content'],
        ':title' => $data['title'] ?? null,
        ':active' => $data['active'],
        ':password_hash' => $data['password_hash'] ?? null,
        ':id' => $id,
    ]);
}

/**
 * Tüm notları getirir.
 * @param string|null $search
 * @return array
 */
function get_all_notes(?string $search = null): array
{
    $pdo = get_db();

    if ($search !== null && trim($search) !== '') {
        $like = '%' . trim($search) . '%';
        $stmt = $pdo->prepare(
            'SELECT * FROM notes WHERE slug LIKE ? OR title LIKE ? OR content LIKE ? ORDER BY id DESC'
        );
        $stmt->execute([$like, $like, $like]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->query('SELECT * FROM notes ORDER BY id DESC');
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Not görüntüleme sayısını bir arttırır.
 * @param int $id
 * @return void
 */
function increment_note_view(int $id): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE notes SET view_count = view_count + 1 WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Not istatistiklerini verir.
 * @return array [count, views]
 */
function get_note_stats(): array
{
    $pdo = get_db();
    $row = $pdo->query('SELECT COUNT(*) as count, SUM(view_count) as views FROM notes')->fetch();
    return [
        'count' => (int) ($row['count'] ?? 0),
        'views' => (int) ($row['views'] ?? 0),
    ];
}

/**
 * Link tıklama detaylarını kaydeder (Analitik için).
 * @param int $linkId
 * @return void
 */
function log_link_click(int $linkId): void
{
    $pdo = get_db();

    // Basit User-Agent analizi
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = 'Unknown';
    $os = 'Unknown';
    $deviceType = 'Desktop'; // Default

    // Çok basit tespit mantığı
    if (stripos($ua, 'Firefox') !== false)
        $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome') !== false)
        $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false)
        $browser = 'Safari';
    elseif (stripos($ua, 'Edge') !== false)
        $browser = 'Edge';
    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false)
        $browser = 'IE';

    if (stripos($ua, 'Windows') !== false)
        $os = 'Windows';
    elseif (stripos($ua, 'Mac') !== false)
        $os = 'MacOS';
    elseif (stripos($ua, 'Linux') !== false)
        $os = 'Linux';
    elseif (stripos($ua, 'Android') !== false)
        $os = 'Android';
    elseif (stripos($ua, 'iOS') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false)
        $os = 'iOS';

    if (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) {
        $deviceType = 'Mobile';
    } elseif (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false) {
        $deviceType = 'Tablet';
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    $createdAt = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO link_stats (link_id, browser, os, device_type, referer, created_at)
         VALUES (:link_id, :browser, :os, :device_type, :referer, :created_at)'
    );
    $stmt->execute([
        ':link_id' => $linkId,
        ':browser' => $browser,
        ':os' => $os,
        ':device_type' => $deviceType,
        ':referer' => $referer,
        ':created_at' => $createdAt
    ]);
}

/**
 * Son N günlük tıklama istatistiklerini getirir.
 * @param int $days
 * @return array
 */
/**
 * Son N günlük tıklama istatistiklerini getirir.
 * @param int $days
 * @param int|null $linkId
 * @return array
 */
function get_daily_click_stats(int $days = 7, ?int $linkId = null): array
{
    $pdo = get_db();
    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

    // Güvenlik için int dönüşümü
    $days = (int) $days;

    if ($driver === 'sqlite') {
        $dateFormat = '%Y-%m-%d';
        $dateCondition = "created_at >= date('now', '-$days days')";
        $dateCol = "strftime('$dateFormat', created_at)";
    } else {
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        $dateCol = "DATE(created_at)";
    }

    $whereSql = "WHERE $dateCondition";
    if ($linkId !== null) {
        $whereSql .= " AND link_id = " . (int) $linkId;
    }

    $sql = "SELECT $dateCol as date, COUNT(*) as count 
            FROM link_stats 
            $whereSql
            GROUP BY date 
            ORDER BY date ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Tarayıcı, İşletim Sistemi gibi dağılımları getirir.
 * @param string $type browser|os|device_type
 * @param int|null $linkId
 * @return array
 */
function get_distribution_stats(string $type, ?int $linkId = null): array
{
    $validTypes = ['browser', 'os', 'device_type'];
    if (!in_array($type, $validTypes))
        return []; // Basit güvenlik önlemi

    $pdo = get_db();

    $whereSql = "";
    if ($linkId !== null) {
        $whereSql = "WHERE link_id = " . (int) $linkId;
    }

    $stmt = $pdo->query("SELECT $type as name, COUNT(*) as count FROM link_stats $whereSql GROUP BY $type ORDER BY count DESC LIMIT 10");
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Not görüntüleme detaylarını kaydeder (Analitik için).
 * @param int $noteId
 * @return void
 */
function log_note_view(int $noteId): void
{
    $pdo = get_db();

    // User-Agent analizi (log_link_click ile aynı)
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $browser = 'Unknown';
    $os = 'Unknown';
    $deviceType = 'Desktop'; // Default

    if (stripos($ua, 'Firefox') !== false)
        $browser = 'Firefox';
    elseif (stripos($ua, 'Chrome') !== false)
        $browser = 'Chrome';
    elseif (stripos($ua, 'Safari') !== false)
        $browser = 'Safari';
    elseif (stripos($ua, 'Edge') !== false)
        $browser = 'Edge';
    elseif (stripos($ua, 'MSIE') !== false || stripos($ua, 'Trident') !== false)
        $browser = 'IE';

    if (stripos($ua, 'Windows') !== false)
        $os = 'Windows';
    elseif (stripos($ua, 'Mac') !== false)
        $os = 'MacOS';
    elseif (stripos($ua, 'Linux') !== false)
        $os = 'Linux';
    elseif (stripos($ua, 'Android') !== false)
        $os = 'Android';
    elseif (stripos($ua, 'iOS') !== false || stripos($ua, 'iPhone') !== false || stripos($ua, 'iPad') !== false)
        $os = 'iOS';

    if (stripos($ua, 'Mobile') !== false || stripos($ua, 'Android') !== false || stripos($ua, 'iPhone') !== false) {
        $deviceType = 'Mobile';
    } elseif (stripos($ua, 'Tablet') !== false || stripos($ua, 'iPad') !== false) {
        $deviceType = 'Tablet';
    }

    $referer = $_SERVER['HTTP_REFERER'] ?? null;
    $createdAt = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare(
        'INSERT INTO note_stats (note_id, browser, os, device_type, referer, created_at)
         VALUES (:note_id, :browser, :os, :device_type, :referer, :created_at)'
    );
    $stmt->execute([
        ':note_id' => $noteId,
        ':browser' => $browser,
        ':os' => $os,
        ':device_type' => $deviceType,
        ':referer' => $referer,
        ':created_at' => $createdAt
    ]);
}

/**
 * Notlar için günlük görüntülenme istatistikleri.
 * @param int $days
 * @param int|null $noteId
 * @return array
 */
function get_daily_note_view_stats(int $days = 7, ?int $noteId = null): array
{
    $pdo = get_db();
    $driver = $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);

    $days = (int) $days;

    if ($driver === 'sqlite') {
        $dateFormat = '%Y-%m-%d';
        $dateCondition = "created_at >= date('now', '-$days days')";
        $dateCol = "strftime('$dateFormat', created_at)";
    } else {
        $dateCondition = "created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)";
        $dateCol = "DATE(created_at)";
    }

    $whereSql = "WHERE $dateCondition";
    if ($noteId !== null) {
        $whereSql .= " AND note_id = " . (int) $noteId;
    }

    $sql = "SELECT $dateCol as date, COUNT(*) as count 
            FROM note_stats 
            $whereSql
            GROUP BY date 
            ORDER BY date ASC";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Notlar için dağılım istatistikleri.
 * @param string $type
 * @param int|null $noteId
 * @return array
 */
function get_note_distribution_stats(string $type, ?int $noteId = null): array
{
    $validTypes = ['browser', 'os', 'device_type'];
    if (!in_array($type, $validTypes))
        return [];

    $pdo = get_db();

    $whereSql = "";
    if ($noteId !== null) {
        $whereSql = "WHERE note_id = " . (int) $noteId;
    }

    $stmt = $pdo->query("SELECT $type as name, COUNT(*) as count FROM note_stats $whereSql GROUP BY $type ORDER BY count DESC LIMIT 10");
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}