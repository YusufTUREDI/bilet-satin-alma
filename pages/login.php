<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

if (is_logged_in()) {
    redirect_back_or(BASE_URL . '/');
}

$errors = [];
$blocked_until = null;

if (!isset($_SESSION['login_fail'])) {
    $_SESSION['login_fail'] = ['count' => 0, 'first_at' => null, 'blocked_until' => null];
}
$lf =& $_SESSION['login_fail'];
$now = time();
if (!empty($lf['blocked_until']) && $now < (int)$lf['blocked_until']) {
    $blocked_until = (int)$lf['blocked_until'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $blocked_until === null) {
    csrf_check($_POST['csrf'] ?? '');
    $email = trim((string)($_POST['email'] ?? ''));
    $pass  = (string)($_POST['password'] ?? '');

    if ($email === '' || $pass === '') {
        $errors[] = 'E-posta ve şifre zorunludur.';
    } else {
        $pdo = db();
        $st = $pdo->prepare("
            SELECT id, firm_id, name, email, password_hash, role, is_active
            FROM users
            WHERE email=? LIMIT 1
        ");
        $st->execute([$email]);
        $u = $st->fetch();

        $ok = $u && (int)$u['is_active'] === 1 && password_verify($pass, (string)$u['password_hash']);

        if ($ok) {
            session_regenerate_id(true); 
            $_SESSION['user'] = [
                'id'      => (int)$u['id'],
                'name'    => (string)$u['name'],
                'email'   => (string)$u['email'],
                'role'    => (string)$u['role'],
                'firm_id' => is_null($u['firm_id']) ? null : (int)$u['firm_id'],
            ];
            $_SESSION['login_fail'] = ['count'=>0,'first_at'=>null,'blocked_until'=>null];

            bildirim_ekle('success', 'Hoş geldin, ' . (string)$u['name'] . '!');
            redirect_back_or(BASE_URL . '/');
        } else {
            if ($lf['first_at'] === null || ($now - (int)$lf['first_at']) > 600) {
                $lf['first_at'] = $now;
                $lf['count'] = 1;
            } else {
                $lf['count']++;
            }
            if ($lf['count'] >= 5) {
                $lf['blocked_until'] = $now + 600; 
                $blocked_until = $lf['blocked_until'];
            }
            $errors[] = 'E-posta veya şifre hatalı.';
        }
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Giriş Yap</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:420px;margin:auto">
      <h1>Giriş Yap</h1>

      <?php if ($blocked_until !== null): ?>
        <div class="alert alert-error">Çok fazla başarısız deneme. Lütfen birkaç dakika sonra tekrar deneyin.</div>
      <?php endif; ?>

      <?php if ($errors): ?>
        <div class="alert alert-error">
          <ul style="margin:0 0 0 18px"><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <?php if (!empty($_GET['r'])): ?>
          <input type="hidden" name="r" value="<?= e($_GET['r']) ?>">
        <?php endif; ?>

        <div style="display:grid;gap:12px">
          <label>E-posta
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
          </label>
          <label>Şifre
            <input type="password" name="password" required>
          </label>
          <div class="form-actions"><button type="submit">Giriş Yap</button></div>
        </div>
      </form>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
