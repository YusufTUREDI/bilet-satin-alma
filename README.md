# Bilet Satış Otomasyonu

Bu proje, PHP (frameworksüz) ve SQLite veritabanı kullanılarak geliştirilmiş basit bir otobüs bileti satış sistemidir. Sistem, kullanıcıların sefer aramasına, bilet almasına, firma yöneticilerinin sefer ve kupon yönetmesine, adminin ise tüm sistemi yönetmesine olanak tanır. Proje, Docker kullanılarak kolayca çalıştırılabilir hale getirilmiştir.

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

## Kurulum ve Çalıştırma (Docker ile)

Bu projeyi yerel makinenizde çalıştırmak için aşağıdaki adımları takip edin:

1.  **Ön Koşullar:**
    * [Docker](https://www.docker.com/get-started)
    * [Docker Compose](https://docs.docker.com/compose/install/) (Genellikle Docker Desktop ile birlikte gelir)
    * [Git](https://git-scm.com/downloads)

2.  **Projeyi Klonlama:**
    Terminali açın ve aşağıdaki komutları çalıştırın:
    ```bash
    git clone [https://github.com/YusufTUREDI/bilet-satin-alma.git](https://github.com/YusufTUREDI/bilet-satin-alma.git)
    cd bilet-satin-alma
    ```

3.  **Docker Konteynerlarını Oluşturma ve Başlatma:**
    Proje dizinindeyken aşağıdaki komutu çalıştırın. Bu komut, gerekli Docker imajını oluşturacak (içinde PHP eklentileri ve Composer bağımlılıkları kurulacak) ve konteynerları arka planda başlatacaktır:
    ```bash
    docker-compose up -d --build
    ```
    *İlk kurulum biraz zaman alabilir.*

4.  **Windows İzinleri (Eğer Windows kullanıyorsanız):**
    * Eğer Docker konteyneri veritabanı dosyasına yazamazsa (500 hatası alırsanız), proje klasöründeki `var` klasörüne sağ tıklayıp **Özellikler > Güvenlik > Düzenle > Ekle** adımlarını izleyin. `Everyone` (veya `Herkes`) kullanıcısını ekleyip **Tam Denetim** izni verin ve **Uygula**'ya basın.

5.  **Veritabanı Kurulumu:**
    * Konteynerler başarıyla çalıştıktan sonra, web tarayıcınızı açın ve `http://localhost:8080/setup.php` adresine gidin.
    * "Veritabanı Kurulumunu Başlat" butonuna tıklayın. Bu işlem, `var/bilet.sqlite` dosyasını ve gerekli tabloları oluşturacak, ayrıca varsayılan admin kullanıcısını ekleyecektir.
    * Başarılı mesajını gördükten sonra devam edebilirsiniz.

6.  **Erişim:**
    * Uygulamaya ana sayfadan erişmek için tarayıcınızda `http://localhost:8080` adresine gidin.

## Varsayılan Giriş Bilgileri

Veritabanı kurulumu (`setup.php`) çalıştırıldıktan sonra aşağıdaki admin kullanıcısı ile giriş yapabilirsiniz:

* **E-posta:** `admin@gmail.com`
* **Şifre:** `password` *(Bu şifreyi `setup.php` dosyasında değiştirebilirsiniz)*

## Kullanılan Teknolojiler

* PHP 8.2 (Frameworksüz)
* Apache Web Sunucusu
* SQLite 3 (Veritabanı)
* Docker & Docker Compose
* HTML5 & CSS3
* JavaScript (Vanilla, minimal)
* [Dompdf](https://github.com/dompdf/dompdf) (PDF bilet oluşturma için - Composer ile yüklenir)