<?php
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function script_nonce_attr(): string {
    $nonce = $GLOBALS['CSP_NONCE'] ?? '';
    return 'nonce="' . e($nonce) . '"';
}
function redirect(string $path): void {
   header('Location: ' . $path, true, 302);
    exit;
}
function bildirim_ekle(string $tur, string $mesaj): void {
    if (!isset($_SESSION['flash']) || !is_array($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = ['t' => $tur, 'm' => $mesaj];
}

function bildirim_al(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}