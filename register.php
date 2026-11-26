<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) { header('Location: ' . url('dashboard.php')); exit; }

$errors = [];
$old = [
    'nama' => trim((string) ($_POST['nama'] ?? '')),
    'username' => strtolower(trim((string) ($_POST['username'] ?? ''))),
    'alamat' => trim((string) ($_POST['alamat'] ?? '')),
    'no_hp' => trim((string) ($_POST['no_hp'] ?? '')),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirmation = (string) ($_POST['password_confirmation'] ?? '');
    if (mb_strlen($old['nama']) < 2 || mb_strlen($old['nama']) > 100) $errors[] = 'Nama harus berisi 2 sampai 100 karakter.';
    if (!preg_match('/^[a-z0-9_.-]{3,50}$/', $old['username'])) $errors[] = 'Username hanya boleh berisi huruf kecil, angka, titik, garis bawah, atau tanda hubung.';
    if (strlen($password) < 8) $errors[] = 'Password minimal 8 karakter.';
    if ($password !== $confirmation) $errors[] = 'Konfirmasi password tidak sama.';
    if ($old['no_hp'] !== '' && !preg_match('/^[0-9+() .-]{8,20}$/', $old['no_hp'])) $errors[] = 'Nomor HP tidak valid.';

    if (!$errors) {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_users WHERE username = ?');
        $stmt->execute([$old['username']]);
        if ((int) $stmt->fetchColumn() > 0) {
            $errors[] = 'Username sudah digunakan. Silakan pilih username lain.';
        } else {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("INSERT INTO app_users (nama, username, password_hash, role) VALUES (?, ?, ?, 'pelanggan')");
                $stmt->execute([$old['nama'], $old['username'], password_hash($password, PASSWORD_DEFAULT)]);
                $userId = (int) $pdo->lastInsertId();
                $stmt = $pdo->prepare('INSERT INTO pelanggan (user_id, nama_pelanggan, alamat, no_hp) VALUES (?, ?, ?, ?)');
                $stmt->execute([$userId, $old['nama'], $old['alamat'] ?: null, $old['no_hp'] ?: null]);
                $pdo->commit();
                flash('success', 'Registrasi berhasil. Silakan masuk dengan akun pelanggan Anda.');
                header('Location: ' . url('login.php')); exit;
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors[] = 'Registrasi gagal. Pastikan database sudah diperbarui dari database.sql.';
            }
        }
    }
}
?><!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Daftar - <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>"><link rel="stylesheet" href="<?= e(url('assets/theme.css')) ?>"></head>
<body><main class="center-page"><div class="auth-layout"><section class="auth-showcase"><div class="showcase-mark">TK</div><p class="showcase-kicker">TOKOKU POS</p><h1>Belanja lebih praktis.</h1><p>Buat akun pelanggan untuk menikmati proses pembelian yang cepat dan teratur.</p><div class="showcase-line"></div><small>Simple tools for better everyday business.</small></section><section class="auth-card"><div class="brand-mark">TK</div><h1>Buat akun pelanggan</h1><p class="muted">Daftar untuk memiliki akun pelanggan di TokoKu.</p>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><label for="nama">Nama lengkap</label><input id="nama" class="input" name="nama" value="<?= e($old['nama']) ?>" required autofocus>
<label style="margin-top:14px" for="username">Username</label><input id="username" class="input" name="username" value="<?= e($old['username']) ?>" autocomplete="username" required>
<label style="margin-top:14px" for="no_hp">Nomor HP <span class="muted">(opsional)</span></label><input id="no_hp" class="input" name="no_hp" value="<?= e($old['no_hp']) ?>" inputmode="tel">
<label style="margin-top:14px" for="alamat">Alamat <span class="muted">(opsional)</span></label><textarea id="alamat" class="input" name="alamat" rows="2"><?= e($old['alamat']) ?></textarea>
<label style="margin-top:14px" for="password">Password</label><input id="password" class="input" type="password" name="password" autocomplete="new-password" minlength="8" required>
<label style="margin-top:14px" for="password_confirmation">Konfirmasi password</label><input id="password_confirmation" class="input" type="password" name="password_confirmation" autocomplete="new-password" minlength="8" required>
<button class="btn btn-primary" type="submit">Daftar sebagai pelanggan</button></form><p class="auth-footer">Sudah punya akun? <a href="<?= e(url('login.php')) ?>">Masuk</a></p></section></div></main></body></html>
