<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

if (isset($_SESSION['user'])) { header('Location: ' . BASE_URL . '/'); exit; }

$errors = [];
$flash  = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $name      = trim((string)($_POST['name'] ?? ''));
    $email     = strtolower(trim((string)($_POST['email'] ?? '')));
    $password  = (string)($_POST['password']  ?? '');
    $password2 = (string)($_POST['password2'] ?? '');

    if ($name === '' || mb_strlen($name) > 120)                                 $errors[] = 'Ad Soyad zorunlu ve 120 karakterden kısa olmalı.';
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200) $errors[] = 'Geçerli bir e-posta girin.';
    if ($password === '' || mb_strlen($password) < 8)                            $errors[] = 'Şifre en az 8 karakter olmalı.';
    if ($password !== $password2)                                                $errors[] = 'Şifreler eşleşmiyor.';

    if (!$errors) {
        $pdo = db();
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
        $st->execute([$email]);
        if ($st->fetchColumn()) { $errors[] = 'Bu e-posta zaten kullanılıyor.'; }
    }

    if (!$errors) {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);

            $st = $pdo->prepare("
                INSERT INTO users (firm_id, name, email, password_hash, role, credit_cents, is_active)
                VALUES (NULL, ?, ?, ?, 'user', 100000, 1)
            ");
            $st->execute([$name, $email, $hash]);

            $uid = (int)$pdo->lastInsertId();

            $st2 = $pdo->prepare("
                INSERT INTO wallet_tx (user_id, type, amount_cents, note)
                VALUES (?, 'topup', 100000, 'Hoş geldin bakiyesi (+1000 TL)')
            ");
            $st2->execute([$uid]);

            $pdo->commit();
            $_SESSION['flash'][] = ['t'=>'success','m'=>'Kayıt başarılı. Giriş yapabilirsiniz. (Cüzdana 1000 TL yüklendi)'];
            header('Location: ' . BASE_URL . '/pages/login.php');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = 'Kayıt sırasında bir hata oluştu.';
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kayıt Ol</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:520px;margin:0 auto">
      <h1>Kayıt Ol</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <ul style="margin:0 0 0 18px"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" class="form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <label>Ad Soyad
          <input name="name" required maxlength="120" value="<?= e($_POST['name'] ?? '') ?>">
        </label>
        <label>E-posta
          <input type="email" name="email" required maxlength="200" value="<?= e($_POST['email'] ?? '') ?>">
        </label>
        <label>Şifre (en az 8 karakter)
          <input type="password" name="password" required>
        </label>
        <label>Şifre (Tekrar)
          <input type="password" name="password2" required>
        </label>
        <div class="form-actions">
          <button type="submit">Kayıt Ol</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/login.php">Giriş Yap</a>
        </div>
      </form>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
