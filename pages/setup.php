<?php
require dirname(__DIR__) . '/inc/bootstrap.php';
require dirname(__DIR__) . '/inc/auth.php';     
require dirname(__DIR__) . '/inc/nav.php'; echo nav_html();

if (APP_ENV !== 'dev') {
    http_response_code(403);
    exit;
}

$pdo = db();
$pdo->exec('PRAGMA foreign_keys = ON');
$pdo->exec('PRAGMA journal_mode = WAL');

function table_exists(PDO $pdo, string $name): bool {
    $st = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name=?");
    $st->execute([$name]);
    return (bool)$st->fetchColumn();
}

$migrations = [
    "CREATE TABLE IF NOT EXISTS firms (
        id         INTEGER PRIMARY KEY AUTOINCREMENT,
        name       TEXT    NOT NULL UNIQUE,
        phone      TEXT,
        email      TEXT,
        is_active  INTEGER NOT NULL DEFAULT 1,
        created_at TEXT    NOT NULL DEFAULT (datetime('now'))
    )",

    "CREATE TABLE IF NOT EXISTS users (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        firm_id       INTEGER,
        name          TEXT    NOT NULL,
        email         TEXT    NOT NULL UNIQUE,
        password_hash TEXT    NOT NULL,
        role          TEXT    NOT NULL CHECK (role IN ('admin','firm_admin','user')),
        credit_cents  INTEGER NOT NULL DEFAULT 0,
        is_active     INTEGER NOT NULL DEFAULT 1,
        created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS trips (
        id          INTEGER PRIMARY KEY AUTOINCREMENT,
        firm_id     INTEGER NOT NULL,
        from_city   TEXT    NOT NULL,
        to_city     TEXT    NOT NULL,
        depart_at   TEXT    NOT NULL,
        arrive_at   TEXT,
        price_cents INTEGER NOT NULL,
        seat_count  INTEGER NOT NULL CHECK (seat_count > 0),
        status      TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','cancelled','past')),
        created_at  TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE CASCADE
    )",

    "CREATE TABLE IF NOT EXISTS coupons (
    id               INTEGER PRIMARY KEY AUTOINCREMENT,
    firm_id          INTEGER,
    code             TEXT    NOT NULL UNIQUE,
    discount_percent INTEGER CHECK (discount_percent BETWEEN 0 AND 100),
    discount_cents   INTEGER CHECK (discount_cents >= 0),
    max_uses         INTEGER,
    used_count       INTEGER NOT NULL DEFAULT 0,
    valid_from       TEXT,
    valid_until      TEXT,
    is_active        INTEGER NOT NULL DEFAULT 1,
    created_at       TEXT    NOT NULL DEFAULT (datetime('now')),
    CHECK (discount_percent IS NOT NULL OR discount_cents IS NOT NULL),
    FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE SET NULL
)",

    "CREATE TABLE IF NOT EXISTS tickets (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL,
        trip_id       INTEGER NOT NULL,
        seat_no       INTEGER NOT NULL,
        price_cents   INTEGER NOT NULL,
        coupon_id     INTEGER,
        status        TEXT    NOT NULL DEFAULT 'purchased' CHECK (status IN ('purchased','cancelled')),
        purchased_at  TEXT    NOT NULL DEFAULT (datetime('now')),
        cancelled_at  TEXT,
        UNIQUE (trip_id, seat_no),
        FOREIGN KEY (user_id)  REFERENCES users(id)   ON DELETE CASCADE,
        FOREIGN KEY (trip_id)  REFERENCES trips(id)   ON DELETE CASCADE,
        FOREIGN KEY (coupon_id)REFERENCES coupons(id) ON DELETE SET NULL
    )",

    "CREATE TABLE IF NOT EXISTS wallet_tx (
        id            INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id       INTEGER NOT NULL,
        type          TEXT    NOT NULL CHECK (type IN ('topup','purchase','refund','adjust')),
        amount_cents  INTEGER NOT NULL,
        ref_ticket_id INTEGER,
        note          TEXT,
        created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
        FOREIGN KEY (user_id)       REFERENCES users(id)   ON DELETE CASCADE,
        FOREIGN KEY (ref_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
    )",
];

$ran = isset($_GET['run']) && $_GET['run'] === '1';
$result = ['migrated' => false, 'counts' => []];

if ($ran) {
    $pdo->beginTransaction();
    try {
        foreach ($migrations as $sql) {
            $pdo->exec($sql);
        }
        $result['migrated'] = true;

        foreach (['firms','users','trips','coupons','tickets','wallet_tx'] as $tbl) {
            if (table_exists($pdo,$tbl)) {
                $result['counts'][$tbl] = (int)$pdo->query("SELECT COUNT(*) FROM {$tbl}")->fetchColumn();
            }
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        http_response_code(500);
        echo "Kurulum hatası: " . e($e->getMessage());
        exit;
    }
}
?>
<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Kurulum</title>
  <link rel="stylesheet" href="<?= e(BASE_URL) ?>/css/styles.css">
</head>
<body>
  <main class="container" style="padding:28px 0">
    <section class="card">
      <h1>Kurulum</h1>
      <?php if (!$ran): ?>
        <p>Tabloları oluşturmak için:</p>
        <p><code><?= e(BASE_URL) ?>/setup.php?run=1</code></p>
      <?php else: ?>
        <ul>
          <li>Migrasyon: <b><?= $result['migrated'] ? 'Tamam' : 'Atlandı' ?></b></li>
        </ul>
        <?php if ($result['counts']): ?>
          <h3>Tablo Sayıları</h3>
          <ul>
            <?php foreach ($result['counts'] as $t => $c): ?>
              <li><?= e($t) ?>: <b><?= e((string)$c) ?></b></li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
        <p><a class="brand" href="<?= e(BASE_URL) ?>/">Ana sayfa</a></p>
      <?php endif; ?>
    </section>
  </main>
  <script src="<?= e(BASE_URL) ?>/js/app.js"<?= script_nonce_attr(); ?>></script>
</body>
</html>
