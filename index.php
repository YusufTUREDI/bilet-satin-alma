<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/nav.php';

$u = $_SESSION['user'] ?? null;
$isAdmin = ($u['role'] ?? '') === 'admin';
$showStatus = ($isAdmin && defined('APP_ENV') && APP_ENV === 'dev');

$hc = $showStatus ? db_healthcheck() : null;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>Yusuf TÜREDİ Bilet Uygulaması</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
    <?= nav_html(); ?>
    <main class="container" style="padding:24px 0">

        <section class="card" style="text-align: center;">
            <h1>Yusuf TÜREDİ Bilet Uygulamasına Hoş Geldiniz!</h1>
            <p>Güvenli ve hızlı otobüs bileti almanın en kolay yolu.</p>
            <div class="form-actions" style="justify-content: center; margin-top: 20px;">
                <a href="<?= e(BASE_URL) ?>/pages/bilet_al.php" class="btn primary">Hemen Bilet Ara</a>
                <?php if (!$u): ?>
                    <a href="<?= e(BASE_URL) ?>/pages/login.php" class="btn outline">Giriş Yap</a>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($showStatus && $hc): ?>
            <section class="card" style="margin-top: 2rem;">
                <h2>Sistem Durumu (Sadece Geliştirici Görür)</h2>
                <ul>
                    <li>SQLite Sürümü: <b><?= e($hc['sqlite_version'] ?? 'Bilinmiyor') ?></b></li>
                    <li>Veritabanı Dosyası: <code><?= e($hc['db_file'] ?? 'Bilinmiyor') ?></code></li>
                    <li>Durum: <b style="color: <?= $hc['ok'] ? 'green' : 'red' ?>;"><?= e($hc['status'] ?? 'Bilinmiyor') ?></b></li>
                </ul>
            </section>
        <?php endif; ?>
    </main>

    <footer class="container" style="text-align: center; margin-top: 2rem; padding-bottom: 2rem;">
        <small>© <?= date('Y') ?> YUSUF TÜREDİ Otobüs Bilet Uygulaması</small>
    </footer>
    <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
