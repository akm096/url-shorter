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
        // Veritabanı tablolarını hazırlayalım (Sadece ilk kurulumda)
        $initLockFile = __DIR__ . '/../storage/.db_init';
        if (!file_exists($initLockFile)) {
            initialize_database($pdo, $driver);
            @touch($initLockFile);
        }
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
                click_limit INTEGER DEFAULT NULL,
                og_title TEXT DEFAULT NULL,
                og_description TEXT DEFAULT NULL,
                og_image TEXT DEFAULT NULL
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
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_title TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_description TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_image TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }

        // System Logs (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS system_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username TEXT,
                action TEXT NOT NULL,
                details TEXT,
                ip_address TEXT,
                created_at TEXT NOT NULL
            )'
        );

        // Collections (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collections (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                slug TEXT NOT NULL UNIQUE,
                title TEXT,
                description TEXT,
                theme_color TEXT DEFAULT "#ffffff",
                active INTEGER NOT NULL DEFAULT 1,
                created_at TEXT NOT NULL
            )'
        );

        // Collection Links (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collection_links (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_id INTEGER NOT NULL,
                link_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
                FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
            )'
        );

        // Collection Notes (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collection_notes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                collection_id INTEGER NOT NULL,
                note_id INTEGER NOT NULL,
                sort_order INTEGER NOT NULL DEFAULT 0,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            )'
        );

        // Rate Limits (SQLite)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ip_address TEXT NOT NULL,
                action TEXT NOT NULL,
                request_count INTEGER NOT NULL DEFAULT 1,
                reset_at TEXT NOT NULL
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_rate_limits_ip_action ON rate_limits (ip_address, action)');
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
                click_limit INT UNSIGNED DEFAULT NULL,
                og_title TEXT DEFAULT NULL,
                og_description TEXT DEFAULT NULL,
                og_image TEXT DEFAULT NULL
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
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_title TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_description TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE links ADD COLUMN og_image TEXT DEFAULT NULL');
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
                password_hash VARCHAR(255) DEFAULT NULL,
                is_burn_after_read TINYINT(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );
        // Migration for notes
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN password_hash VARCHAR(255) DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN is_burn_after_read TINYINT(1) NOT NULL DEFAULT 0');
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
                country_code VARCHAR(2) DEFAULT NULL,
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
                country_code VARCHAR(2) DEFAULT NULL,
                created_at DATETIME NOT NULL,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // System Logs (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS system_logs (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) DEFAULT NULL,
                action VARCHAR(50) NOT NULL,
                details TEXT DEFAULT NULL,
                ip_address VARCHAR(45) DEFAULT NULL,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Collections (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collections (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(191) NOT NULL UNIQUE,
                title TEXT DEFAULT NULL,
                description TEXT DEFAULT NULL,
                theme_color VARCHAR(7) DEFAULT "#ffffff",
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Collection Links (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collection_links (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                collection_id INT UNSIGNED NOT NULL,
                link_id INT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
                FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Collection Notes (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS collection_notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                collection_id INT UNSIGNED NOT NULL,
                note_id INT UNSIGNED NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Rate Limits (MySQL)
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS rate_limits (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                action VARCHAR(50) NOT NULL,
                request_count INT UNSIGNED NOT NULL DEFAULT 1,
                reset_at DATETIME NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
        );

        // Migration for stats (MySQL)
        try {
            $pdo->exec('ALTER TABLE link_stats ADD COLUMN country_code VARCHAR(2) DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE note_stats ADD COLUMN country_code VARCHAR(2) DEFAULT NULL');
        } catch (\Exception $e) {
        }
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
                password_hash TEXT DEFAULT NULL,
                is_burn_after_read INTEGER NOT NULL DEFAULT 0
            )'
        );
        // Migration for notes
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN password_hash TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE notes ADD COLUMN is_burn_after_read INTEGER NOT NULL DEFAULT 0');
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
                country_code TEXT,
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
                country_code TEXT,
                created_at TEXT NOT NULL,
                FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
            )'
        );

        // Migration for stats (SQLite)
        try {
            $pdo->exec('ALTER TABLE link_stats ADD COLUMN country_code TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
        try {
            $pdo->exec('ALTER TABLE note_stats ADD COLUMN country_code TEXT DEFAULT NULL');
        } catch (\Exception $e) {
        }
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
        'INSERT INTO links (slug, target_url, title, redirect_type, click_count, created_at, active, password_hash, expires_at, click_limit, og_title, og_description, og_image)
         VALUES (:slug, :target_url, :title, :redirect_type, 0, :created_at, :active, :password_hash, :expires_at, :click_limit, :og_title, :og_description, :og_image)'
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
        ':og_title' => $data['og_title'] ?? null,
        ':og_description' => $data['og_description'] ?? null,
        ':og_image' => $data['og_image'] ?? null,
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
        'UPDATE links SET slug = :slug, target_url = :target_url, title = :title, redirect_type = :redirect_type, active = :active, password_hash = :password_hash, expires_at = :expires_at, click_limit = :click_limit, og_title = :og_title, og_description = :og_description, og_image = :og_image
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
        ':og_title' => $data['og_title'] ?? null,
        ':og_description' => $data['og_description'] ?? null,
        ':og_image' => $data['og_image'] ?? null,
        ':id' => $id,
    ]);
}

/**
 * Linkin durumunu (aktif/pasif) değiştirir.
 * @param int $id
 * @param int $status
 * @return void
 */
function toggle_link_status(int $id, int $status): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare('UPDATE links SET active = ? WHERE id = ?');
    $stmt->execute([$status, $id]);
}

/**
 * ID ile link kaydını siler ve ilişkili istatistikleri temizler.
 * @param int $id
 * @return void
 */
function delete_link(int $id): void
{
    $pdo = get_db();
    // Manuel cascade: Önce istatistikleri sil
    $stmtStats = $pdo->prepare('DELETE FROM link_stats WHERE link_id = ?');
    $stmtStats->execute([$id]);

    // Sonra linki sil
    $stmt = $pdo->prepare('DELETE FROM links WHERE id = ?');
    $stmt->execute([$id]);
}

/**
 * Tüm linkleri getirir. Opsiyonel arama parametresi ile slug veya URL'de filtreleme yapar.
 * Sayfalama için limit ve offset eklendi.
 * 
 * @param string|null $search
 * @param int $limit
 * @param int $offset
 * @return array
 */
function get_all_links(?string $search = null, int $limit = 20, int $offset = 0): array
{
    $pdo = get_db();
    $sql = 'SELECT * FROM links';
    $params = [];

    if ($search !== null && trim($search) !== '') {
        $sql .= ' WHERE slug LIKE ? OR target_url LIKE ?';
        $like = '%' . trim($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= ' ORDER BY id DESC LIMIT ? OFFSET ?';
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Toplam link sayısını getirir (Arama filtresi ile uyumlu).
 * 
 * @param string|null $search
 * @return int
 */
function get_links_count(?string $search = null): int
{
    $pdo = get_db();
    $sql = 'SELECT COUNT(*) as count FROM links';
    $params = [];

    if ($search !== null && trim($search) !== '') {
        $sql .= ' WHERE slug LIKE ? OR target_url LIKE ?';
        $like = '%' . trim($search) . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
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
        'INSERT INTO notes (slug, content, title, view_count, created_at, active, password_hash, is_burn_after_read)
         VALUES (:slug, :content, :title, 0, :created_at, :active, :password_hash, :is_burn_after_read)'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':content' => $data['content'],
        ':title' => $data['title'] ?? null,
        ':created_at' => $data['created_at'],
        ':active' => $data['active'] ?? 1,
        ':password_hash' => $data['password_hash'] ?? null,
        ':is_burn_after_read' => $data['is_burn_after_read'] ?? 0,
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * ID ile not kaydını siler ve ilişkili istatistikleri temizler.
 * @param int $id
 * @return void
 */
function delete_note(int $id): void
{
    $pdo = get_db();
    // Manuel cascade: Önce istatistikleri sil
    $stmtStats = $pdo->prepare('DELETE FROM note_stats WHERE note_id = ?');
    $stmtStats->execute([$id]);

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
        'UPDATE notes SET slug = :slug, content = :content, title = :title, active = :active, password_hash = :password_hash, is_burn_after_read = :is_burn_after_read
         WHERE id = :id'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':content' => $data['content'],
        ':title' => $data['title'] ?? null,
        ':active' => $data['active'],
        ':password_hash' => $data['password_hash'] ?? null,
        ':is_burn_after_read' => $data['is_burn_after_read'] ?? 0,
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Country code lookup
    $countryCode = \App\get_ip_country($ip);

    $stmt = $pdo->prepare(
        'INSERT INTO link_stats (link_id, browser, os, device_type, referer, country_code, created_at)
         VALUES (:link_id, :browser, :os, :device_type, :referer, :country, :created_at)'
    );
    $stmt->execute([
        ':link_id' => $linkId,
        ':browser' => $browser,
        ':os' => $os,
        ':device_type' => $deviceType,
        ':referer' => $referer,
        ':country' => $countryCode,
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
/**
 * Tarayıcı, İşletim Sistemi gibi dağılımları getirir.
 * @param string $type browser|os|device_type
 * @param int|null $linkId
 * @param int $limit
 * @return array
 */
function get_distribution_stats(string $type, ?int $linkId = null, int $limit = 10): array
{
    $validTypes = ['browser', 'os', 'device_type', 'referer', 'country_code'];
    if (!in_array($type, $validTypes))
        return []; // Basit güvenlik önlemi

    $pdo = get_db();

    $whereSql = "";
    if ($linkId !== null) {
        $whereSql = "WHERE link_id = " . (int) $linkId;
    }

    $limitSql = ($limit > 0) ? "LIMIT " . (int) $limit : "";

    $stmt = $pdo->query("SELECT $type as name, COUNT(*) as count FROM link_stats $whereSql GROUP BY $type ORDER BY count DESC $limitSql");
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    // Country code lookup
    $countryCode = \App\get_ip_country($ip);

    $stmt = $pdo->prepare(
        'INSERT INTO note_stats (note_id, browser, os, device_type, referer, country_code, created_at)
         VALUES (:note_id, :browser, :os, :device_type, :referer, :country, :created_at)'
    );
    $stmt->execute([
        ':note_id' => $noteId,
        ':browser' => $browser,
        ':os' => $os,
        ':device_type' => $deviceType,
        ':referer' => $referer,
        ':country' => $countryCode,
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
/**
 * Notlar için dağılım istatistikleri.
 * @param string $type
 * @param int|null $noteId
 * @param int $limit
 * @return array
 */
function get_note_distribution_stats(string $type, ?int $noteId = null, int $limit = 10): array
{
    $validTypes = ['browser', 'os', 'device_type', 'referer', 'country_code'];
    if (!in_array($type, $validTypes))
        return [];

    $pdo = get_db();

    $whereSql = "";
    if ($noteId !== null) {
        $whereSql = "WHERE note_id = " . (int) $noteId;
    }

    $limitSql = ($limit > 0) ? "LIMIT " . (int) $limit : "";

    $stmt = $pdo->query("SELECT $type as name, COUNT(*) as count FROM note_stats $whereSql GROUP BY $type ORDER BY count DESC $limitSql");
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

// -----------------------------------------------------------------------------
// COLLECTION FUNCTIONS
// -----------------------------------------------------------------------------

/**
 * Yeni koleksiyon oluşturur.
 */
function insert_collection(array $data): int
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'INSERT INTO collections (slug, title, description, theme_color, active, created_at)
         VALUES (:slug, :title, :description, :theme_color, :active, :created_at)'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':title' => $data['title'] ?? null,
        ':description' => $data['description'] ?? null,
        ':theme_color' => $data['theme_color'] ?? '#ffffff',
        ':active' => $data['active'] ?? 1,
        ':created_at' => date('Y-m-d H:i:s'),
    ]);
    return (int) $pdo->lastInsertId();
}

/**
 * Koleksiyonu getirir (slug ile).
 */
function get_collection_by_slug(string $slug): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Koleksiyonu getirir (ID ile).
 */
function get_collection_by_id(int $id): ?array
{
    $pdo = get_db();
    $stmt = $pdo->prepare('SELECT * FROM collections WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Tüm koleksiyonları listeler.
 */
function get_all_collections(): array
{
    $pdo = get_db();
    return $pdo->query('SELECT * FROM collections ORDER BY id DESC')->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Koleksiyonu günceller.
 */
function update_collection(int $id, array $data): void
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'UPDATE collections SET slug = :slug, title = :title, description = :description, theme_color = :theme_color, active = :active 
         WHERE id = :id'
    );
    $stmt->execute([
        ':slug' => $data['slug'],
        ':title' => $data['title'],
        ':description' => $data['description'],
        ':theme_color' => $data['theme_color'],
        ':active' => $data['active'],
        ':id' => $id
    ]);
}

/**
 * Koleksiyonu siler.
 */
function delete_collection(int $id): void
{
    $pdo = get_db();
    // Cascade delete handle by DB but explicit just in case
    $pdo->prepare('DELETE FROM collection_links WHERE collection_id = ?')->execute([$id]);
    $pdo->prepare('DELETE FROM collections WHERE id = ?')->execute([$id]);
}

/**
 * Koleksiyona link ekler.
 */
function add_link_to_collection(int $collectionId, int $linkId): void
{
    $pdo = get_db();
    // Check if exists
    $stmt = $pdo->prepare('SELECT id FROM collection_links WHERE collection_id = ? AND link_id = ?');
    $stmt->execute([$collectionId, $linkId]);
    if ($stmt->fetch())
        return; // Already exists

    $pdo->prepare('INSERT INTO collection_links (collection_id, link_id) VALUES (?, ?)')
        ->execute([$collectionId, $linkId]);
}

/**
 * Koleksiyondan link çıkarır.
 */
function remove_link_from_collection(int $collectionId, int $linkId): void
{
    $pdo = get_db();
    $pdo->prepare('DELETE FROM collection_links WHERE collection_id = ? AND link_id = ?')
        ->execute([$collectionId, $linkId]);
}

/**
 * Koleksiyondaki linkleri getirir.
 */
function get_collection_links(int $collectionId): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT l.*, cl.sort_order 
         FROM links l 
         JOIN collection_links cl ON l.id = cl.link_id 
         WHERE cl.collection_id = ? 
         ORDER BY cl.sort_order ASC, cl.id ASC'
    );
    $stmt->execute([$collectionId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Koleksiyondaki notları getirir.
 * @param int $collectionId
 * @return array
 */
function get_collection_notes(int $collectionId): array
{
    $pdo = get_db();
    $stmt = $pdo->prepare(
        'SELECT n.id, n.slug, n.title, n.content, n.active, cn.sort_order, cn.id as junction_id
         FROM notes n 
         JOIN collection_notes cn ON n.id = cn.note_id 
         WHERE cn.collection_id = ? 
         ORDER BY cn.sort_order ASC, cn.id ASC'
    );
    $stmt->execute([$collectionId]);
    return $stmt->fetchAll(\PDO::FETCH_ASSOC);
}

/**
 * Koleksiyona not ekler.
 * @param int $collectionId
 * @param int $noteId
 * @return void
 */
function add_note_to_collection(int $collectionId, int $noteId): void
{
    $pdo = get_db();
    // Check if exists
    $stmt = $pdo->prepare('SELECT id FROM collection_notes WHERE collection_id = ? AND note_id = ?');
    $stmt->execute([$collectionId, $noteId]);
    if ($stmt->fetch())
        return; // Already exists

    $pdo->prepare('INSERT INTO collection_notes (collection_id, note_id) VALUES (?, ?)')
        ->execute([$collectionId, $noteId]);
}

/**
 * Koleksiyondan not çıkarır.
 * @param int $collectionId
 * @param int $noteId
 * @return void
 */
function remove_note_from_collection(int $collectionId, int $noteId): void
{
    $pdo = get_db();
    $pdo->prepare('DELETE FROM collection_notes WHERE collection_id = ? AND note_id = ?')
        ->execute([$collectionId, $noteId]);
}