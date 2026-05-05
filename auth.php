<?php
// includes/auth.php

require_once __DIR__ . '/database.php';

function session_start_safe(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

function current_user(): ?array {
    session_start_safe();
    if (empty($_SESSION['user_id'])) return null;
    return db_get("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function require_login(): array {
    $user = current_user();
    if (!$user) {
        header('Location: /index.php?page=login');
        exit;
    }
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        header('Location: /index.php?page=dashboard');
        exit;
    }
    return $user;
}

function login(string $username, string $password): bool {
    $user = db_get("SELECT * FROM users WHERE username = ?", [trim($username)]);
    if (!$user || !password_verify($password, $user['password'])) return false;
    session_start_safe();
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    db_exec("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?", [$user['id']]);
    return true;
}

function logout(): void {
    session_start_safe();
    session_destroy();
    header('Location: /index.php?page=login');
    exit;
}

function csrf_token(): string {
    session_start_safe();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    if (($_POST['csrf'] ?? '') !== csrf_token()) {
        http_response_code(403);
        die(json_encode(['error' => 'Invalid request']));
    }
}

function uid(): int {
    return (int)($_SESSION['user_id'] ?? 0);
}

function fmt_money(float $amount, string $currency = '₹'): string {
    return $currency . number_format(abs($amount), 2);
}

function safe(mixed $v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
