<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function csrf_token(): string
{
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function check_csrf(): void
{
    $token = $_POST['_csrf'] ?? '';
    if (!hash_equals($_SESSION['_csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Token keamanan tidak valid. Silakan kembali dan coba lagi.');
    }
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}

function require_login(): void
{
    if (!current_user()) {
        header('Location: ' . url('login.php'));
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        include __DIR__ . '/403.php';
        exit;
    }
}

function log_activity(string $action, string $description = ''): void
{
    $user = current_user();
    if (!$user) {
        return;
    }
    $stmt = db()->prepare('INSERT INTO app_log_kasir (user_id, aksi, keterangan) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], $action, $description]);
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function take_flash(): array
{
    $messages = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return $messages;
}
