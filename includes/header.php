<?php
require_once __DIR__ . '/auth.php';
require_login();
$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$flashMessages = take_flash();
$cartItems = $_SESSION['cart'] ?? [];
$cartCount = 0;
$cartTotal = 0;
foreach ($cartItems as $cartItem) {
    $cartCount += (int) ($cartItem['qty'] ?? 0);
    $cartTotal += (float) ($cartItem['harga'] ?? 0) * (int) ($cartItem['qty'] ?? 0);
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('assets/theme.css')) ?>">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <a class="brand" href="<?= e(url('dashboard.php')) ?>"><span class="brand-mark">TK</span><span><strong>TokoKu</strong></span></a>
        <div class="user-chip"><div class="avatar"><?= e(strtoupper(substr($user['nama'], 0, 1))) ?></div><div><strong><?= e($user['nama']) ?></strong><small><?= e(ucfirst($user['role'])) ?></small></div></div>
        <nav>
            <small class="nav-label">MENU UTAMA</small>
            <a class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>" href="<?= e(url('dashboard.php')) ?>"><span>▦</span> Dashboard</a>
            <a class="nav-link <?= $activePage === 'kasir' ? 'active' : '' ?>" href="<?= e(url('kasir.php')) ?>"><span>▣</span> Kasir</a>
            <a class="nav-link <?= $activePage === 'barang' ? 'active' : '' ?>" href="<?= e(url('barang.php')) ?>"><span>▤</span> Data Barang</a>
            <a class="nav-link <?= $activePage === 'transaksi' ? 'active' : '' ?>" href="<?= e(url('transaksi.php')) ?>"><span>◷</span> Transaksi</a>
            <?php if (is_admin()): ?>
                <small class="nav-label">ADMINISTRASI</small>
                <a class="nav-link <?= $activePage === 'laporan' ? 'active' : '' ?>" href="<?= e(url('laporan.php')) ?>"><span>▥</span> Laporan</a>
                <a class="nav-link <?= $activePage === 'log' ? 'active' : '' ?>" href="<?= e(url('log.php')) ?>"><span>◌</span> Log Aktivitas</a>
            <?php endif; ?>
        </nav>
        <a class="logout-link" href="<?= e(url('logout.php')) ?>"><span>↪</span> Keluar</a>
    </aside>
    <main class="main-content">
        <header class="topbar"><div><p class="eyebrow"><?= e(date('l, d F Y')) ?></p><h1><?= e($pageTitle) ?></h1></div><div class="topbar-actions"><details class="cart-menu"><summary class="cart-trigger" title="Lihat keranjang"><span class="cart-icon">🛒</span><?php if ($cartCount > 0): ?><span class="cart-count"><?= e($cartCount) ?></span><?php endif; ?></summary><div class="cart-popover"><div class="cart-popover-head"><strong>Keranjang</strong><span class="muted small"><?= e($cartCount) ?> item</span></div><?php if ($cartItems): ?><div class="cart-popover-items"><?php foreach ($cartItems as $cartItem): ?><div class="cart-popover-item"><div><strong><?= e($cartItem['nama']) ?></strong><small><?= e(money($cartItem['harga'])) ?> × <?= e($cartItem['qty']) ?></small></div><strong><?= e(money((float) $cartItem['harga'] * (int) $cartItem['qty'])) ?></strong></div><?php endforeach; ?></div><div class="cart-popover-total"><span>Total</span><strong><?= e(money($cartTotal)) ?></strong></div><form class="cart-quick-form" method="post" action="<?= e(url('kasir.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update"><?php foreach ($cartItems as $cartItem): ?><label><span><?= e($cartItem['nama']) ?></span><input type="number" min="0" name="qty[<?= e($cartItem['id']) ?>]" value="<?= e($cartItem['qty']) ?>"></label><?php endforeach; ?><button class="btn btn-light btn-sm" type="submit">Perbarui jumlah</button></form><form class="cart-quick-form" method="post" action="<?= e(url('kasir.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="checkout"><label>Bayar</label><input class="input" type="number" min="<?= e($cartTotal) ?>" name="bayar" required placeholder="Nominal pembayaran"><button class="btn btn-primary cart-open-btn" type="submit">Bayar sekarang</button></form><form method="post" action="<?= e(url('kasir.php')) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="clear"><button class="btn btn-danger btn-sm" type="submit">Kosongkan keranjang</button></form><?php else: ?><div class="cart-empty">Keranjang masih kosong.</div><?php endif; ?><a class="btn btn-light cart-open-btn" href="<?= e(url('kasir.php')) ?>">Buka halaman kasir</a></div></details><div class="topbar-user"><?= e($user['nama']) ?><span class="role-pill <?= e($user['role']) ?>"><?= e(ucfirst($user['role'])) ?></span></div></div></header>
        <?php foreach ($flashMessages as $flash): ?><div class="alert <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endforeach; ?>
