<?php

require __DIR__ . '/../inc/bootstrap.php';
require __DIR__ . '/../inc/auth.php';


if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}


$_SESSION = [];


if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}


session_destroy();


header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');


header('Location: ' . (BASE_URL . '/pages/login.php'));
exit;
