<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
if (!is_customer()) {
    header('Location: ' . url('transaksi.php'));
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT t.id,t.nomor_transaksi,t.total,t.payment_method,t.status,t.created_at,t.paid_at,t.gateway_payment_url FROM app_transaksi t WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT 50");
$stmt->execute([(int) current_user()['id']]);
$orders = $stmt->fetchAll();
$showSuccess = ($_GET['result'] ?? '') === 'success';
$showPending = ($_GET['result'] ?? '') === 'pending';
$pageTitle = 'Riwayat Pembelian';
$activePage = 'riwayat';
include __DIR__ . '/includes/header.php';
?>
<section class="card history-card">
    <div class="section-head"><div><h2>Riwayat pembelian</h2><p class="muted small">Daftar transaksi pembelian Anda.</p></div><a class="btn btn-primary" href="<?= e(url('kasir.php')) ?>">Belanja lagi</a></div>
    <div class="table-wrap"><table class="table"><thead><tr><th>Transaksi</th><th>Tanggal</th><th>Total</th><th>Metode</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?><tr><td><strong><?= e($order['nomor_transaksi']) ?></strong></td><td><?= e(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td><td class="amount"><?= e(money($order['total'])) ?></td><td><?= e(strtoupper($order['payment_method'])) ?></td><td><span class="badge <?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td><td><?php if ($order['status'] === 'unpaid' && $order['gateway_payment_url']): ?><a class="btn btn-light btn-sm" href="<?= e($order['gateway_payment_url']) ?>">Lanjut bayar</a><?php else: ?><span class="muted small"><?= $order['paid_at'] ? 'Selesai' : '-' ?></span><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$orders): ?><tr><td colspan="6" class="empty">Belum ada riwayat pembelian.</td></tr><?php endif; ?></tbody></table></div>
</section>
<?php if ($showSuccess || $showPending): ?><div class="payment-modal-backdrop" id="payment-success-modal"><div class="payment-modal" role="dialog" aria-modal="true"><div class="payment-modal-icon <?= $showSuccess ? 'success' : 'pending' ?>"><?= $showSuccess ? '✓' : '!' ?></div><h2><?= $showSuccess ? 'Pembayaran berhasil' : 'Pembayaran sedang diproses' ?></h2><p><?= $showSuccess ? 'Pesanan Anda sudah tercatat dan masuk ke riwayat pembelian.' : 'Silakan tunggu konfirmasi pembayaran atau lanjutkan pembayaran dari riwayat.' ?></p><button class="btn btn-primary" type="button" onclick="document.getElementById('payment-success-modal').remove()">Oke, mengerti</button></div></div><script>setTimeout(function(){var modal=document.getElementById('payment-success-modal');if(modal)modal.remove()},8000)</script><?php endif; ?>
<?php include __DIR__ . '/includes/footer.php'; ?>
