<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';

require_login();
$pdo = db();
$u   = current_user();
$id  = (int)($_GET['id'] ?? 0);

$st = $pdo->prepare("
  SELECT k.id, k.user_id, k.seat_no, k.price_cents, k.status, k.purchased_at,
         t.from_city, t.to_city, t.depart_at, t.arrive_at,
         f.name AS firm_name
  FROM tickets k
  JOIN trips t ON t.id=k.trip_id
  JOIN firms f ON f.id=t.firm_id
  WHERE k.id=? LIMIT 1
");
$st->execute([$id]);
$T = $st->fetch();

if (!$T || (int)$T['user_id'] !== (int)$u['id']) {
  http_response_code(404); exit('Bilet bulunamadı.');
}

$tl = function(int $c){ return number_format($c/100,2,',','.'); };

$html = '
<!doctype html><html lang="tr"><head>
<meta charset="utf-8"><style>
  @page { margin:20mm; }
  body{font-family:DejaVu Sans, Arial, sans-serif; font-size:12px;}
  h1{font-size:18px;margin:0 0 10px;}
  .box{border:1px solid #ddd;border-radius:8px;padding:12px;}
  .row{display:flex;gap:18px;margin:8px 0}
  .cell{flex:1}
  .muted{color:#666}
</style></head><body>
  <h1>Bilet</h1>
  <div class="box">
    <div class="row"><div class="cell"><b>Firma:</b> '.htmlspecialchars($T['firm_name']).'</div>
                     <div class="cell"><b>Koltuk:</b> '.(int)$T['seat_no'].'</div></div>
    <div class="row"><div class="cell"><b>Güzergâh:</b> '.htmlspecialchars($T['from_city'].' → '.$T['to_city']).'</div>
                     <div class="cell"><b>Fiyat:</b> '.$tl((int)$T['price_cents']).' TL</div></div>
    <div class="row"><div class="cell"><b>Kalkış:</b> '.htmlspecialchars($T['depart_at']).'</div>
                     <div class="cell"><b>Varış:</b> '.htmlspecialchars((string)($T['arrive_at'] ?? '')).'</div></div>
    <div class="row"><div class="cell"><b>Durum:</b> '.htmlspecialchars($T['status']).'</div>
                     <div class="cell"><b>Satın alma:</b> '.htmlspecialchars((string)$T['purchased_at']).'</div></div>
    <p class="muted">Bilet no: #'.(int)$T['id'].' • Yolcu: '.htmlspecialchars($u['name']).' ('.htmlspecialchars($u['email']).')</p>
  </div>
</body></html>
';

$pdfName = 'bilet-'.(int)$T['id'].'.pdf';

$autoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($autoload)) {
    require $autoload;

    if (class_exists('\Dompdf\Dompdf')) {
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);     
        $options->set('defaultFont', 'DejaVu Sans'); 

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $dompdf->stream($pdfName, ['Attachment'=>true]);
        exit;
    }
}


header('Content-Type: text/html; charset=utf-8');
echo $html, '<script>setTimeout(()=>window.print(),300);</script>';
