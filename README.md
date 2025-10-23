# Bilet Satış Otomasyonu

Bu proje, PHP (frameworksüz) ve SQLite veritabanı kullanılarak geliştirilmiş basit bir otobüs bileti satış sistemidir. Sistem, kullanıcıların sefer aramasına, bilet almasına, firma yöneticilerinin sefer ve kupon yönetmesine, adminin ise tüm sistemi yönetmesine olanak tanır.
## Özellikler

* **Kullanıcılar:**
    * Kayıt olma (1000 TL hoş geldin bakiyesi ile)
    * Giriş yapma (Brute-force korumalı)
    * Sefer arama (Kalkış/Varış şehri, tarih)
    * Koltuk seçerek bilet satın alma (Cüzdan bakiyesi ile)
    * İndirim kuponu kullanma
    * Satın alınan biletleri listeleme
    * PDF bilet indirme
    * Bilet iptali (Sefer saatinden 1 saat öncesine kadar, ücret iadeli)
    * Profil (Ad/Soyad, Şifre) güncelleme
* **Firma Yöneticileri (`firm_admin`):**
    * Kendi firmasına ait seferleri ekleme, listeleme, düzenleme
    * Kendi firmasına ait kuponları oluşturma, yönetme (aktif/pasif, silme)
* **Site Yöneticisi (`admin`):**
    * Yeni firmalar ekleme, firmaları aktifleştirme/pasifleştirme
    * Kullanıcıları firma yöneticisi olarak atama/geri alma
    * Tüm kullanıcıları listeleme
    * Global (tüm firmalar için) veya firmaya özel kuponları yönetme

## Varsayılan Giriş Bilgileri
* SİSTEM ADMİNİ
* **E-posta:** `admin@gmail.com`
* **Şifre:** `123456789` 
*SİSTEMDE YÜKLÜ 2 ADET FİRMA ADMİNİ VAR
* `muhammedtemli@gmail.com` şifre:`123456789`
* `mehmetgursoy@gmail.com`  şifre:`123456789`


## Kullanılan Teknolojiler

* PHP 8.2 (Frameworksüz)
* Apache Web Sunucusu
* SQLite 3 (Veritabanı)
* Docker & Docker Compose
* HTML5 & CSS3
* JavaScript (Vanilla, minimal)
* [Dompdf](https://github.com/dompdf/dompdf) (PDF bilet oluşturma için - Composer ile yüklenir)
