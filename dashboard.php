<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pageTitle = 'Dashboard'; $activePage = 'dashboard';
$today = date('Y-m-d');
$pdo = db();
$stats = [
    'sales' => $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM app_transaksi WHERE status = 'paid' AND DATE(paid_at) = ?"),
    'count' => $pdo->prepare("SELECT COUNT(*) FROM app_transaksi WHERE status = 'paid' AND DATE(paid_at) = ?"),
];
$stats['sales']->execute([$today]); $todaySales = $stats['sales']->fetchColumn();
$stats['count']->execute([$today]); $todayCount = $stats['count']->fetchColumn();
$productCount = $pdo->query('SELECT COUNT(*) FROM barang WHERE aktif = 1')->fetchColumn();
$lowStock = $pdo->query('SELECT COUNT(*) FROM barang WHERE aktif = 1 AND stok <= 5')->fetchColumn();
$recent = $pdo->query("SELECT t.*, u.nama AS kasir FROM app_transaksi t JOIN app_users u ON u.id=t.user_id ORDER BY t.created_at DESC LIMIT 7")->fetchAll();
$topProducts = $pdo->query("SELECT d.nama_barang, SUM(d.qty) qty FROM app_detail_transaksi d JOIN app_transaksi t ON t.id=d.transaksi_id WHERE t.status='paid' GROUP BY d.barang_id, d.nama_barang ORDER BY qty DESC LIMIT 5")->fetchAll();
include __DIR__ . '/includes/header.php';
?><div class="grid stats-grid"><div class="card stat-card"><div class="stat-icon">Rp</div><div class="label">Penjualan hari ini</div><div class="number"><?= money($todaySales) ?></div></div><div class="card stat-card green"><div class="stat-icon">✓</div><div class="label">Transaksi hari ini</div><div class="number"><?= e($todayCount) ?></div></div><div class="card stat-card orange"><div class="stat-icon">▤</div><div class="label">Total barang</div><div class="number"><?= e($productCount) ?></div></div><div class="card stat-card red"><div class="stat-icon">!</div><div class="label">Stok menipis</div><div class="number"><?= e($lowStock) ?></div></div></div>
<div class="grid two-col"><section class="card"><div class="section-head"><h2>Transaksi terbaru</h2><a class="btn btn-light btn-sm" href="<?= e(url('transaksi.php')) ?>">Lihat semua</a></div><div class="table-wrap"><table class="table"><thead><tr><th>Nomor</th><th>Kasir</th><th>Total</th><th>Status</th></tr></thead><tbody><?php foreach ($recent as $row): ?><tr><td><strong><?= e($row['nomor_transaksi']) ?></strong><div class="muted small"><?= e(date('d/m/Y H:i', strtotime($row['created_at']))) ?></div></td><td><?= e($row['kasir']) ?></td><td class="amount"><?= money($row['total']) ?></td><td><span class="badge <?= e($row['status']) ?>"><?= e(ucfirst($row['status'])) ?></span></td></tr><?php endforeach; ?><?php if (!$recent): ?><tr><td colspan="4" class="empty">Belum ada transaksi.</td></tr><?php endif; ?></tbody></table></div></section><section class="card"><div class="section-head"><h2>Barang terlaris</h2><span class="muted small">Semua waktu</span></div><div class="bar-list"><?php $max = max(1, (int)($topProducts[0]['qty'] ?? 1)); foreach ($topProducts as $item): ?><div class="bar-row"><span><?= e($item['nama_barang']) ?></span><div class="bar"><i style="width:<?= min(100, round($item['qty'] / $max * 100)) ?>%"></i></div><strong><?= e($item['qty']) ?> terjual</strong></div><?php endforeach; ?><?php if (!$topProducts): ?><p class="muted">Belum ada data penjualan.</p><?php endif; ?></div></section></div>
<?php include __DIR__ . '/includes/footer.php'; ?>
