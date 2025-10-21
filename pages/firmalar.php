<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

require_login();
$u = current_user();
if (($u['role'] ?? '') !== 'admin') { http_response_code(403); exit('Erişim engellendi.'); }

$pdo = db();
$hatalar = [];
$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'firma_ekle') {
        $name  = trim((string)($_POST['name'] ?? ''));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $act   = (int)($_POST['is_active'] ?? 1);

        if ($name === '' || mb_strlen($name) > 200) $hatalar[] = 'Firma adı zorunlu.';
        if ($email !== '' && (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 200)) $hatalar[] = 'Geçerli firma e-postası girin.';
        if (!$hatalar) {
            $st = $pdo->prepare('SELECT 1 FROM firms WHERE name=? LIMIT 1');
            $st->execute([$name]);
            if ($st->fetchColumn()) $hatalar[] = 'Bu firma adı zaten kayıtlı.';
        }
        if (!$hatalar) {
            $st = $pdo->prepare('INSERT INTO firms(name, phone, email, is_active) VALUES(?,?,?,?)');
            $st->execute([$name, $phone, $email, $act ? 1 : 0]);
            $_SESSION['flash'][] = ['t'=>'success','m'=>'Firma eklendi.'];
            header('Location: ' . (BASE_URL . '/pages/firmalar.php'));
            exit;
        }
    }

    if ($islem === 'firma_durum') {
        $fid = (int)($_POST['id'] ?? 0);
        $val = (int)($_POST['val'] ?? 1);
        if ($fid > 0) {
            $st = $pdo->prepare('UPDATE firms SET is_active=? WHERE id=?');
            $st->execute([$val ? 1 : 0, $fid]);
            $_SESSION['flash'][] = ['t'=>'success','m'=> $val ? 'Firma aktif edildi.' : 'Firma pasif edildi.'];
            header('Location: ' . (BASE_URL . '/pages/firmalar.php'));
            exit;
        }
    }
}

$firmalar = $pdo->query("
    SELECT
      f.id, f.name, f.phone, f.email, f.is_active, f.created_at,
      (SELECT COUNT(*) FROM users u WHERE u.firm_id=f.id AND u.role='firm_admin') AS admin_sayisi,
      (SELECT COUNT(*) FROM trips t WHERE t.firm_id=f.id) AS sefer_sayisi
    FROM firms f
    ORDER BY f.created_at DESC, f.id DESC
")->fetchAll();
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Firmalar</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:1100px">
      <h1>Firmalar</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <?php if ($hatalar): ?>
        <div class="alert alert-error"><ul style="margin:0 0 0 18px"><?php foreach ($hatalar as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <div class="grid-two" style="margin-bottom:16px">
        <div class="card" style="padding:16px">
          <h3>Firma Ekle</h3>
          <form method="post" class="form">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="islem" value="firma_ekle">
            <label>Firma Adı
              <input name="name" required maxlength="200" value="<?= e($_POST['name'] ?? '') ?>">
            </label>
            <label>Telefon
              <input name="phone" maxlength="50" value="<?= e($_POST['phone'] ?? '') ?>">
            </label>
            <label>E-posta
              <input type="email" name="email" maxlength="200" value="<?= e($_POST['email'] ?? '') ?>">
            </label>
            <label>Aktif mi?
              <select name="is_active">
                <option value="1" selected>Evet</option>
                <option value="0">Hayır</option>
              </select>
            </label>
            <div class="form-actions">
              <button type="submit">Kaydet</button>
            </div>
          </form>
        </div>

        <div class="card" style="padding:16px">
          <h3>Firma yöneticileri</h3>
          <p>Yönetici atama/geri alma işlemleri için <a class="brand" href="<?= e(BASE_URL) ?>/pages/admin_firma_adminleri.php">buraya</a> gidin.</p>
        </div>
      </div>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Firma</th>
              <th>İletişim</th>
              <th>Yöneticiler</th>
              <th>Sefer</th>
              <th>Durum</th>
              <th>Aksiyon</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$firmalar): ?>
              <tr><td colspan="6">Kayıt yok.</td></tr>
            <?php else: ?>
              <?php foreach ($firmalar as $r): ?>
              <tr>
                <td><?= e($r['name']) ?></td>
                <td><?= e(trim($r['phone'] . ' ' . $r['email'])) ?></td>
                <td><?= e((string)$r['admin_sayisi']) ?></td>
                <td><?= e((string)$r['sefer_sayisi']) ?></td>
                <td><?= $r['is_active'] ? 'aktif' : 'pasif' ?></td>
                <td>
                  <form method="post" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="islem" value="firma_durum">
                    <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                    <input type="hidden" name="val" value="<?= $r['is_active']? '0':'1' ?>">
                    <button class="btn"><?= $r['is_active']? 'Pasifleştir':'Aktifleştir' ?></button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
