<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/nav.php';

require_login();
require_role('firm_admin');

$kullanici = current_user();
$pdo = db();

function bul_firma_seferi(PDO $pdo, int $seferId, int $firmaId): ?array {
    $st = $pdo->prepare("
        SELECT id, firm_id, from_city, to_city, depart_at, arrive_at,
               price_cents, seat_count, status
        FROM trips
        WHERE id = ? AND firm_id = ?
        LIMIT 1
    ");
    $st->execute([$seferId, $firmaId]);
    $r = $st->fetch();
    return $r ?: null;
}

function tarih_saat_coz(string $girdi): ?string {
    $s = trim($girdi);
    if ($s === '') return null;
    $s = str_replace(['T', '/', '.'], [' ', '-', '-'], $s);
    $s = preg_replace('/\s+/', ' ', $s);
    $dt = DateTime::createFromFormat('Y-m-d H:i', $s);
    if (!$dt) return null;
    $err = DateTime::getLastErrors();
    if (($err['warning_count'] ?? 0) > 0 || ($err['error_count'] ?? 0) > 0) return null;
    return $dt->format('Y-m-d H:i');
}

function fiyat_kurusa(string $tl): ?int {
    if ($tl === '') return null;
    $n = str_replace([' ', ','], ['', '.'], $tl);
    if (substr_count($n, '.') > 1) return null;
    if (!is_numeric($n)) return null;
    return (int)round(((float)$n) * 100);
}

$seferId = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
if ($seferId <= 0) { http_response_code(400); exit('Geçersiz sefer.'); }

$sefer = bul_firma_seferi($pdo, $seferId, (int)$kullanici['firm_id']);
if (!$sefer) { http_response_code(404); exit('Kayıt bulunamadı.'); }

$hatalar = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');

    $from_city = trim($_POST['from_city'] ?? '');
    $to_city   = trim($_POST['to_city']   ?? '');
    $depart_at = trim($_POST['depart_at'] ?? '');
    $arrive_at = trim($_POST['arrive_at'] ?? '');
    $price     = trim($_POST['price']     ?? '');
    $seat      = (int)($_POST['seat_count'] ?? 0);
    $status    = trim($_POST['status']    ?? 'active');

    if ($from_city === '' || mb_strlen($from_city) > 80) $hatalar[] = 'Kalkış şehri zorunlu (<=80).';
    if ($to_city   === '' || mb_strlen($to_city)   > 80) $hatalar[] = 'Varış şehri zorunlu (<=80).';

    $depart_sql = tarih_saat_coz($depart_at);
    if (!$depart_sql) $hatalar[] = 'Kalkış zamanı formatı geçersiz (ör: 2025-11-11 12:00).';

    $arrive_sql = null;
    if ($arrive_at !== '') {
        $arrive_sql = tarih_saat_coz($arrive_at);
        if (!$arrive_sql) $hatalar[] = 'Varış zamanı formatı geçersiz (ör: 2025-11-11 23:59).';
    }

    $price_cents = fiyat_kurusa($price);
    if ($price_cents === null) $hatalar[] = 'Fiyat sayısal olmalı.';
    if ($price_cents !== null && $price_cents < 0) $hatalar[] = 'Fiyat negatif olamaz.';

    if ($seat < 1 || $seat > 40) $hatalar[] = 'Koltuk sayısı 1-40 arası olmalı.';

    if (!in_array($status, ['active','cancelled','past'], true)) $hatalar[] = 'Geçersiz durum.';

    if (!$hatalar && $arrive_sql && $depart_sql && ($arrive_sql < $depart_sql)) $hatalar[] = 'Varış, kalkıştan önce olamaz.';

    if (!$hatalar) {
        $sql = "
            UPDATE trips
            SET from_city   = :from_city,
                to_city     = :to_city,
                depart_at   = :depart_at,
                arrive_at   = :arrive_at,
                price_cents = :price_cents,
                seat_count  = :seat_count,
                status      = :status
            WHERE id = :id AND firm_id = :firm_id
        ";
        $st = $pdo->prepare($sql);
        $st->bindValue(':from_city',   $from_city);
        $st->bindValue(':to_city',     $to_city);
        $st->bindValue(':depart_at',   $depart_sql);
        $st->bindValue(':arrive_at',   $arrive_sql);
        $st->bindValue(':price_cents', $price_cents, PDO::PARAM_INT);
        $st->bindValue(':seat_count',  $seat,        PDO::PARAM_INT);
        $st->bindValue(':status',      $status);
        $st->bindValue(':id',          $seferId,     PDO::PARAM_INT);
        $st->bindValue(':firm_id',     (int)$kullanici['firm_id'], PDO::PARAM_INT);
        $st->execute();

        $ok = true;
        $sefer = bul_firma_seferi($pdo, $seferId, (int)$kullanici['firm_id']);
    }
}

$fv_from  = $_POST['from_city']  ?? $sefer['from_city'];
$fv_to    = $_POST['to_city']    ?? $sefer['to_city'];
$fv_dep   = $_POST['depart_at']  ?? $sefer['depart_at'];
$fv_arr   = $_POST['arrive_at']  ?? ($sefer['arrive_at'] ?? '');
$fv_price = $_POST['price']      ?? number_format($sefer['price_cents']/100, 2, '.', '');
$fv_seat  = $_POST['seat_count'] ?? (string)$sefer['seat_count'];
$fv_stat  = $_POST['status']     ?? $sefer['status'];
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sefer Düzenle</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card" style="max-width:720px">
      <h1>Sefer Düzenle</h1>

      <?php if ($ok): ?><div class="alert alert-success">Değişiklikler kaydedildi.</div><?php endif; ?>
      <?php if ($hatalar): ?>
        <div class="alert alert-error"><ul style="margin:0 0 0 18px"><?php foreach ($hatalar as $h): ?><li><?= e($h) ?></li><?php endforeach; ?></ul></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="id" value="<?= e((string)$seferId) ?>">

        <div style="display:grid;gap:12px;grid-template-columns:repeat(2, minmax(0,1fr))">
          <label>Kalkış Şehri <input name="from_city" value="<?= e($fv_from) ?>" required maxlength="80"></label>
          <label>Varış Şehri <input name="to_city" value="<?= e($fv_to) ?>" required maxlength="80"></label>
          <label>Kalkış (y-a-g s:d) <input name="depart_at" value="<?= e($fv_dep) ?>" placeholder="2025-11-11 12:00" required></label>
          <label>Varış (y-a-g s:d) — opsiyonel <input name="arrive_at" value="<?= e($fv_arr) ?>" placeholder="2025-11-11 23:59"></label>
          <label>Fiyat (TL) <input name="price" value="<?= e($fv_price) ?>" required></label>
          <label>Koltuk Sayısı <input type="number" name="seat_count" value="<?= e($fv_seat) ?>" min="1" max="40" required></label>
          <label>Durum
            <select name="status">
              <option value="active"    <?= $fv_stat==='active'?'selected':'' ?>>aktif</option>
              <option value="cancelled" <?= $fv_stat==='cancelled'?'selected':'' ?>>iptal edildi</option>
              <option value="past"      <?= $fv_stat==='past'?'selected':'' ?>>geçmiş</option>
            </select>
          </label>
        </div>

        <div class="form-actions">
          <button type="submit">Kaydet</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/firma_sefer_listesi.php">Listeye dön</a>
        </div>
      </form>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js"<?= script_nonce_attr(); ?>></script>
</body>
</html>
