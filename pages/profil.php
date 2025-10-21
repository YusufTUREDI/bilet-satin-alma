<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require_login();
require dirname(__DIR__) . '/inc/nav.php';

$pdo = db();
$kullanici = current_user();

$hatalar = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');

    $adsoyad   = trim($_POST['name']       ?? '');
    $mevcut    = (string)($_POST['curpass'] ?? '');
    $yeni1     = (string)($_POST['newpass'] ?? '');
    $yeni2     = (string)($_POST['newpass2']?? '');

    if ($adsoyad === '' || mb_strlen($adsoyad) < 2 || mb_strlen($adsoyad) > 80) {
        $hatalar[] = 'Ad soyad 2-80 karakter olmalı.';
    }

    $sifre_degistiriliyor = ($yeni1 !== '' || $yeni2 !== '' || $mevcut !== '');

    if ($sifre_degistiriliyor) {
        if ($mevcut === '' || $yeni1 === '' || $yeni2 === '') {
            $hatalar[] = 'Şifre değişikliği için mevcut ve yeni şifre alanlarını doldurun.';
        } else {
            $st = $pdo->prepare("SELECT password_hash FROM users WHERE id=? LIMIT 1");
            $st->execute([(int)$kullanici['id']]);
            $row = $st->fetch();

            if (!$row || !password_verify($mevcut, (string)$row['password_hash'])) {
                $hatalar[] = 'Mevcut şifre yanlış.';
            }

            if ($yeni1 !== $yeni2) {
                $hatalar[] = 'Yeni şifreler aynı olmalı.';
            }
            if (mb_strlen($yeni1) < 8) {
                $hatalar[] = 'Yeni şifre en az 8 karakter olmalı.';
            }
        }
    }

    if (!$hatalar) {
        if ($sifre_degistiriliyor) {
            $hash = password_hash($yeni1, PASSWORD_DEFAULT);
            $st = $pdo->prepare("UPDATE users SET name=?, password_hash=? WHERE id=?");
            $st->execute([$adsoyad, $hash, (int)$kullanici['id']]);
        } else {
            $st = $pdo->prepare("UPDATE users SET name=? WHERE id=?");
            $st->execute([$adsoyad, (int)$kullanici['id']]);
        }

        $_SESSION['user']['name'] = $adsoyad;
        bildirim_ekle('success', 'Profil güncellendi.');
        header('Location: ' . (BASE_URL . '/pages/profil.php')); 
        exit;
    }
}

$adsoyad = $_POST['name'] ?? $kullanici['name'];
$eposta  = $kullanici['email'];
$flash   = bildirim_al();
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Profilim</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:560px">
      <h1>Profilim</h1>

      <?php if ($flash): ?>
        <?php foreach ($flash as $f): ?>
          <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if ($hatalar): ?>
        <div class="alert alert-error">
          <ul style="margin:0 0 0 18px">
            <?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div style="display:grid;gap:12px">
          <label>Ad Soyad
            <input name="name" value="<?= e($adsoyad) ?>" required maxlength="80">
          </label>
          <label>E-posta
            <input value="<?= e($eposta) ?>" disabled>
          </label>

          <hr>

          <label>Mevcut Şifre
            <input type="password" name="curpass" placeholder="Mevcut Şifre">
          </label>
          <label>Yeni Şifre
            <input type="password" name="newpass" placeholder="En az 8 karakter">
          </label>
          <label>Yeni Şifre (Tekrar)
            <input type="password" name="newpass2">
          </label>

          <div style="display:flex;gap:8px;align-items:center">
            <button type="submit">Kaydet</button>
            <a class="btn" href="<?= e(BASE_URL) ?>/">Ana sayfa</a>
          </div>
        </div>
      </form>
    </section>
  </main>

  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
