<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

require_login();
require_role('admin');

$pdo = db();

$hatalar = [];
$flash   = [];

$firmalar = $pdo->query("SELECT id, name FROM firms WHERE is_active=1 ORDER BY name")->fetchAll();
$uyeler   = $pdo->query("SELECT id, name, email FROM users WHERE role='user' AND is_active=1 ORDER BY name, email")->fetchAll();
$yonetici_list = $pdo->query("
    SELECT u.id, u.name, u.email, u.firm_id, f.name AS firm_name
    FROM users u
    LEFT JOIN firms f ON f.id = u.firm_id
    WHERE u.role='firm_admin' AND u.is_active=1
    ORDER BY f.name, u.name, u.email
")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $act = $_POST['act'] ?? '';

    if ($act === 'promote') {
        $firm_id = (int)($_POST['firm_id'] ?? 0);
        $user_id = (int)($_POST['user_id'] ?? 0);

        if ($firm_id <= 0) $hatalar[] = 'Firma seçiniz.';
        if ($user_id <= 0) $hatalar[] = 'Kullanıcı seçiniz.';

        if (!$hatalar) {
            $st = $pdo->prepare("SELECT id FROM firms WHERE id=? AND is_active=1");
            $st->execute([$firm_id]);
            if (!$st->fetchColumn()) $hatalar[] = 'Firma bulunamadı veya pasif.';

            $st = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='user' AND is_active=1");
            $st->execute([$user_id]);
            if (!$st->fetchColumn()) $hatalar[] = 'Seçilen kullanıcı uygun değil.';
        }

        if (!$hatalar) {
            $st = $pdo->prepare("UPDATE users SET role='firm_admin', firm_id=? WHERE id=?");
            $st->execute([$firm_id, $user_id]);
            $flash[] = ['t'=>'success','m'=>'Kullanıcı firma yöneticisi yapıldı.'];
        }
    }
    elseif ($act === 'demote') {
        $fa_id = (int)($_POST['fa_user_id'] ?? 0);
        if ($fa_id <= 0) $hatalar[] = 'Firma yöneticisi seçiniz.';

        if (!$hatalar) {
            $st = $pdo->prepare("SELECT id FROM users WHERE id=? AND role='firm_admin' AND is_active=1");
            $st->execute([$fa_id]);
            if (!$st->fetchColumn()) $hatalar[] = 'Seçilen kişi firma yöneticisi değil.';
        }

        if (!$hatalar) {
            $st = $pdo->prepare("UPDATE users SET role='user', firm_id=NULL WHERE id=?");
            $st->execute([$fa_id]);
            $flash[] = ['t'=>'success','m'=>'Firma yöneticisi kullanıcı rolüne düşürüldü.'];
        }
    }

    $_SESSION['flash'] = array_merge($flash, $hatalar ? [['t'=>'error','m'=>implode(' ', $hatalar)]] : []);
    header('Location: ' . (BASE_URL . '/pages/admin_firma_adminleri.php'));
    exit;
}

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Firma Yöneticileri</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:900px">
      <h1>Firma Yöneticileri</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':($f['t']==='error'?'alert-error':'') ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <h2>Yükselt: Üyeyi Firma Yöneticisi Yap</h2>
      <form method="post" class="form" action="<?= e(BASE_URL) ?>/pages/admin_firma_adminleri.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act"  value="promote">
        <div class="form-grid-2">
          <label>Firma
            <select name="firm_id" required>
              <option value="">Seçiniz…</option>
              <?php foreach ($firmalar as $f): ?>
                <option value="<?= e((string)$f['id']) ?>"><?= e($f['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label>Kullanıcı (ad + e-posta)
            <select name="user_id" required>
              <option value="">Seçiniz…</option>
              <?php foreach ($uyeler as $u): ?>
                <option value="<?= e((string)$u['id']) ?>"><?= e($u['name'] . ' — ' . $u['email']) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-actions">
          <button type="submit">Yükselt</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/">Ana sayfa</a>
        </div>
      </form>

      <hr style="margin:20px 0;opacity:.2">

      <h2>Geri Al: Firma Yöneticisini Kullanıcı Yap</h2>
      <form method="post" class="form" action="<?= e(BASE_URL) ?>/pages/admin_firma_adminleri.php">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="act"  value="demote">
        <div class="form-grid-2">
          <label>Firma yöneticisi
            <select name="fa_user_id" required>
              <option value="">Seçiniz…</option>
              <?php foreach ($yonetici_list as $r): ?>
                <option value="<?= e((string)$r['id']) ?>">
                  <?= e(($r['firm_name'] ?? '—') . ' — ' . $r['name'] . ' — ' . $r['email']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <div class="form-actions">
          <button type="submit" class="btn danger">Geri al</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/">Ana sayfa</a>
        </div>
      </form>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
