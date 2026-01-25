# URL Shortener

Basit, güvenli ve hızlı URL kısaltma servisi. PHP ile yazılmış, framework gerektirmeyen hafif bir proje.

## Özellikler

- ✅ URL kısaltma (otomatik veya özel slug)
- ✅ Not oluşturma (paylaşılabilir metinler)
- ✅ 301/302 yönlendirme desteği
- ✅ Gelişmiş Analitik (Tarayıcı, İşletim Sistemi, Cihaz, Ülke takibi ve Chart.js grafikleri)
- ✅ QR Kod desteği (Her link ve not için otomatik oluşturma)
- ✅ Şifre Korumalı İçerik (Link ve notlar için)
- ✅ Süreli Linkler & Tıklama Limiti
- ✅ Veritabanı Yedekleme (Admin panelinden SQL indirme)
- ✅ Admin paneli (Karanlık Mod desteği ile)
- ✅ Toplu İşlemler (Silme, Aktif/Pasif yapma - Hızlı Yönetim)
- ✅ Toplu İçe/Dışa Aktarma (CSV üzerinden link yükleme ve yedekleme)
- ✅ Link Koleksiyonları (Bio Pages - Linktree tarzı paylaşım sayfaları)
- ✅ CSRF ve Brute-force koruması
- ✅ MySQL ve SQLite desteği
- ✅ InfinityFree uyumlu (shared hosting)
- ✅ Modern JavaScript Entegrasyonu (Onay kutusuz, hızlı işlemler)

## Kurulum

### 1. Dosyaları Yükleyin

Tüm dosyaları hosting klasörünüze (örn. `htdocs` veya `public_html`) yükleyin.

### 2. Konfigürasyon

`app/config.example.php` dosyasını `app/config.php` olarak kopyalayın ve düzenleyin:

```php
<?php
return [
    'db' => [
        'driver' => 'mysql',
        'host' => 'sql100.infinityfree.com',
        'dbname' => 'if0_XXXXXXX_short',
        'username' => 'if0_XXXXXXX',
        'password' => 'PAROLA',
        'charset' => 'utf8mb4',
    ],
    'admin' => [
        'username' => 'admin',
        'password_hash' => 'HASH', // php -r "echo password_hash('parola', PASSWORD_DEFAULT);"
    ],
    'redirect_default' => 302,
    'base_url' => 'https://sizin-domain.com',
];
```

### 3. Parola Hash Oluşturma

Admin parolası için hash oluşturun:

```bash
php -r "echo password_hash('sizin_parolaniz', PASSWORD_DEFAULT);"
```

Çıktıyı `config.php` dosyasındaki `password_hash` alanına yapıştırın.

### 4. Veritabanı

Veritabanı tablosu otomatik olarak oluşturulur. Manuel oluşturmak isterseniz:

```sql
CREATE TABLE links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(191) NOT NULL UNIQUE,
    target_url TEXT NOT NULL,
    title TEXT DEFAULT NULL,
    redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 302,
    click_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## Kullanım

### Public Sayfa
Ana sayfa (`/`) üzerinden herkes URL kısaltabilir.

### Admin Paneli
`/admin/` adresinden giriş yaparak:
- Tüm linkleri görüntüleme
- Link düzenleme/silme
- Aktif/pasif yapma
- Arama ve sayfalama

## Dosya Yapısı

```
├── index.php           # Ana router + public form
├── 404.php             # 404 sayfası
├── .htaccess           # Apache routing
├── admin/
│   ├── index.php       # Admin dashboard
│   ├── login.php       # Giriş sayfası
│   ├── new.php         # Yeni link ekleme
│   ├── edit.php        # Link düzenleme
│   ├── bulk.php        # Toplu içe/dışa aktarma
│   ├── collections.php # Koleksiyon listesi
│   ├── collection_edit.php # Koleksiyon düzenleme
│   └── logout.php      # Çıkış
├── app/
│   ├── config.php      # Konfigürasyon (gitignore)
│   ├── config.example.php
│   ├── db.php          # Veritabanı fonksiyonları
│   ├── auth.php        # Kimlik doğrulama
│   ├── csrf.php        # CSRF koruması
│   ├── functions.php   # Yardımcı fonksiyonlar
│   └── security.php    # Güvenlik başlıkları
└── storage/
    ├── .htaccess       # Erişim engeli
    └── login_attempts.json
```

## Güvenlik

- ✅ PDO prepared statements (SQL injection koruması)
- ✅ XSS koruması (htmlspecialchars)
- ✅ CSRF token doğrulama
- ✅ Brute-force koruması (5 deneme / 15 dakika)
- ✅ Session güvenliği (HTTPOnly, SameSite, secure)
- ✅ Güvenlik başlıkları
- ✅ Rezerve slug koruması

## Gereksinimler

- PHP 7.4+
- MySQL 5.7+ veya SQLite 3
- Apache (mod_rewrite)

## Lisans

MIT License
