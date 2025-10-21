<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/nav.php';

require_login();
require_role('firm_admin');

$u   = current_user();
$pdo = db();

$from_city = trim($_GET['from_city'] ?? '');
$to_city   = trim($_GET['to_city']   ?? '');
$date_min  = trim($_GET['date_min']  ?? '');
$date_max  = trim($_GET['date_max']  ?? '');
$status    = trim($_GET['status']    ?? '');

$page     = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($page < 1) $page = 1;
$per_page = 10;
$offset   = ($page - 1) * $per_page;

$dt_min = null; $dt_max = null;
if ($date_min !== '') { $d = DateTime::createFromFormat('Y-m-d', $date_min); if ($d) $dt_min = $d->format('Y-m-d').' 00:00'; }
if ($date_max !== '') { $d = DateTime::createFromFormat('Y-m-d', $date_max); if ($d) $dt_max = $d->format('Y-m-d').' 23:59'; }

$where  = ["t.firm_id = ?"];
$params = [(int)$u['firm_id']];
if ($from_city !== '') { $where[] = "t.from_city LIKE ?"; $params[] = "%{$from_city}%"; }
if ($to_city   !== '') { $where[] = "t.to_city   LIKE ?"; $params[] = "%{$to_city}%"; }
if ($dt_min !== null)  { $where[] = "t.depart_at >= ?";   $params[] = $dt_min; }
if ($dt_max !== null)  { $where[] = "t.depart_at <= ?";   $params[] = $dt_max; }
if ($status !== '' && in_array($status, ['active','cancelled','past'], true)) { $where[] = "t.status=?"; $params[] = $status; }
$where_sql = implode(' AND ', $where);

$stc = $pdo->prepare("SELECT COUNT(*) FROM trips t WHERE {$where_sql}");
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

$sql = "
SELECT t.id, t.from_city, t.to_city, t.depart_at, t.arrive_at, t.price_cents, t.seat_count, t.status
FROM trips t
WHERE {$where_sql}
ORDER BY t.depart_at DESC, t.id DESC
LIMIT :limit OFFSET :offset
";
$st = $pdo->prepare($sql);
foreach ($params as $i=>$v) $st->bindValue($i+1,$v);
$st->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,   PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();


function keep_query(array $extra): string {
  $q = $_GET;
  foreach ($extra as $k=>$v) { if ($v===null) unset($q[$k]); else $q[$k]=$v; }
  return http_build_query($q);
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Sefer Listesi</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>
  <main class="container" style="padding:28px 0">
    <section class="card">
      <h1>Sefer Listesi</h1>

      <form method="get" style="margin:12px 0">
        <div style="display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:12px;align-items:end">
          <label>Kalkış Şehri <input type="text" name="from_city" value="<?= e($from_city) ?>" placeholder="örn: İstanbul"></label>
          <label>Varış Şehri   <input type="text" name="to_city"   value="<?= e($to_city)   ?>" placeholder="örn: Ankara"></label>
          <label>Başlangıç Tarihi <input type="date" name="date_min" value="<?= e($date_min) ?>"></label>
          <label>Bitiş Tarihi     <input type="date" name="date_max" value="<?= e($date_max) ?>"></label>
          <label>Durum
            <select name="status">
              <option value="">Tümü</option>
              <option value="active"    <?= $status==='active'?'selected':'' ?>>aktif</option>
              <option value="cancelled" <?= $status==='cancelled'?'selected':'' ?>>iptal</option>
              <option value="past"      <?= $status==='past'?'selected':'' ?>>geçmiş</option>
            </select>
          </label>
        </div>
        <div class="btn-row" style="margin-top:12px">
          <button type="submit">Filtrele</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/firma_sefer_listesi.php">Temizle</a>
          <a class="btn" href="<?= e(BASE_URL) ?>/pages/firma_sefer_ekle.php">Yeni sefer ekle</a>
        </div>
      </form>

      <div style="margin:10px 0;color:#64748b">
        Toplam: <b><?= e((string)$total) ?></b> — Sayfa <b><?= e((string)$page) ?></b> / <?= e((string)$total_pages) ?>
      </div>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Güzergâh</th>
              <th>Kalkış</th>
              <th>Varış</th>
              <th>Fiyat (TL)</th>
              <th>Koltuk</th>
              <th>Durum</th>
              <th>Aksiyonlar</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rows): ?>
            <tr><td colspan="8">Kayıt bulunamadı.</td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td><?= e((string)$r['id']) ?></td>
              <td><?= e($r['from_city'].' → '.$r['to_city']) ?></td>
              <td><?= e($r['depart_at']) ?></td>
              <td><?= e((string)($r['arrive_at'] ?? '')) ?></td>
              <td><?= e(number_format($r['price_cents']/100,2,',','.')) ?></td>
              <td><?= e((string)$r['seat_count']) ?></td>
              <td><?= e($r['status']) ?></td>
              <td>
                <div class="table-actions">
                  <a class="btn" href="<?= e(BASE_URL) ?>/pages/sefer_duzenle.php?id=<?= e((string)$r['id']) ?>">Düzenle</a>
                  <!-- iptal butonu kaldırıldı -->
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($total_pages > 1): ?>
        <nav style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
          <?php $prev=$page>1?$page-1:null; $next=$page<$total_pages?$page+1:null; ?>
          <?php if ($prev): ?><a class="btn" href="?<?= e(keep_query(['p'=>$prev])) ?>">‹ Önceki</a><?php endif; ?>
          <?php for($i=max(1,$page-2); $i<=min($total_pages,$page+2); $i++): ?>
            <?php if ($i===$page): ?>
              <span class="btn" style="opacity:.6;cursor:default"><?= e((string)$i) ?></span>
            <?php else: ?>
              <a class="btn" href="?<?= e(keep_query(['p'=>$i])) ?>"><?= e((string)$i) ?></a>
            <?php endif; ?>
          <?php endfor; ?>
          <?php if ($next): ?><a class="btn" href="?<?= e(keep_query(['p'=>$next])) ?>">Sonraki ›</a><?php endif; ?>
        </nav>
      <?php endif; ?>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
