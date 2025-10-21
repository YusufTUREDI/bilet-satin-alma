<?php
require_once __DIR__ . '/auth.php';

function nav_html(): string {
    $u    = current_user();
    $role = $u['role'] ?? null;

    ob_start(); ?>
    <header class="site-header">
      <div class="container">
        <nav class="top">
          <a class="chip" href="<?= e(BASE_URL) ?>/">Ana sayfa</a>

          <?php if ($u): ?>
            <?php if ($role === 'admin'): ?>
              <a class="chip" href="<?= e(PAGES_URL) ?>/admin_firma_adminleri.php">Firma yöneticileri</a>
              <a class="chip" href="<?= e(PAGES_URL) ?>/firmalar.php">Firmalar</a>
              <a class="chip" href="<?= e(PAGES_URL) ?>/kullanici_listesi.php">Kullanıcılar</a>
              <a class="chip" href="<?= e(PAGES_URL) ?>/kuponlar.php">Kuponlar</a>
            <?php elseif ($role === 'firm_admin'): ?>
              <a class="chip" href="<?= e(PAGES_URL) ?>/firma_sefer_listesi.php">Seferlerim</a>
              <a class="chip primary" href="<?= e(PAGES_URL) ?>/firma_sefer_ekle.php">Yeni sefer ekle</a>
              <a class="chip" href="<?= e(PAGES_URL) ?>/kuponlar.php">Kuponlar</a>
            <?php else: ?>
              <a class="chip" href="<?= e(PAGES_URL) ?>/bilet_al.php">Bilet ara</a>
              <a class="chip" href="<?= e(PAGES_URL) ?>/biletlerim.php">Biletlerim</a>
            <?php endif; ?>

            <span class="spacer"></span>

            <?php if ($role === 'user' && isset($u['id'])):
              $pdo = db();
              $st = $pdo->prepare('SELECT credit_cents FROM users WHERE id=?');
              $st->execute([(int)$u['id']]);
              $cc = (int)$st->fetchColumn();
              $cc_tl = number_format($cc/100, 2, ',', '.');
            ?>
              <span class="chip muted">Cüzdan: <?= e($cc_tl) ?> TL</span>
            <?php endif; ?>

            <a class="chip" href="<?= e(PAGES_URL) ?>/profil.php">Profilim</a>
            <a class="chip danger" href="<?= e(PAGES_URL) ?>/cikis.php">Çıkış</a>

          <?php else: ?>
            <a class="chip" href="<?= e(PAGES_URL) ?>/bilet_al.php">Bilet ara</a>
            <span class="spacer"></span>
            <a class="chip primary" href="<?= e(PAGES_URL) ?>/login.php">Giriş yap</a>
            <a class="chip" href="<?= e(PAGES_URL) ?>/register.php">Kayıt ol</a>
          <?php endif; ?>
        </nav>
      </div>
    </header>
    <?php
    return ob_get_clean();
}
