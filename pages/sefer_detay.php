<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

require_login();
$u   = current_user();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit('Sefer bulunamadı'); }

$st = $pdo->prepare("
SELECT t.*, f.name AS firm_name
FROM trips t
JOIN firms f ON f.id=t.firm_id
WHERE t.id=? AND t.status='active'
LIMIT 1
");
$st->execute([$id]);
$trip = $st->fetch();
if (!$trip) { http_response_code(404); exit('Sefer bulunamadı'); }

$st = $pdo->prepare("SELECT seat_no FROM tickets WHERE trip_id=? AND status='purchased'");
$st->execute([$id]);
$taken = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));

$hatalar=[]; $ok_mesaji=null;

function kupon_bul(PDO $pdo, int $firmId, string $code): ?array {
  $now = (new DateTime())->format('Y-m-d');
  $st = $pdo->prepare("
    SELECT *
    FROM coupons
    WHERE code=? AND is_active=1
      AND (valid_from IS NULL OR valid_from<=?)
      AND (valid_until IS NULL OR valid_until>=?)
      AND (firm_id IS NULL OR firm_id=?)
    LIMIT 1
  ");
  $st->execute([$code, $now, $now, $firmId]);
  $c = $st->fetch();
  if (!$c) return null;
  if ($c['max_uses'] !== null && (int)$c['used_count'] >= (int)$c['max_uses']) return null;
  return $c;
}
function uygula(int $priceCents, ?array $coupon): array {
  if (!$coupon) return [$priceCents, 0];
  $ind1 = isset($coupon['discount_percent']) && $coupon['discount_percent']!==null ? (int)$coupon['discount_percent'] : null;
  $ind2 = isset($coupon['discount_cents'])   && $coupon['discount_cents']!==null   ? (int)$coupon['discount_cents']   : null;
  $best = 0;
  if ($ind1 !== null) $best = max($best, (int)round($priceCents*$ind1/100)); // yuvarlama düzeltilmişti
  if ($ind2 !== null) $best = max($best, $ind2);
  $best = min($best, $priceCents);
  return [$priceCents - $best, $best];
}
function tl(int $c): string { return number_format($c/100, 2, ',', '.'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
  csrf_check($_POST['csrf'] ?? '');
  $seat = (int)($_POST['seat_no'] ?? 0);
  $code = strtoupper(trim((string)($_POST['coupon'] ?? '')));

  if ($seat < 1 || $seat > (int)$trip['seat_count']) $hatalar[]='Geçersiz koltuk.';
  if (in_array($seat, $taken, true)) $hatalar[]='Bu koltuk dolu.';

  $coupon = null;
  if ($code !== '') {
    $coupon = kupon_bul($pdo, (int)$trip['firm_id'], $code);
    if (!$coupon) $hatalar[]='Kupon geçersiz veya süresi doldu.';
  }

  [$tutar, $indirim] = uygula((int)$trip['price_cents'], $coupon);

  $st = $pdo->prepare("SELECT credit_cents FROM users WHERE id=? AND is_active=1");
  $st->execute([(int)$u['id']]);
  $credit = (int)$st->fetchColumn();
  if ($tutar > $credit) $hatalar[]='Cüzdan bakiyeniz yetersiz.';

  if (!$hatalar) {
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare("SELECT 1 FROM tickets WHERE trip_id=? AND seat_no=? AND status='purchased' LIMIT 1");
      $st->execute([$id,$seat]);
      if ($st->fetchColumn()) throw new Exception('Koltuk artık dolu.');

      $st = $pdo->prepare("INSERT INTO tickets(user_id, trip_id, seat_no, price_cents, coupon_id, status, purchased_at)
                           VALUES(?,?,?,?,?, 'purchased', datetime('now'))");
      $st->execute([(int)$u['id'],$id,$seat,$tutar, $coupon ? (int)$coupon['id'] : null]);

      $ticketId = (int)$pdo->lastInsertId();

      $st = $pdo->prepare("UPDATE users SET credit_cents = credit_cents - ? WHERE id=?");
      $st->execute([$tutar,(int)$u['id']]);

      $st = $pdo->prepare("INSERT INTO wallet_tx(user_id, type, amount_cents, ref_ticket_id, note, created_at)
                           VALUES(?,?,?,?,?, datetime('now'))");
      $st->execute([(int)$u['id'],'purchase',$tutar,$ticketId,'Bilet satın alma']);

      if ($coupon) {
        $st = $pdo->prepare("UPDATE coupons SET used_count=used_count+1 WHERE id=?");
        $st->execute([(int)$coupon['id']]);
      }

      $pdo->commit();
      $ok_mesaji = 'Satın alındı. Biletlerim sayfasından görüntüleyebilirsiniz.';
      $taken[] = $seat;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $hatalar[] = 'İşlem tamamlanamadı: '.$e->getMessage();
    }
  }
}
$bos_sayi = max(0, (int)$trip['seat_count'] - count($taken));
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sefer Detayı</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:780px">
      <h1>Sefer Detayı</h1>
      <p><b><?= e($trip['firm_name']) ?></b> — <?= e($trip['from_city'].' → '.$trip['to_city']) ?></p>
      <ul>
        <li>Kalkış: <?= e($trip['depart_at']) ?></li>
        <li>Varış: <?= e((string)($trip['arrive_at'] ?? '')) ?></li>
        <li>Fiyat: <?= e(tl((int)$trip['price_cents'])) ?> TL</li>
        <li>Boş koltuk: <?= e((string)$bos_sayi) ?> / <?= e((string)$trip['seat_count']) ?></li>
      </ul>

      <?php if ($ok_mesaji): ?>
        <div class="alert alert-success"><?= e($ok_mesaji) ?></div>
        <p><a class="btn" href="<?= e(BASE_URL) ?>/pages/biletlerim.php">Biletlerim</a></p>
      <?php endif; ?>

      <?php if ($hatalar): ?>
        <div class="alert alert-error"><ul style="margin:0 0 0 18px"><?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-grid-2">
          <label>Koltuk No <input type="number" name="seat_no" min="1" max="<?= e((string)$trip['seat_count']) ?>" required></label>
          <label>Kupon Kodu <input name="coupon" placeholder="Opsiyonel"></label>
        </div>
        <div class="form-actions">
          <button type="submit">Satın al</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/bilet_ara.php">Geri</a>
        </div>
      </form>

      <h3 style="margin-top:16px">Dolu Koltuklar</h3>
      <p><?= $taken ? e(implode(', ', $taken)) : 'Yok' ?></p>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
