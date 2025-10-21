<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/nav.php';

$u = current_user();
$role = $u['role'] ?? null;


$isAdmin = ($role === 'admin');
$isDevMode = (defined('APP_ENV') && APP_ENV === 'dev');
$showStatus = ($isAdmin && $isDevMode);


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
    <?php  ?>
    <?= nav_html(); ?>

    <main class="container" style="padding:24px 0">
        <section class="card">
            <h1>Yusuf TÜREDİ Bilet Uygulamasına Hoş Geldiniz!</h1>
            <p>Güvenli ve hızlı otobüs bileti almanın en kolay yolu.</p>

            <?php
           
            if ($role === 'user' || $role === null):
            ?>
                <div class="form-actions" style="margin-top: 20px;">
                    <a href="<?= e(PAGES_URL) ?>/bilet_al.php" class="btn primary">Hemen Bilet Ara</a>
                </div>
            <?php endif; ?>
            <?php ?>

        </section>

        <?php  ?>
        <?php if ($showStatus && $hc): ?>
            <section class="card" style="margin-top: 20px;">
                <h2>Sistem Durumu (Sadece Geliştirici Görür)</h2>
                <ul>
                    <li>SQLite Sürümü: <b><?= e($hc['sqlite_version'] ?? 'N/A') ?></b></li>
                    <li>Veritabanı Dosyası: <code><?= e($hc['db_file'] ?? 'N/A') ?></code></li>
                    <li>Durum: <b><?= e($hc['status'] ?? 'N/A') ?></b></li>
                </ul>
            </section>
        <?php endif; ?>
    </main>

    <footer class="container" style="text-align: center; padding: 20px 0;">
        <small>&copy; <?= date('Y') ?> YUSUF TÜREDİ Otobüs Bilet Uygulaması</small>
    </footer>

    <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>