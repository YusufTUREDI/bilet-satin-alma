<?php
declare(strict_types=1);

require_once __DIR__ . '/db_setup.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'bilet.sqlite';
    $dbDir = dirname($dbPath);
    $needsSetup = !is_file($dbPath);

    if (!is_dir($dbDir)) {
        error_log("Veritabanı dizini mevcut değil, oluşturulmaya çalışılıyor: " . $dbDir);
        if (!@mkdir($dbDir, 0775, true) && !is_dir($dbDir)) {
            error_log("!!! KRİTİK HATA: Veritabanı dizini oluşturulamadı: " . $dbDir . " - Web sunucusunun bu dizine yazma izni olduğundan emin olun!");
            throw new \RuntimeException(sprintf('Veritabanı dizini "%s" oluşturulamadı. Lütfen klasör izinlerini kontrol edin.', $dbDir));
        }
        $needsSetup = true;
        error_log("Veritabanı dizini başarıyla oluşturuldu: " . $dbDir);
    }

    try {
        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        error_log("PDO bağlantısı başarıyla oluşturuldu: " . $dbPath);

        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->exec('PRAGMA journal_mode = WAL;');
        $pdo->exec('PRAGMA busy_timeout = 5000;');

        if (!$needsSetup) {
            $checkTableStmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users' LIMIT 1");
            if ($checkTableStmt->fetchColumn() === false) {
                error_log("Veritabanı dosyası bulundu ancak 'users' tablosu eksik. Otomatik kurulum tetikleniyor.");
                $needsSetup = true;
            } else {
                error_log("Veritabanı ve 'users' tablosu mevcut görünüyor.");
            }
        }

        if ($needsSetup) {
            error_log("Kurulum gerekli. initialize_database() fonksiyonu çağrılıyor...");
            initialize_database($pdo);
        }

    } catch (\PDOException $e) {
        error_log("!!! KRİTİK VERİTABANI HATASI (PDOException): " . $e->getMessage() . " (Dosya: " . $e->getFile() . ", Satır: " . $e->getLine() . ")");
        exit("Veritabanı hatası oluştu. Lütfen sistem yöneticisi ile iletişime geçin.");
    } catch (\Throwable $e) {
        error_log("!!! BEKLENMEDİK HATA (db_connect): " . $e->getMessage() . " (Dosya: " . $e->getFile() . ", Satır: " . $e->getLine() . ")");
        exit("Beklenmedik bir sunucu hatası oluştu.");
    }

    return $pdo;
}

if (!function_exists('db_healthcheck')) {
    function db_healthcheck(): array
    {
        $result = [
            'ok' => false,
            'status' => 'Not connected',
            'sqlite_version' => null,
            'db_file' => null,
            'journal_mode' => null,
            'foreign_keys' => null,
            'tables_checked' => false,
            'error' => null,
        ];
        try {
            $pdo = db();
            $result['ok'] = true;
            $result['status'] = 'Connected';

            $info = $pdo->query('PRAGMA database_list')->fetchAll();
            $result['db_file'] = $info[0]['file'] ?? 'In-memory or temporary';
            $result['sqlite_version'] = $pdo->query('SELECT sqlite_version() AS v')->fetchColumn() ?? 'unknown';
            $result['journal_mode'] = strtoupper((string)($pdo->query('PRAGMA journal_mode')->fetchColumn() ?? 'unknown'));
            $result['foreign_keys'] = $pdo->query('PRAGMA foreign_keys')->fetchColumn() == 1 ? 'ON' : 'OFF';

            $requiredTables = ['users', 'firms', 'trips', 'tickets', 'coupons', 'wallet_tx'];
            $missingTables = [];
            foreach ($requiredTables as $table) {
                $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table' LIMIT 1");
                if ($stmt->fetchColumn() === false) {
                    $missingTables[] = $table;
                }
            }
            $result['tables_checked'] = true;
            if (empty($missingTables)) {
                $result['status'] .= ' and all required tables exist.';
            } else {
                $result['status'] .= ' but required tables are MISSING: ' . implode(', ', $missingTables);
                $result['ok'] = false;
            }

        } catch (\Throwable $e) {
            error_log("db_healthcheck error: " . $e->getMessage());
            $result['ok'] = false;
            $result['status'] = 'Connection or setup FAILED';
            $result['error'] = $e->getMessage();
        }
        return $result;
    }
}
