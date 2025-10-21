<?php
require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';

require_login();
$u   = current_user();
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Yöntem desteklenmiyor');
}

csrf_check($_POST['csrf'] ?? '');
$ticket_id = (int)($_POST['ticket_id'] ?? 0);

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare("
      SELECT k.id, k.user_id, k.seat_no, k.price_cents, k.status, k.coupon_id,
             t.depart_at
      FROM tickets k
      JOIN trips t ON t.id=k.trip_id
      WHERE k.id=? AND k.user_id=?
      LIMIT 1
    ");
    $st->execute([$ticket_id, (int)$u['id']]);
    $k = $st->fetch();

    if (!$k) throw new RuntimeException('Bilet bulunamadı.');
    if ((string)$k['status'] !== 'purchased') throw new RuntimeException('Bu bilet iptal edilemez.');

    $now = new DateTime('now');
    $dep = new DateTime($k['depart_at']);
    if ($dep <= (clone $now)->modify('+0 minute')) throw new RuntimeException('Kalkış saati geçen bilet iptal edilemez.');
    if ($dep <= (clone $now)->modify('+60 minute')) throw new RuntimeException('İptal için geç kaldınız (kalkışa < 1 saat).');

    $price     = (int)$k['price_cents'];
    $coupon_id = $k['coupon_id'] !== null ? (int)$k['coupon_id'] : null;

    $pdo->prepare("
      UPDATE tickets
         SET status='cancelled',
             cancelled_at=datetime('now'),
             seat_no = -ABS(seat_no)
       WHERE id=? AND status='purchased'
    ")->execute([$ticket_id]);

    $pdo->prepare("UPDATE users SET credit_cents = credit_cents + ? WHERE id=?")
        ->execute([$price, (int)$u['id']]);

    if (!is_null($coupon_id)) {
        $pdo->prepare("
          UPDATE coupons
             SET used_count = CASE WHEN used_count>0 THEN used_count-1 ELSE 0 END
           WHERE id=?
        ")->execute([$coupon_id]);
    }

    $pdo->prepare("
      INSERT INTO wallet_tx (user_id, type, amount_cents, ref_ticket_id, note, created_at)
      VALUES (?, 'refund', ?, ?, 'Bilet iptali iadesi', datetime('now'))
    ")->execute([(int)$u['id'], $price, $ticket_id]);

    $pdo->commit();
    $_SESSION['flash'][] = ['t'=>'success','m'=>'Bilet iptal edildi, tutar cüzdana iade edildi.'];
    header('Location: ' . (BASE_URL . '/pages/biletlerim.php'));
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash'][] = ['t'=>'error','m'=>$e->getMessage()];
    header('Location: ' . (BASE_URL . '/pages/biletlerim.php'));
    exit;
}
