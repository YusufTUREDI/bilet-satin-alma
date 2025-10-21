<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/nav.php';

$health = db_healthcheck();

?>
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Veritabanı Durumu</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
    <style>
        .status-ok { color: green; font-weight: bold; }
        .status-error { color: red; font-weight: bold; }
        pre { background-color: #f0f0f0; padding: 10px; border-radius: 5px; white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
    <?= nav_html(); ?>
    <main class="container" style="padding:28px 0">
        <section class="card">
            <h1>Veritabanı Durumu</h1>

            <div class="alert alert-info">
                Veritabanı kurulumu ve tablo oluşturma işlemleri artık <strong>otomatik olarak</strong> uygulama ilk çalıştığında yapılmaktadır. Bu sayfayı manuel olarak çalıştırmanıza gerek yoktur.
            </div>

            <h2>Sağlık Kontrolü Sonucu:</h2>
            <p>Mevcut veritabanı bağlantı ve tablo durumu aşağıdadır:</p>
            <pre><?= e(print_r($health, true)); ?></pre>

            <?php if (!$health['ok']): ?>
                <div class="alert alert-error">
                    <strong>Hata:</strong> Veritabanı bağlantısında veya kurulumunda bir sorun var gibi görünüyor. Lütfen aşağıdaki olası nedenleri kontrol edin:
                    <ul>
                        <li>`var` klasörünün web sunucusu (Apache/PHP kullanıcısı) tarafından yazılabilir olduğundan emin olun.</li>
                        <li>PHP `pdo_sqlite` eklentisinin etkin olduğundan emin olun.</li>
                        <li>Apache/PHP hata log dosyalarını (`<?= e(ini_get('error_log') ?: 'Sunucu yapılandırmasında belirtilen dosya') ?>`) kontrol edin.</li>
                        <?php if (!empty($health['error'])): ?>
                            <li>Tespit edilen hata mesajı: <?= e($health['error']) ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php else: ?>
                 <div class="alert alert-success">
                     Durum: <span class="status-ok">Başarılı.</span> <?= e($health['status']) ?>
                 </div>
            <?php endif; ?>

             <p style="margin-top: 20px;">
                 <a class="btn" href="<?= e(BASE_URL) ?>/">Ana Sayfaya Dön</a>
             </p>
        </section>
    </main>
     <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>