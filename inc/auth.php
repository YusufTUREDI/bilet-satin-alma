<?php
declare(strict_types=1);

function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_login(): void {
    if (!current_user()) {
        http_response_code(302);
        $target = BASE_URL . '/login.php';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $q = http_build_query(['r' => $_SERVER['REQUEST_URI']]);
            $target .= '?' . $q;
        }
        header('Location: ' . $target);
        exit;
    }
}

function require_role(string $role): void {
    $u = current_user();
    if (!$u || ($u['role'] ?? '') !== $role) {
        http_response_code(403);
        exit('Erişim engellendi.');
    }
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check(string $token): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(400);
        exit('Geçersiz CSRF token.');
    }
}

function is_logged_in(): bool {
    return current_user() !== null;
}

function redirect_back_or(string $fallback): void {
    $r = $_GET['r'] ?? $_POST['r'] ?? null;
    $url = $r && is_string($r) ? $r : $fallback;
    header('Location: ' . $url);
    exit;
}
