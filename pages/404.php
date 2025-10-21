<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/nav.php';

http_response_code(404);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sayfa Bulunamadı</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:640px">
      <h1>Sayfa bulunamadı</h1>
      <p>İstediğiniz sayfa mevcut değil veya taşınmış olabilir.</p>
      <p><a class="btn" href="<?= e(BASE_URL) ?>/">Ana sayfaya dön</a></p>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
