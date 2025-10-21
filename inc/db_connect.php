<?php
declare(strict_types=1);

if (!function_exists('db')) {
    function db(): PDO {
        static $pdo = null;
        if ($pdo instanceof PDO) return $pdo;

        $dbPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'bilet.sqlite';
        $dir = dirname($dbPath);
        if (!is_dir($dir)) { mkdir($dir, 0777, true); }

        $pdo = new PDO('sqlite:' . $dbPath, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }
}

if (!function_exists('db_healthcheck')) {
    function db_healthcheck(): array {
        $pdo  = db();
        $info = $pdo->query('PRAGMA database_list')->fetchAll();
        $path = $info[0]['file'] ?? '';
        $ver  = $pdo->query('SELECT sqlite_version() AS v')->fetch()['v'] ?? 'unknown';
        return [
            'ok'             => true,
            'sqlite_version' => $ver,
            'db_file'        => $path,
            'wal_mode'       => 'WAL',
        ];
    }
}
