# Proje Durumu ve Yapılacaklar (Task List)

## ✅ Tamamlananlar (Son Güncelleme)
- [x] **Ülke Analitiği:** GeoIP (IP-API) entegrasyonu ile tıklamaların hangi ülkeden geldiği takip ediliyor.
- [x] **Veritabanı Yedekleme:** Admin panelinden tek tıkla `.sql` formatında yedek alma sistemi eklendi.
- [x] **F5 (Çift Kayıt) Hatası:** PRG (Post-Redirect-Get) deseni uygulanarak sayfa yenilemede oluşan mükerrer kayıtlar engellendi.
- [x] **Silme İşlemi Düzeltildi:** Foreign Key kısıtlamaları halledildi ve ilişkili veriler temizleniyor.
- [x] **Toplu İşlemler:** Çoklu silme, aktif/pasif yapma özellikleri eklendi.
- [x] **API Desteği:** `api.php` üzerinden JSON formatında veri çıkışı sağlandı.
- [x] **Gelişmiş Grafikler:** Referer (yönlendiren site), tarayıcı, OS ve cihaz bazlı Chart.js grafikleri.
- [x] **Güvenlik İyileştirmeleri:** CSRF koruması, XSS önleme ve brute-force koruması.
- [x] **InfinityFree Uyumluluğu:** CURL bağımlılığı kaldırıldı, `file_get_contents` fallback mekanizması eklendi.

## 📋 Yol Haritası (Gelecek Planlar)

### 🎨 Kullanıcı Deneyimi (UX)
- [x] **QR Kod Özelleştirme:** Renk, logo ve boyut seçenekleri.
- [x] **Markdown Editör:** Notlar için zengin metin desteği.
- [x] ~~**Open Graph Önizleme:**~~ (Iptal edildi).

### 📊 Gelişmiş Analiz
- [x] **Etkileşim Haritası:** Ülke istatistiklerinin dünya haritası üzerinde gösterimi.
- [x] ~~**E-posta Raporları:**~~ (İptal edildi).

### 🛠 Araçlar & Diğer
- [x] **Toplu İçe/Dışa Aktarma:** Excel veya CSV dosyasından toplu link oluşturma ve yedekleme.
- [x] **Link Koleksiyonları:** Birden fazla linki tek bir sayfada toplama (Linktree tarzı).
- [ ] **Kendini İmha Eden Notlar:** Görüldükten sonra otomatik silinen içerikler.

### 🧹 Bakım & Güvenlik
- [ ] Spam/Zararlı Link Koruması (Google Safe Browsing Entegrasyonu).
- [ ] Rate Limiting (Kullanıcı bazlı tıklama/oluşturma sınırı).

## 🛠️ Mevcut Teknik Durum
- **Dil:** PHP 7.4+ (Framework-suz, Saf PHP)
- **Veritabanı:** MySQL veya SQLite desteği.
- **Frontend:** Vanilla CSS & JS (Karanlık Mod destekli).
- **Sunucu:** Apache & Nginx uyumlu (.htaccess dahil).
