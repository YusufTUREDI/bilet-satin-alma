<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

require_login();
$u   = current_user();
$pdo = db();

$flash = $_SESSION['flash'] ?? [];
unset($_SESSION['flash']);

$st = $pdo->prepare("
SELECT k.id, k.seat_no, k.price_cents, k.status, k.purchased_at, k.cancelled_at,
       t.from_city, t.to_city, t.depart_at, t.arrive_at,
       f.name AS firm_name
FROM tickets k
JOIN trips t ON t.id=k.trip_id
JOIN firms f ON f.id=t.firm_id
WHERE k.user_id=?
ORDER BY k.purchased_at DESC, k.id DESC
");
$st->execute([(int)$u['id']]);
$rows = $st->fetchAll();

function tl(int $c): string { return number_format($c/100, 2, ',', '.'); }
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Biletlerim</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card">
      <h1>Biletlerim</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Firma</th>
              <th>Güzergâh</th>
              <th>Kalkış</th>
              <th>Koltuk</th>
              <th>Fiyat</th>
              <th>Durum</th>
              <th>Satın alma</th>
              <th>İşlem</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="8">Bilet bulunamadı.</td></tr>
            <?php else: foreach ($rows as $r): ?>
              <?php
                $iptal_olabilir = false;
                if ((string)$r['status'] === 'purchased') {
                  $now = new DateTime('now');
                  $dep = new DateTime($r['depart_at']);
              
                  $iptal_olabilir = ($dep > (clone $now)->modify('+60 minute'));
                }
                $koltukGor = abs((int)$r['seat_no']); 
              ?>
              <tr>
                <td><?= e($r['firm_name']) ?></td>
                <td><?= e($r['from_city'].' → '.$r['to_city']) ?></td>
                <td><?= e($r['depart_at']) ?></td>
                <td><?= $koltukGor > 0 ? e((string)$koltukGor) : '—' ?></td>
                <td><?= e(tl((int)$r['price_cents'])) ?> TL</td>
                <td><?= e($r['status']) ?></td>
                <td><?= e($r['purchased_at']) ?></td>
                <td>
                  <?php if ($iptal_olabilir): ?>
                    <form method="post" action="<?= e(BASE_URL) ?>/pages/bilet_iptal.php" onsubmit="return confirm('Bu bileti iptal etmek istiyor musunuz?');" style="display:inline">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="ticket_id" value="<?= e((string)$r['id']) ?>">
                      <button class="btn danger" type="submit">İptal et</button>
                    </form>
                  <?php else: ?>
                    <span class="muted">—</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
