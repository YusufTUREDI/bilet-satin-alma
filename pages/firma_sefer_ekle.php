<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/nav.php';

require_login();
require_role('firm_admin');

$u   = current_user();
$pdo = db();

$st = $pdo->prepare('SELECT is_active FROM firms WHERE id=? LIMIT 1');
$st->execute([(int)$u['firm_id']]);
$firm = $st->fetch();
if (!$firm || (int)$firm['is_active'] !== 1) {
    http_response_code(403);
    echo nav_html(); ?>
    <!doctype html><html lang="tr"><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <title>Erişim Engellendi</title>
      <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
    </head><body>
      <main class="container" style="padding:48px 0;display:grid;place-items:center;min-height:60vh">
        <section class="card" style="max-width:640px;text-align:center">
          <h1 style="margin-bottom:8px">Erişim Engellendi</h1>
          <div class="alert alert-error" style="justify-content:center">Firmanız pasif. Bu işlem yapılamaz.</div>
          <div class="form-actions" style="justify-content:center;margin-top:8px">
            <a class="btn" href="<?= e(BASE_URL) ?>/">Ana sayfa</a>
          </div>
        </section>
      </main>
      <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
    </body></html><?php
    exit;
}

function tl_to_cents(string $s): ?int {
    $norm = trim($s);
    $norm = str_replace([' ', "\u{00A0}"], '', $norm);
    if (strpos($norm, ',') !== false) {
        $norm = str_replace('.', '', $norm);
        $norm = str_replace(',', '.', $norm);
    }
    if (!is_numeric($norm)) return null;
    return (int)round(((float)$norm) * 100);
}
function opt($val, $sel) { return (string)$val === (string)$sel ? 'selected' : ''; }

$hatalar  = [];
$basarili = false;

$from_city    = (string)($_POST['from_city'] ?? '');
$to_city      = (string)($_POST['to_city'] ?? '');
$price_raw    = (string)($_POST['price'] ?? '');
$seat_count   = (string)($_POST['seat_count'] ?? '40');

$today = (new DateTime('today'))->format('Y-m-d');

$depart_date  = (string)($_POST['depart_date']  ?? '');
$depart_hour  = (string)($_POST['depart_hour']  ?? '12');
$depart_min   = (string)($_POST['depart_min']   ?? '0');

$arrive_date  = (string)($_POST['arrive_date']  ?? '');
$arrive_hour  = (string)($_POST['arrive_hour']  ?? '16');
$arrive_min   = (string)($_POST['arrive_min']   ?? '0');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');

    $from_city = trim($from_city);
    $to_city   = trim($to_city);
    if ($from_city === '' || mb_strlen($from_city) > 80) $hatalar[] = 'Kalkış şehri zorunlu (≤80).';
    if ($to_city   === '' || mb_strlen($to_city)   > 80) $hatalar[] = 'Varış şehri zorunlu (≤80).';
    if ($from_city !== '' && $from_city === $to_city)     $hatalar[] = 'Kalkış ve varış şehirleri farklı olmalıdır.';

    $depart_at = null;
    if ($depart_date === '') {
        $hatalar[] = 'Kalkış tarihi seçin.';
    } else {
        $dh = sprintf('%02d', (int)$depart_hour);
        $dm = sprintf('%02d', (int)$depart_min);
        $depart_at = "$depart_date $dh:$dm";
        $d1 = DateTime::createFromFormat('Y-m-d H:i', $depart_at);
        if (!$d1) {
            $hatalar[] = 'Kalkış zamanı formatı geçersiz.';
        } else {
            $now = new DateTime('now');
            if ($d1 <= $now) $hatalar[] = 'Kalkış zamanı gelecek bir zaman olmalı.';
        }
    }

    $arrive_at = null;
    if ($arrive_date !== '') {
        $ah = sprintf('%02d', (int)$arrive_hour);
        $am = sprintf('%02d', (int)$arrive_min);
        $arrive_at = "$arrive_date $ah:$am";
        $d2 = DateTime::createFromFormat('Y-m-d H:i', $arrive_at);
        if (!$d2) $hatalar[] = 'Varış zamanı formatı geçersiz.';
        if (isset($d1, $d2) && $d2 <= $d1) $hatalar[] = 'Varış, kalkıştan sonra olmalıdır.';
    }

    $price_cents = tl_to_cents($price_raw);
    if ($price_cents === null || $price_cents < 0) $hatalar[] = 'Fiyat geçersiz.';

    $seat = (int)$seat_count;
    if ($seat < 1 || $seat > 40) $hatalar[] = 'Koltuk sayısı 1–40 arası olmalı.';

    if (!$hatalar) {
        $st = $pdo->prepare('INSERT INTO trips (firm_id, from_city, to_city, depart_at, arrive_at, price_cents, seat_count, status)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
        $st->execute([
            (int)$u['firm_id'],
            $from_city,
            $to_city,
            $depart_at,
            $arrive_at ?: null,
            $price_cents,
            $seat,
            'active'
        ]);
        $basarili = true;

        $from_city=''; $to_city=''; $price_raw=''; $seat_count='40';
        $depart_date=''; $depart_hour='12'; $depart_min='0';
        $arrive_date=''; $arrive_hour='16'; $arrive_min='0';
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Yeni Sefer</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
  <style>.grid-3{display:grid;gap:10px;grid-template-columns:140px 100px 100px}@media (max-width:720px){ .grid-3{grid-template-columns:1fr 1fr 1fr} }</style>
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:820px">
      <h1>Yeni Sefer</h1>

      <?php if ($basarili): ?><div class="alert alert-success">Sefer kaydedildi.</div><?php endif; ?>
      <?php if ($hatalar): ?>
        <div class="alert alert-error"><ul style="margin:0 0 0 18px"><?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="post" class="form" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <div class="form-grid-2">
          <label>Kalkış Şehri <input name="from_city" required maxlength="80" value="<?= e($from_city) ?>" placeholder="örn: İstanbul"></label>
          <label>Varış Şehri   <input name="to_city"  required maxlength="80" value="<?= e($to_city)   ?>" placeholder="örn: Ankara"></label>
        </div>

        <hr>

        <div class="form-grid-2">
          <div>
            <label>Kalkış Tarihi / Saati</label>
            <div class="grid-3">
              <input type="date" name="depart_date" value="<?= e($depart_date) ?>" min="<?= e($today) ?>">
              <select name="depart_hour"><?php for($h=0;$h<=23;$h++): ?><option value="<?= $h ?>" <?= opt($h,$depart_hour) ?>><?= sprintf('%02d',$h) ?></option><?php endfor; ?></select>
              <select name="depart_min"><?php for($m=0;$m<=55;$m+=5): ?><option value="<?= $m ?>" <?= opt($m,$depart_min) ?>><?= sprintf('%02d',$m) ?></option><?php endfor; ?></select>
            </div>
          </div>

          <div>
            <label>Varış Tarihi / Saati <span class="small">(opsiyonel)</span></label>
            <div class="grid-3">
              <input type="date" name="arrive_date" value="<?= e($arrive_date) ?>" min="<?= e($depart_date ?: $today) ?>">
              <select name="arrive_hour"><?php for($h=0;$h<=23;$h++): ?><option value="<?= $h ?>" <?= opt($h,$arrive_hour) ?>><?= sprintf('%02d',$h) ?></option><?php endfor; ?></select>
              <select name="arrive_min"><?php for($m=0;$m<=55;$m+=5): ?><option value="<?= $m ?>" <?= opt($m,$arrive_min) ?>><?= sprintf('%02d',$m) ?></option><?php endfor; ?></select>
            </div>
          </div>
        </div>

        <hr>

        <div class="form-grid-2">
          <label>Fiyat (TL) <input name="price" required inputmode="decimal" placeholder="örn: 299,90" value="<?= e($price_raw) ?>"></label>
          <label>Koltuk Sayısı <input type="number" name="seat_count" min="1" max="40" required value="<?= e($seat_count) ?>"></label>
        </div>

        <div class="form-actions" style="margin-top:12px">
          <button type="submit">Kaydet</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/firma_sefer_listesi.php">Listeye dön</a>
        </div>
      </form>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
