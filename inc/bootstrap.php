<?php declare(strict_types=1);

const APP_ENV = 'dev';
if (APP_ENV === 'dev') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_STRICT & ~E_DEPRECATED);
}

date_default_timezone_set('Europe/Istanbul');


header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

$cspnonce = base64_encode(random_bytes(16));
header(
    "Content-Security-Policy: " .
    "default-src 'self'; " .
    "script-src 'self' 'nonce-{$cspnonce}'; " .
    "style-src 'self'; " .
    "img-src 'self' data:; " .
    "object-src 'none'; " .
    "base-uri 'self'; " .
    "form-action 'self'; " .
    "frame-ancestors 'none'"
);


$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',  
    'domain'   => '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}



if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}



$scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
if (substr($scriptDir, -6) === '/pages') {
    $scriptDir = substr($scriptDir, 0, -6);
}
if (!defined('BASE_URL')) {
    define('BASE_URL', $scriptDir ?: '');
}


if (!defined('PAGES_PATH')) {
    define('PAGES_PATH', BASE_PATH . '/pages');
}
if (!defined('PAGES_URL')) {
    define('PAGES_URL', BASE_URL . '/pages');
}


require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/helpers.php';

$GLOBALS['CSP_NONCE'] = $cspnonce;
