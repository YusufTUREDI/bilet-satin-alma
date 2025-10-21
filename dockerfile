# Temel PHP 8.2 Apache imajını kullan
FROM php:8.2-apache

# Çalışma dizinini konteyner içinde /var/www/html olarak ayarla
WORKDIR /var/www/html

# Gerekli sistem paketlerini kuruyoruz.
# libsqlite3-dev: pdo_sqlite eklentisi için şart.
# Diğerleri: gd, zip, intl eklentileri ve composer için gerekli.
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    zip \
    unzip \
  # Kurulumdan sonra APT önbelleğini temizleyerek imaj boyutunu küçültüyoruz
  && rm -rf /var/lib/apt/lists/*

# Gerekli PHP eklentilerini kuruyoruz.
# gd eklentisini freetype ve jpeg desteğiyle yapılandırıyoruz.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  # gd, pdo, pdo_sqlite, zip ve intl eklentilerini kuruyoruz.
  # -j$(nproc): Kurulumu hızlandırmak için işlemci çekirdeği kadar paralel iş kullanır.
  && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite zip intl

# Apache'nin mod_rewrite modülünü etkinleştiriyoruz (.htaccess yönlendirmeleri için).
RUN a2enmod rewrite

# Composer'ı (PHP paket yöneticisi) global olarak kuruyoruz.
# Başka bir imajdan (composer:latest) sadece composer executable'ını kopyalıyoruz (multi-stage build).
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Önce sadece composer dosyalarını kopyalıyoruz (Docker katman önbelleğinden yararlanmak için).
COPY composer.json composer.lock ./

# Composer bağımlılıklarını kuruyoruz (Dompdf vb.).
# --no-interaction: Kurulum sırasında soru sorma.
# --no-dev: Sadece production bağımlılıklarını kur.
# --optimize-autoloader: Daha hızlı sınıf yükleme için optimize et.
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Projenin Geri Kalan Tüm Dosyalarını kopyalıyoruz.
# NOT: Bu komut composer install'dan SONRA olmalı.
COPY . /var/www/html/

# /var/www/html/var dizininin sahibini Apache'nin çalıştığı www-data kullanıcısı yapıyoruz.
# Bu, SQLite dosyasının yazılabilir olması için kritik.
RUN chown -R www-data:www-data /var/www/html/var

# Konteyner çalıştığında Apache sunucusunu ön planda başlatacak komutu belirtiyoruz.
CMD ["apache2-foreground"]