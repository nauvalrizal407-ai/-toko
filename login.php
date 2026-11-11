<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { header('Location: ' . url('dashboard.php')); exit; }
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $username = trim($_POST['username'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, nama, username, password_hash, role FROM app_users WHERE username = ? AND aktif = 1 LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        unset($user['password_hash']);
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        log_activity('login', 'Masuk ke dalam aplikasi');
        header('Location: ' . url('dashboard.php')); exit;
    }
    $error = 'Username atau password salah.';
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk · <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>"></head><body><main class="center-page"><section class="auth-card"><div class="brand-mark">TK</div><h1>Selamat datang</h1><p class="muted">Masuk untuk mengelola penjualan toko.</p><?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Username</label><input class="input" name="username" autocomplete="username" required autofocus><label style="margin-top:14px">Password</label><input class="input" type="password" name="password" autocomplete="current-password" required><button class="btn btn-primary" type="submit">Masuk ke aplikasi</button></form><p class="auth-footer">TokoKu POS · Sistem kasir sederhana</p></section></main></body></html>
