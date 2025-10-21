<?php
require_once __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/nav.php';

$u = $_SESSION['user'] ?? null;
$isAdmin = ($u['role'] ?? '') === 'admin';
$showStatus = ($isAdmin && APP_ENV === 'dev');

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
    <header class="site-header" style="margin-bottom:12px">
      <div class="container bar">
        <a class="brand" href="<?= e(BASE_URL) ?>/">YUSUF TÜREDİ Bilet Uygulaması</a>
      </div>
    </header>

    <?php if ($showStatus): ?>
      <section class="card">
        <h1>Sistem durumu</h1>
        <ul>
          <li>SQLite: <b><?= e($hc['sqlite_version']) ?></b></li>
          <li>Günlükleme: <b><?= e($hc['wal_mode']) ?></b></li>
          <li>Veritabanı: <code><?= e($hc['db_file']) ?></code></li>
        </ul>
      </section>
    <?php endif; ?>
  </main>

  <footer class="container footer">
    <small>© <?= date('Y') ?> YUSUF TÜREDİ Otobüs Bilet Uygulaması</small>
  </footer>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
