<?php
declare(strict_types=1);

function initialize_database(PDO $pdo): void
{
    error_log("Otomatik veritabanı kurulum fonksiyonu (initialize_database) başlatıldı.");

    $pdo->beginTransaction();
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS firms (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                name       TEXT    NOT NULL UNIQUE,
                phone      TEXT,
                email      TEXT,
                is_active  INTEGER NOT NULL DEFAULT 1,
                created_at TEXT    NOT NULL DEFAULT (datetime('now', 'localtime'))
            );
        ");
        error_log(" - 'firms' tablosu oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                firm_id       INTEGER,
                name          TEXT    NOT NULL,
                email         TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                role          TEXT    NOT NULL CHECK (role IN ('admin','firm_admin','user')),
                credit_cents  INTEGER NOT NULL DEFAULT 0,
                is_active     INTEGER NOT NULL DEFAULT 1,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
                FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE SET NULL
            );
        ");
        error_log(" - 'users' tablosu oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS trips (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                firm_id     INTEGER NOT NULL,
                from_city   TEXT    NOT NULL,
                to_city     TEXT    NOT NULL,
                depart_at   TEXT    NOT NULL,
                arrive_at   TEXT,
                price_cents INTEGER NOT NULL CHECK (price_cents >= 0),
                seat_count  INTEGER NOT NULL CHECK (seat_count > 0),
                status      TEXT    NOT NULL DEFAULT 'active' CHECK (status IN ('active','cancelled','past')),
                created_at  TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
                FOREIGN KEY (firm_id) REFERENCES firms(id) ON DELETE CASCADE
            );
        ");
        error_log(" - 'trips' tablosu oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS coupons (
                id               INTEGER PRIMARY KEY AUTOINCREMENT,
                firm_id          INTEGER,
                code             TEXT    NOT NULL,
                discount_percent INTEGER CHECK (discount_percent BETWEEN 0 AND 100),
                discount_cents   INTEGER CHECK (discount_cents >= 0),
                max_uses         INTEGER CHECK (max_uses IS NULL OR max_uses >= 1),
                used_count       INTEGER NOT NULL DEFAULT 0,
                valid_from       TEXT,
                valid_until      TEXT,
                is_active        INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
                created_at       TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
                FOREIGN KEY(firm_id) REFERENCES firms(id) ON DELETE CASCADE,
                UNIQUE (code, firm_id),
                CHECK (discount_percent IS NOT NULL OR discount_cents IS NOT NULL)
            );
        ");
        error_log(" - 'coupons' tablosu oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS tickets (
              id            INTEGER PRIMARY KEY AUTOINCREMENT,
              user_id       INTEGER NOT NULL,
              trip_id       INTEGER NOT NULL,
              seat_no       INTEGER NOT NULL,
              price_cents   INTEGER NOT NULL CHECK (price_cents >= 0),
              coupon_id     INTEGER,
              status        TEXT    NOT NULL CHECK (status IN ('purchased', 'cancelled')),
              purchased_at  TEXT    DEFAULT (datetime('now', 'localtime')),
              cancelled_at  TEXT,
              FOREIGN KEY(user_id)   REFERENCES users(id)   ON DELETE CASCADE,
              FOREIGN KEY(trip_id)   REFERENCES trips(id)   ON DELETE CASCADE,
              FOREIGN KEY(coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
            );
        ");
        error_log(" - 'tickets' tablosu oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE UNIQUE INDEX IF NOT EXISTS ux_tickets_trip_seat_active
            ON tickets(trip_id, seat_no)
            WHERE status='purchased';
        ");
        error_log(" - 'ux_tickets_trip_seat_active' index'i oluşturuldu veya zaten vardı.");

        $pdo->exec("
            CREATE TABLE IF NOT EXISTS wallet_tx (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id       INTEGER NOT NULL,
                type          TEXT    NOT NULL CHECK (type IN ('topup','purchase','refund','adjust')),
                amount_cents  INTEGER NOT NULL,
                ref_ticket_id INTEGER,
                note          TEXT,
                created_at    TEXT    NOT NULL DEFAULT (datetime('now', 'localtime')),
                FOREIGN KEY (user_id)       REFERENCES users(id)   ON DELETE CASCADE,
                FOREIGN KEY (ref_ticket_id) REFERENCES tickets(id) ON DELETE SET NULL
            );
        ");
        error_log(" - 'wallet_tx' tablosu oluşturuldu veya zaten vardı.");

        $adminEmail = 'admin@gmail.com';
        $checkStmt = $pdo->prepare("SELECT 1 FROM users WHERE email = ? LIMIT 1");
        $checkStmt->execute([$adminEmail]);

        if (!$checkStmt->fetchColumn()) {
            error_log("Varsayılan admin kullanıcısı bulunamadı, oluşturuluyor...");
            $adminPasswordHash = password_hash('password', PASSWORD_DEFAULT);
            $insertStmt = $pdo->prepare("
                INSERT INTO users (name, email, password_hash, role, is_active, credit_cents)
                VALUES ('Site Yöneticisi', ?, ?, 'admin', 1, 0)
            ");
            $insertStmt->execute([$adminEmail, $adminPasswordHash]);
            error_log("Varsayılan admin kullanıcısı başarıyla oluşturuldu.");
        } else {
            error_log("Varsayılan admin kullanıcısı zaten mevcut.");
        }

        $pdo->commit();
        error_log("Veritabanı kurulumu başarıyla commit edildi.");

    } catch (\PDOException $e) {
        $pdo->rollBack();
        error_log("Veritabanı kurulumu BAŞARISIZ! Hata: " . $e->getMessage());
        throw $e;
    }
}