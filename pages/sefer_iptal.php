<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';

require_login();
require_role('firm_admin');

$pdo = db();
$u   = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method Not Allowed');
}

csrf_check($_POST['csrf'] ?? '');
$trip_id = (int)($_POST['id'] ?? 0);

try {
    if ($trip_id <= 0) throw new RuntimeException('Geçersiz sefer ID.');

    $pdo->beginTransaction();

    $st = $pdo->prepare("SELECT id, firm_id, depart_at, status FROM trips WHERE id=?");
    $st->execute([$trip_id]);
    $t = $st->fetch();
    if (!$t) throw new RuntimeException('Sefer bulunamadı.');
    if ((int)$t['firm_id'] !== (int)$u['firm_id']) throw new RuntimeException('Bu sefer size ait değil.');
    if ($t['status'] !== 'active') throw new RuntimeException('Sefer zaten iptal/pasif.');

    $now = new DateTime('now');
    $dep = new DateTime($t['depart_at']);
    if ($dep <= $now) throw new RuntimeException('Kalkış saati geçmiş sefer iptal edilemez.');

   

    $pdo->commit();
    $_SESSION['flash'][] = ['t'=>'success','m'=>'Sefer iptal edildi.'];
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'][] = ['t'=>'error','m'=>$e->getMessage()];
}

header('Location: ' . (BASE_URL . '/pages/firma_sefer_listesi.php'));
exit;
