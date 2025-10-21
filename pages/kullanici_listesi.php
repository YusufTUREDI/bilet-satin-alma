<?php
require __DIR__.'/../inc/bootstrap.php';
require __DIR__.'/../inc/auth.php';
require __DIR__.'/../inc/nav.php';

require_login();
$me = current_user();
if (($me['role'] ?? '') !== 'admin') { http_response_code(403); exit('Erişim yok'); }

$pdo = db();
$rows = $pdo->query("
  SELECT u.id, u.name, u.email, u.role, u.is_active, u.credit_cents, u.created_at,
         f.name AS firm_name
  FROM users u
  LEFT JOIN firms f ON f.id=u.firm_id
  ORDER BY CASE u.role WHEN 'firm_admin' THEN 0 WHEN 'user' THEN 1 ELSE 2 END, u.created_at DESC
")->fetchAll();

function tl(int $c){return number_format($c/100,2,',','.');}
?>
<!doctype html><html lang="tr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Kullanıcılar</title>
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head><body>
<?= nav_html(); ?>
<main class="container" style="padding:28px 0">
  <section class="card">
    <h1>Kullanıcılar</h1>
    <div style="overflow:auto">
      <table>
        <thead><tr>
          <th>ID</th><th>Ad Soyad</th><th>E-posta</th><th>Rol</th><th>Firma</th><th>Aktif</th><th>Cüzdan</th><th>Kayıt</th>
        </tr></thead>
        <tbody>
          <?php if(!$rows): ?><tr><td colspan="8">Kayıt yok.</td></tr>
          <?php else: foreach($rows as $r): ?>
            <tr>
              <td><?= e((string)$r['id']) ?></td>
              <td><?= e($r['name']) ?></td>
              <td><?= e($r['email']) ?></td>
              <td><?= e($r['role']) ?></td>
              <td><?= e((string)($r['firm_name'] ?? '—')) ?></td>
              <td><?= $r['is_active'] ? 'Evet':'Hayır' ?></td>
              <td><?= e(tl((int)$r['credit_cents'])) ?> TL</td>
              <td><?= e($r['created_at']) ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </section>
</main>
<script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body></html>
