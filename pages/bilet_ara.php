<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

require_login();
$pdo = db();

$from = trim((string)($_GET['from_city'] ?? ''));
$to   = trim((string)($_GET['to_city'] ?? ''));
$dt   = trim((string)($_GET['date'] ?? ''));

$where = ["t.status='active'"];
$params = [];
if ($from !== '') { $where[] = "t.from_city LIKE ?"; $params[] = "%{$from}%"; }
if ($to   !== '') { $where[] = "t.to_city LIKE ?";   $params[] = "%{$to}%"; }
if ($dt   !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $dt);
  if ($d) {
    $where[] = "t.depart_at BETWEEN ? AND ?";
    $params[] = $d->format('Y-m-d') . ' 00:00';
    $params[] = $d->format('Y-m-d') . ' 23:59';
  }
}
$sql = "
SELECT t.id, t.from_city, t.to_city, t.depart_at, t.arrive_at, t.price_cents, t.seat_count,
       (SELECT COUNT(*) FROM tickets k WHERE k.trip_id=t.id AND k.status='purchased') AS sold
FROM trips t
WHERE ".implode(' AND ', $where)."
ORDER BY t.depart_at ASC, t.id ASC
LIMIT 200
";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll();

function tl(int $c): string { return number_format($c/100, 2, ',', '.'); }
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bilet Ara</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card">
      <h1>Bilet Ara</h1>
      <form method="get" class="form" style="margin-bottom:12px">
        <div class="form-grid-3">
          <label>Kalkış
            <input name="from_city" value="<?= e($from) ?>" placeholder="örn: İstanbul">
          </label>
          <label>Varış
            <input name="to_city" value="<?= e($to) ?>" placeholder="örn: Ankara">
          </label>
          <label>Tarih
            <input type="date" name="date" value="<?= e($dt) ?>">
          </label>
        </div>
        <div class="form-actions">
          <button type="submit">Ara</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/bilet_ara.php">Temizle</a>
        </div>
      </form>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Güzergâh</th>
              <th>Kalkış</th>
              <th>Varış</th>
              <th>Fiyat</th>
              <th>Boş</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="6">Uygun sefer bulunamadı.</td></tr>
            <?php else: foreach ($rows as $r):
              $bos = max(0, (int)$r['seat_count'] - (int)$r['sold']); ?>
              <tr>
                <td><?= e($r['from_city'].' → '.$r['to_city']) ?></td>
                <td><?= e($r['depart_at']) ?></td>
                <td><?= e((string)($r['arrive_at'] ?? '')) ?></td>
                <td><?= e(tl((int)$r['price_cents'])) ?> TL</td>
                <td><?= e((string)$bos) ?></td>
                <td><a class="btn" href="<?= e(BASE_URL) ?>/pages/sefer_detay.php?id=<?= e((string)$r['id']) ?>">Satın al</a></td>
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
