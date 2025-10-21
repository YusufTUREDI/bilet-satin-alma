<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';
require dirname(__DIR__) . '/inc/nav.php';

require_login();

$u   = current_user();
$pdo = db();
$rol = $u['role'] ?? '';

if (!in_array($rol, ['admin','firm_admin'], true)) { http_response_code(403); exit('Erişim engellendi.'); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check($_POST['csrf'] ?? '');
    $islem = $_POST['islem'] ?? '';

    if ($islem === 'ekle') {
        $firm_id = null;
        if ($rol === 'admin') {
            $raw = $_POST['firm_id'] ?? '';
            $firm_id = ($raw === '' || $raw === 'global') ? null : (int)$raw;
        } else {
            $firm_id = (int)($u['firm_id'] ?? 0);
            if ($firm_id <= 0) { bildirim_ekle('error','Firma bilgisi bulunamadı.'); header('Location: '.BASE_URL.'/pages/kuponlar.php'); exit; }
        }

        $code   = strtoupper(trim($_POST['code'] ?? ''));
        $p_ind  = trim($_POST['discount_percent'] ?? '');
        $t_ind  = trim($_POST['discount_cents']   ?? '');
        $max    = trim($_POST['max_uses']         ?? '');
        $vf     = trim($_POST['valid_from']       ?? '');
        $vu     = trim($_POST['valid_until']      ?? '');
        $aktif  = isset($_POST['is_active']) ? 1 : 0;

        $h = [];
        if ($code === '' || !preg_match('/^[A-Z0-9_-]{3,32}$/', $code)) $h[]='Kod 3-32 karakter, A-Z 0-9 _ - olmalı.';
        $percent = ($p_ind === '') ? null : (int)$p_ind;
        $cents   = ($t_ind === '') ? null : (int)$t_ind;
        if ($percent === null && $cents === null) $h[]='Yüzde veya tutar indirimi girin.';
        if ($percent !== null && ($percent<0 || $percent>100)) $h[]='Yüzde 0-100 arası.';
        if ($cents   !== null && $cents<0) $h[]='Tutar indirimi negatif olamaz.';
        $max_uses = ($max==='')? null : (int)$max;
        if ($max_uses !== null && $max_uses<1) $h[]='Maksimum kullanım 1+ olmalı.';
        $vf_sql = ($vf!=='') ? date('Y-m-d', strtotime($vf)) : null;
        $vu_sql = ($vu!=='') ? date('Y-m-d', strtotime($vu)) : null;
        if ($vf_sql && $vu_sql && $vu_sql < $vf_sql) $h[]='Bitiş, başlangıçtan önce olamaz.';

        if (!$h) {
            if ($firm_id === null) {
                $st = $pdo->prepare("SELECT 1 FROM coupons WHERE code=? AND firm_id IS NULL LIMIT 1");
                $st->execute([$code]);
            } else {
                $st = $pdo->prepare("SELECT 1 FROM coupons WHERE code=? AND (firm_id = ? OR firm_id IS NULL) LIMIT 1");
                $st->execute([$code, $firm_id]);
            }
            if ($st->fetchColumn()) $h[]='Bu kod zaten kullanılıyor.';
        }

        if ($h) {
            bildirim_ekle('error', implode(' ', $h));
            header('Location: '.BASE_URL.'/pages/kuponlar.php');
            exit;
        }

        $sql = "INSERT INTO coupons (firm_id, code, discount_percent, discount_cents, max_uses, valid_from, valid_until, is_active)
                VALUES (:firm_id,:code,:p,:c,:m,:vf,:vu,:a)";
        $st = $pdo->prepare($sql);
        $st->bindValue(':firm_id', $firm_id, $firm_id===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $st->bindValue(':code',    $code);
        $st->bindValue(':p',       $percent, $percent===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $st->bindValue(':c',       $cents,   $cents===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $st->bindValue(':m',       $max_uses, $max_uses===null?PDO::PARAM_NULL:PDO::PARAM_INT);
        $st->bindValue(':vf',      $vf_sql);
        $st->bindValue(':vu',      $vu_sql);
        $st->bindValue(':a',       $aktif, PDO::PARAM_INT);
        $st->execute();

        bildirim_ekle('success','Kupon oluşturuldu.');
        header('Location: '.BASE_URL.'/pages/kuponlar.php');
        exit;
    }

    if ($islem === 'durum') {
        $id = (int)($_POST['id'] ?? 0);
        $to = (int)($_POST['to'] ?? -1);
        if ($id<=0 || ($to!==0 && $to!==1)) { bildirim_ekle('error','Geçersiz istek.'); header('Location: '.BASE_URL.'/pages/kuponlar.php'); exit; }

        if ($rol === 'firm_admin') {
            $st = $pdo->prepare("SELECT firm_id FROM coupons WHERE id=?");
            $st->execute([$id]);
            $fid = $st->fetchColumn();
            if ((int)$fid !== (int)$u['firm_id']) { bildirim_ekle('error','Yetki yok.'); header('Location: '.BASE_URL.'/pages/kuponlar.php'); exit; }
        }

        $st = $pdo->prepare("UPDATE coupons SET is_active=? WHERE id=?");
        $st->execute([$to,$id]);
        bildirim_ekle('success','Durum güncellendi.');
        header('Location: '.BASE_URL.'/pages/kuponlar.php');
        exit;
    }

    if ($islem === 'sil') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id<=0) { bildirim_ekle('error','Geçersiz istek.'); header('Location: '.BASE_URL.'/pages/kuponlar.php'); exit; }

        if ($rol === 'firm_admin') {
            $st = $pdo->prepare("SELECT firm_id FROM coupons WHERE id=?");
            $st->execute([$id]);
            $fid = $st->fetchColumn();
            if ((int)$fid !== (int)$u['firm_id']) { bildirim_ekle('error','Yetki yok.'); header('Location: '.BASE_URL.'/pages/kuponlar.php'); exit; }
        }

        $st = $pdo->prepare("DELETE FROM coupons WHERE id=?");
        $st->execute([$id]);
        bildirim_ekle('success','Kupon silindi.');
        header('Location: '.BASE_URL.'/pages/kuponlar.php');
        exit;
    }

    bildirim_ekle('error','Bilinmeyen işlem.');
    header('Location: '.BASE_URL.'/pages/kuponlar.php');
    exit;
}

$code   = trim($_GET['code']   ?? '');
$act    = trim($_GET['active'] ?? '');
$firmQ  = trim($_GET['firm']   ?? '');
$page = max(1, (int)($_GET['p'] ?? 1));$per    = 12;
$offset = ($page - 1) * $per;

$where  = [];
$params = [];

if ($rol === 'firm_admin') {
    $where[]  = '(c.firm_id = ?)';
    $params[] = (int)$u['firm_id'];
} else {
    if ($firmQ !== '') {
        if ($firmQ === 'global') $where[] = '(c.firm_id IS NULL)';
        else { $where[]='(c.firm_id = ?)'; $params[]=(int)$firmQ; }
    }
}
if ($code !== '') { $where[]='(c.code LIKE ?)'; $params[]="%{$code}%"; }
if ($act !== '' && ($act==='0' || $act==='1')) { $where[]='(c.is_active = ?)'; $params[]=(int)$act; }

$whereSql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

$stc = $pdo->prepare("SELECT COUNT(*) FROM coupons c {$whereSql}");
$stc->execute($params);
$total = (int)$stc->fetchColumn();
$totalPages = max(1, (int)ceil($total/$per));
if ($page > $totalPages) { $page=$totalPages; $offset = ($page-1)*$per; }

$sql = "
SELECT
  c.id, c.firm_id, c.code, c.discount_percent, c.discount_cents,
  c.max_uses, c.used_count, c.valid_from, c.valid_until, c.is_active, c.created_at,
  f.name AS firm_name
FROM coupons c
LEFT JOIN firms f ON f.id = c.firm_id
{$whereSql}
ORDER BY c.id DESC
LIMIT :l OFFSET :o
";
$st = $pdo->prepare($sql);
foreach ($params as $i=>$v) $st->bindValue($i+1,$v);
$st->bindValue(':l',$per,PDO::PARAM_INT);
$st->bindValue(':o',$offset,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

$firmalar = [];
if ($rol === 'admin') {
    $firmalar = $pdo->query("SELECT id, name FROM firms WHERE is_active=1 ORDER BY name")->fetchAll();
}
$flash = bildirim_al();

function kq(array $extra): string {
    $q = $_GET;
    foreach ($extra as $k=>$v) { if ($v===null) unset($q[$k]); else $q[$k]=$v; }
    return http_build_query($q);
}
function tl_text($krs): string { return number_format(($krs??0)/100, 2, ',', '.').' TL'; }
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kuponlar</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <?= nav_html(); ?>

  <main class="container" style="padding:28px 0">
    <section class="card">
      <h1>Kuponlar</h1>

      <?php foreach ($flash as $f): ?>
        <div class="alert <?= $f['t']==='success'?'alert-success':'alert-error' ?>"><?= e($f['m']) ?></div>
      <?php endforeach; ?>

      <form method="get" style="margin:12px 0">
        <div class="form-grid-2">
          <label>Kupon kodu <input name="code" value="<?= e($code) ?>" placeholder="örn: INDIRIM"></label>
          <label>Durum
            <select name="active">
              <option value="">Tümü</option>
              <option value="1" <?= $act==='1'?'selected':'' ?>>Aktif</option>
              <option value="0" <?= $act==='0'?'selected':'' ?>>Pasif</option>
            </select>
          </label>
          <?php if ($rol==='admin'): ?>
            <label>Firma
              <select name="firm">
                <option value="">Tümü</option>
                <option value="global" <?= $firmQ==='global'?'selected':'' ?>>Genel</option>
                <?php foreach ($firmalar as $f): ?>
                  <option value="<?= e((string)$f['id']) ?>" <?= $firmQ==(string)$f['id']?'selected':'' ?>><?= e($f['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php else: ?>
            <label>Firma
              <input value="<?= e((string)$u['firm_id']) ?> — sadece bu firmada" disabled>
            </label>
          <?php endif; ?>
        </div>
        <div class="btn-row" style="margin-top:12px">
          <button type="submit">Filtrele</button>
          <a class="btn outline" href="<?= e(BASE_URL) ?>/pages/kuponlar.php">Temizle</a>
        </div>
      </form>

      <hr style="margin:16px 0;opacity:.2">

      <h3>Yeni Kupon Ekle</h3>
      <form method="post" class="form">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="islem" value="ekle">

        <div class="form-grid-2">
          <?php if ($rol==='admin'): ?>
            <label>Kupon kapsamı
              <select name="firm_id">
                <option value="global">Genel (tüm firmalar)</option>
                <?php foreach ($firmalar as $f): ?>
                  <option value="<?= e((string)$f['id']) ?>"><?= e($f['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </label>
          <?php else: ?>
            <label>Firma
              <input value="<?= e((string)$u['firm_id']) ?> — sadece bu firmada" disabled>
            </label>
          <?php endif; ?>

          <label>Kod <input name="code" placeholder="Örn: INDIRIM10" required></label>
          <label>Yüzde (%) — ops. <input type="number" name="discount_percent" min="0" max="100" placeholder="Örn: 10"></label>
          <label>Tutar indirimi (kuruş) — ops. <input type="number" name="discount_cents" min="0" placeholder="Örn: 2500 (25,00 TL)"></label>
          <label>Maks. kullanım — ops. <input type="number" name="max_uses" min="1" placeholder="Boş=sınırsız"></label>
          <label>Başlangıç — ops. <input type="date" name="valid_from"></label>
          <label>Bitiş — ops.     <input type="date" name="valid_until"></label>
          <label>Durum
            <select name="is_active">
              <option value="1" selected>Aktif</option>
              <option value="0">Pasif</option>
            </select>
          </label>
        </div>

        <div class="form-actions"><button type="submit">Kaydet</button></div>
      </form>

      <hr style="margin:16px 0;opacity:.2">

      <div class="small" style="margin:8px 0;color:#64748b">
        Toplam <b><?= e((string)$total) ?></b> — Sayfa <b><?= e((string)$page) ?></b> / <?= e((string)$totalPages) ?>
      </div>

      <div style="overflow:auto">
        <table>
          <thead>
            <tr>
              <th>ID</th><th>Kod</th><th>Firma</th><th>İndirim</th><th>Kullanım</th><th>Tarih</th><th>Durum</th><th>Aksiyonlar</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!$rows): ?>
              <tr><td colspan="8">Kayıt yok.</td></tr>
            <?php else: foreach ($rows as $r):
              $indTxt = $r['discount_percent']!==null ? ((int)$r['discount_percent']).'%' : tl_text($r['discount_cents']);
              $frmTxt = $r['firm_id']===null ? 'Genel' : ($r['firm_name'] ?: ('#'.$r['firm_id']));
              $tar   = [];
              if (!empty($r['valid_from']))  $tar[]='Başla: '.$r['valid_from'];
              if (!empty($r['valid_until'])) $tar[]='Bitir: '.$r['valid_until'];
              $tarTxt = $tar? implode(' / ',$tar) : '—';
              $kullTxt = ($r['used_count'] ?? 0).' / '.($r['max_uses'] ?? '∞');
              $aktif = (int)$r['is_active']===1;
            ?>
              <tr>
                <td><?= e((string)$r['id']) ?></td>
                <td><code><?= e($r['code']) ?></code></td>
                <td><?= e($frmTxt) ?></td>
                <td><?= e($indTxt) ?></td>
                <td><?= e($kullTxt) ?></td>
                <td><?= e($tarTxt) ?></td>
                <td><?= $aktif ? 'Aktif' : 'Pasif' ?></td>
                <td>
                  <div class="table-actions">
                    <form method="post" action="<?= e(BASE_URL) ?>/pages/kuponlar.php">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="islem" value="durum">
                      <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                      <input type="hidden" name="to" value="<?= $aktif? '0':'1' ?>">
                      <button class="btn"><?= $aktif ? 'Pasifleştir' : 'Aktifleştir' ?></button>
                    </form>
                    <form method="post" action="<?= e(BASE_URL) ?>/pages/kuponlar.php" onsubmit="return confirm('Silinsin mi?');">
                      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                      <input type="hidden" name="islem" value="sil">
                      <input type="hidden" name="id" value="<?= e((string)$r['id']) ?>">
                      <button class="btn danger">Sil</button>
                    </form>
                  </div>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($totalPages>1): ?>
      <nav style="margin-top:12px;display:flex;gap:6px;flex-wrap:wrap">
        <?php $prev=$page>1?$page-1:null; $next=$page<$totalPages?$page+1:null; ?>
        <?php if ($prev): ?><a class="btn outline" href="?<?= e(kq(['p'=>$prev])) ?>">‹ Önceki</a><?php endif; ?>
        <?php for($i=max(1,$page-2);$i<=min($totalPages,$page+2);$i++): ?>
          <?php if ($i===$page): ?>
            <span class="btn" style="opacity:.6;cursor:default"><?= e((string)$i) ?></span>
          <?php else: ?>
            <a class="btn outline" href="?<?= e(kq(['p'=>$i])) ?>"><?= e((string)$i) ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($next): ?><a class="btn outline" href="?<?= e(kq(['p'=>$next])) ?>">Sonraki ›</a><?php endif; ?>
      </nav>
      <?php endif; ?>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js" <?= script_nonce_attr(); ?>></script>
</body>
</html>
