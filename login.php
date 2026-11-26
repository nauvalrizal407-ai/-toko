<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { header('Location: ' . url('dashboard.php')); exit; }
$error = '';
$flashMessages = take_flash();
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
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Masuk - <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>"><link rel="stylesheet" href="<?= e(url('assets/theme.css')) ?>"></head>
<body><main class="center-page"><div class="auth-layout"><section class="auth-showcase"><div class="showcase-mark">TK</div><p class="showcase-kicker">TOKOKU POS</p><h1>Kelola toko dengan lebih sederhana.</h1><p>Penjualan, stok, dan pelanggan dalam satu ruang kerja yang rapi.</p><div class="showcase-line"></div><small>One workspace for your daily store operation.</small></section><section class="auth-card"><div class="brand-mark">TK</div><h1>Selamat datang</h1><p class="muted">Masuk untuk mengelola penjualan toko.</p>
<?php foreach ($flashMessages as $flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label>Username</label><input class="input" name="username" autocomplete="username" required autofocus><label style="margin-top:14px">Password</label><input class="input" type="password" name="password" autocomplete="current-password" required><button class="btn btn-primary" type="submit">Masuk ke aplikasi</button></form>
<p class="auth-footer">Belum punya akun pelanggan? <a href="<?= e(url('register.php')) ?>">Daftar sekarang</a></p><p class="auth-footer">TokoKu POS - Sistem kasir sederhana</p></section></div></main></body></html>
