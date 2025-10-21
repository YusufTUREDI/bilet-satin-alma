<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';
require __DIR__ . '/../inc/nav.php';

$pdo  = db();
$u    = current_user();
$role = $u['role'] ?? null;

function tlx(int $c): string { return number_format($c/100, 2, ',', '.'); }

$flash = bildirim_al();
$hatalar_satinal = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['islem'] ?? '') === 'satinal') {
    csrf_check($_POST['csrf'] ?? '');
    if (!$u || $role !== 'user') { http_response_code(403); exit('Erişim engellendi.'); }

    $trip_id = (int)($_POST['trip_id'] ?? 0);
    $seat_no = (int)($_POST['seat_no'] ?? 0);
    $kupon   = strtoupper(trim((string)($_POST['coupon'] ?? '')));

    try {
        $pdo->beginTransaction();

        $st = $pdo->prepare("
          SELECT id, firm_id, depart_at, price_cents, seat_count, status
          FROM trips WHERE id=? LIMIT 1
        ");
        $st->execute([$trip_id]);
        $t = $st->fetch();
        if (!$t) throw new RuntimeException('Sefer bulunamadı.');
        if ((string)$t['status'] !== 'active') throw new RuntimeException('Sefer satışa kapalı.');

        $now = new DateTime('now');
        $dep = new DateTime($t['depart_at']);
        if ($dep <= $now) throw new RuntimeException('Kalkış saati geçti.');

        $maxSeat = (int)$t['seat_count'];
        if ($seat_no < 1 || $seat_no > $maxSeat) throw new RuntimeException('Lütfen koltuk seçiniz.');

        $occ = $pdo->prepare("SELECT 1 FROM tickets WHERE trip_id=? AND seat_no=? AND status='purchased' LIMIT 1");
        $occ->execute([$trip_id, $seat_no]);
        if ($occ->fetchColumn()) throw new RuntimeException('Seçtiğiniz koltuk dolu.');

        $price = (int)$t['price_cents'];
        $coupon_id = null;

        if ($kupon !== '') {
            $st = $pdo->prepare("
              SELECT id, firm_id, discount_percent, discount_cents, max_uses, used_count, valid_from, valid_until, is_active
              FROM coupons
              WHERE code=? AND is_active=1
              LIMIT 1
            ");
            $st->execute([$kupon]);
            $c = $st->fetch();
            if (!$c) throw new RuntimeException('Kupon geçersiz.');
            if ($c['firm_id'] !== null && (int)$c['firm_id'] !== (int)$t['firm_id'])
                throw new RuntimeException('Kupon bu firmada geçerli değil.');

            $today = (new DateTime('now'))->format('Y-m-d');
            if ($c['valid_from']  !== null && $today <  $c['valid_from'])
                throw new RuntimeException('Kupon başlangıç tarihi gelmedi.');
            if ($c['valid_until'] !== null && $today >  $c['valid_until'])
                throw new RuntimeException('Kupon süresi doldu.');
            if ($c['max_uses']    !== null && (int)$c['used_count'] >= (int)$c['max_uses'])
                throw new RuntimeException('Kupon kullanım limiti doldu.');

            $indirim = 0;
            if ($c['discount_percent'] !== null) {
                $indirim += (int) round($price * ((float)$c['discount_percent']) / 100);
            }
            if ($c['discount_cents'] !== null) {
                $indirim += (int) $c['discount_cents'];
            }
            if ($indirim > $price) $indirim = $price;

            $price     -= $indirim;
            $coupon_id  = (int)$c['id'];
        }

        $st = $pdo->prepare("SELECT credit_cents FROM users WHERE id=? AND is_active=1");
        $st->execute([(int)$u['id']]);
        $mevcut = (int)$st->fetchColumn();
        if ($mevcut < $price) throw new RuntimeException('Cüzdan bakiyesi yetersiz.');

        $pdo->prepare("UPDATE users SET credit_cents = credit_cents - ? WHERE id=?")
            ->execute([$price, (int)$u['id']]);

        $pdo->prepare("
          INSERT INTO tickets (user_id, trip_id, seat_no, price_cents, coupon_id, status, purchased_at)
          VALUES (?, ?, ?, ?, ?, 'purchased', datetime('now'))
        ")->execute([(int)$u['id'], $trip_id, $seat_no, $price, $coupon_id]);

        $ticketId = (int)$pdo->lastInsertId();

        if ($coupon_id !== null) {
            $pdo->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id=?")->execute([$coupon_id]);
        }

        $pdo->prepare("
          INSERT INTO wallet_tx (user_id, type, amount_cents, ref_ticket_id, note, created_at)
          VALUES (?, 'purchase', ?, ?, 'Bilet satın alma', datetime('now'))
        ")->execute([(int)$u['id'], $price, $ticketId]);

        $pdo->commit();
        bildirim_ekle('success', 'Bilet satın alındı.');
        header('Location: ' . (BASE_URL . '/pages/bilet_al.php'));
        exit;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $hatalar_satinal[] = $e->getMessage();
    }
}

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
SELECT t.id, t.firm_id, t.from_city, t.to_city, t.depart_at, t.arrive_at, t.price_cents, t.seat_count,
       (SELECT COUNT(*) FROM tickets k WHERE k.trip_id=t.id AND k.status='purchased') AS sold,
       f.name AS firm_name
FROM trips t
JOIN firms f ON f.id=t.firm_id
WHERE ".implode(' AND ', $where)."
ORDER BY t.depart_at ASC, t.id ASC
LIMIT 200";
$st = $pdo->prepare($sql);
$st->execute($params);
$seferler = $st->fetchAll();
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Bilet ara ve satın al</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
  <style>
    .inline-seat{min-width:180px}
    .muted-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 12px;border:1px solid #e5e7eb;border-radius:999px;background:#fff}
  </style>
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:24px 0">
    <section class="card">
      <h1>Bilet ara ve satın al</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <?php if ($hatalar_satinal): ?>
        <div class="alert alert-error"><ul style="margin:0 0 0 18px"><?php foreach ($hatalar_satinal as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="get" class="form" style="margin-bottom:12px">
        <div class="form-grid-3">
          <label>Kalkış <input name="from_city" value="<?= e($from) ?>" placeholder="örn: İstanbul"></label>
          <label>Varış  <input name="to_city"  value="<?= e($to)   ?>" placeholder="örn: Ankara"></label>
          <label>Tarih  <input type="date" name="date" value="<?= e($dt) ?>"></label>
        </div>
        <div class="form-actions">
          <button type="submit">Ara</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/bilet_al.php">Temizle</a>
        </div>
      </form>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>Firma</th>
              <th>Güzergâh</th>
              <th>Kalkış</th>
              <th>Varış</th>
              <th>Fiyat</th>
              <th>Boş</th>
              <th>Satın alma</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$seferler): ?>
            <tr><td colspan="7">Uygun sefer bulunamadı.</td></tr>
          <?php else: foreach ($seferler as $r):
            $bos = max(0, (int)$r['seat_count'] - (int)$r['sold']);

            $kSt = $pdo->prepare("
              SELECT code, discount_percent, discount_cents
              FROM coupons
              WHERE is_active=1
                AND (firm_id IS NULL OR firm_id = ?)
                AND (valid_from IS NULL OR valid_from <= date('now'))
                AND (valid_until IS NULL OR valid_until >= date('now'))
                AND (max_uses IS NULL OR used_count < max_uses)
              ORDER BY firm_id IS NULL DESC, code ASC
            ");
            $kSt->execute([(int)$r['firm_id']]);
            $aktifKuponlar = $kSt->fetchAll();

            $stOcc = $pdo->prepare("SELECT seat_no FROM tickets WHERE trip_id=? AND status='purchased'");
            $stOcc->execute([(int)$r['id']]);
            $occupied = array_map('intval', $stOcc->fetchAll(PDO::FETCH_COLUMN));
            $maxSeat  = (int)$r['seat_count'];
          ?>
            <tr>
              <td><?= e($r['firm_name']) ?></td>
              <td><?= e($r['from_city'].' → '.$r['to_city']) ?></td>
              <td><?= e($r['depart_at']) ?></td>
              <td><?= e((string)($r['arrive_at'] ?? '')) ?></td>
              <td><?= e(tlx((int)$r['price_cents'])) ?> TL</td>
              <td><?= e((string)$bos) ?></td>
              <td>
                <?php if (!$u || $role !== 'user'): ?>
                  <a class="btn" href="<?= e(BASE_URL) ?>/pages/login.php?r=<?= e(urlencode(PAGES_URL.'/bilet_al.php')) ?>">Giriş yap</a>
                <?php elseif ($bos < 1): ?>
                  <span class="muted-badge">Dolu</span>
                <?php else: ?>
                  <form method="post" class="buy-form" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="islem" value="satinal">
                    <input type="hidden" name="trip_id" value="<?= e((string)$r['id']) ?>">

                    <select name="seat_no" class="inline-seat" required>
                      <option value="">Koltuk seç</option>
                      <?php for ($i=1; $i<=$maxSeat; $i++):
                        $isOcc = in_array($i, $occupied, true);
                      ?>
                        <option value="<?= $i ?>" <?= $isOcc ? 'disabled' : '' ?>>
                          <?= $i . ($isOcc ? ' (dolu)' : '') ?>
                        </option>
                      <?php endfor; ?>
                    </select>

                    <select name="coupon" style="max-width:240px">
                      <option value="">Kupon (yok)</option>
                      <?php foreach ($aktifKuponlar as $k):
                        $indTxt = [];
                        if ($k['discount_percent'] !== null) {
                          $indTxt[] = '%' . (int) round((float)$k['discount_percent']);
                        }
                        if ($k['discount_cents'] !== null) {
                          $indTxt[] = number_format(((int)$k['discount_cents'])/100, 2, ',', '.') . ' TL';
                        }
                      ?>
                        <option value="<?= e($k['code']) ?>"><?= e($k['code'].' — '.implode(' + ', $indTxt)) ?></option>
                      <?php endforeach; ?>
                    </select>

                    <button type="submit" class="btn">Satın al</button>
                  </form>
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
