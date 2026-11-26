<!doctype html>
<html lang="id"><head><meta charset="utf-8"><title>Akses ditolak</title><link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>"></head>
<body><main class="center-page"><section class="auth-card"><div class="brand-mark">TK</div><h1>Akses ditolak</h1><p class="muted">Halaman ini hanya bisa diakses oleh admin atau kasir.</p><a class="btn btn-primary" href="<?= e(url(current_user() && is_customer() ? 'kasir.php' : 'dashboard.php')) ?>">Kembali</a></section></main></body></html>
