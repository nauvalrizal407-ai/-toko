<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

$pageTitle = 'Laporan';
$activePage = 'laporan';
$pdo = db();
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$cashierId = trim((string) ($_GET['cashier_id'] ?? ''));
if ($cashierId !== '' && !ctype_digit($cashierId)) {
    http_response_code(400);
    exit('Akun kasir tidak valid.');
}

$cashiers = $pdo->query("SELECT id,nama,username FROM app_users WHERE role='kasir' ORDER BY nama,id")->fetchAll();
$selectedCashier = null;
if ($cashierId !== '') {
    foreach ($cashiers as $cashier) {
        if ((string) $cashier['id'] === $cashierId) {
            $selectedCashier = $cashier;
            break;
        }
    }
    if (!$selectedCashier) {
        http_response_code(400);
        exit('Akun kasir tidak ditemukan.');
    }
}

$transactionFilter = "t.status='paid' AND DATE(t.paid_at) BETWEEN ? AND ? AND t.user_id IN (SELECT id FROM app_users WHERE role='kasir')";
$queryParams = [$from, $to];
if ($selectedCashier) {
    $transactionFilter .= ' AND t.user_id=?';
    $queryParams[] = (int) $selectedCashier['id'];
}

$stmt = $pdo->prepare("SELECT COUNT(*) jumlah_transaksi,COALESCE(SUM(t.total),0) omzet,COALESCE(SUM(t.bayar-t.total),0) kembalian FROM app_transaksi t WHERE $transactionFilter");
$stmt->execute($queryParams);
$summary = $stmt->fetch();

$stmt = $pdo->prepare("SELECT DATE(t.paid_at) tanggal,COUNT(*) jumlah,COALESCE(SUM(t.total),0) omzet FROM app_transaksi t WHERE $transactionFilter GROUP BY DATE(t.paid_at) ORDER BY tanggal DESC");
$stmt->execute($queryParams);
$daily = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT b.nama_barang,SUM(d.qty) qty,SUM(d.subtotal) omzet FROM app_detail_transaksi d JOIN app_transaksi t ON t.id=d.transaksi_id JOIN barang b ON b.id=d.barang_id WHERE $transactionFilter GROUP BY d.barang_id,b.nama_barang ORDER BY omzet DESC LIMIT 10");
$stmt->execute($queryParams);
$products = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT t.user_id cashier_id,t.nomor_transaksi,t.paid_at,t.payment_method,t.total,u.nama kasir,u.username FROM app_transaksi t JOIN app_users u ON u.id=t.user_id WHERE $transactionFilter ORDER BY t.paid_at DESC,t.id DESC");
$stmt->execute($queryParams);
$transactions = $stmt->fetchAll();

$cashierReports = [];
foreach ($cashiers as $cashier) {
    if ($selectedCashier && (int) $cashier['id'] !== (int) $selectedCashier['id']) {
        continue;
    }
    $cashierReports[(int) $cashier['id']] = [
        'name' => $cashier['nama'],
        'username' => $cashier['username'],
        'transactions' => [],
        'count' => 0,
        'cash' => 0.0,
        'qris' => 0.0,
        'total' => 0.0,
    ];
}
$printTotals = ['count' => 0, 'cash' => 0.0, 'qris' => 0.0, 'total' => 0.0];
foreach ($transactions as $transaction) {
    $cashierIdForReport = (int) $transaction['cashier_id'];
    if (!isset($cashierReports[$cashierIdForReport])) {
        continue;
    }
    $amount = (float) $transaction['total'];
    $paymentMethod = strtolower($transaction['payment_method']);
    $cashierReports[$cashierIdForReport]['transactions'][] = $transaction;
    $cashierReports[$cashierIdForReport]['count']++;
    if (isset($cashierReports[$cashierIdForReport][$paymentMethod])) {
        $cashierReports[$cashierIdForReport][$paymentMethod] += $amount;
    }
    $cashierReports[$cashierIdForReport]['total'] += $amount;
    $printTotals['count']++;
    if (isset($printTotals[$paymentMethod])) {
        $printTotals[$paymentMethod] += $amount;
    }
    $printTotals['total'] += $amount;
}

include __DIR__ . '/includes/header.php';
?>
<section class="card no-print">
    <div class="section-head">
        <div><h2>Filter laporan</h2></div>
        <button class="btn btn-light" type="button" onclick="window.print()">Cetak laporan ini</button>
    </div>
    <form class="form-grid report-filter-form" method="get">
        <div>
            <label for="cashier-filter">Akun kasir</label>
            <select class="select" id="cashier-filter" name="cashier_id">
                <option value="">Semua kasir</option>
                <?php foreach ($cashiers as $cashier): ?>
                    <option value="<?= e($cashier['id']) ?>" <?= $cashierId === (string) $cashier['id'] ? 'selected' : '' ?>><?= e($cashier['nama']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="date-from">Dari tanggal</label>
            <input class="input" id="date-from" type="date" name="from" value="<?= e($from) ?>">
        </div>
        <div>
            <label for="date-to">Sampai tanggal</label>
            <input class="input" id="date-to" type="date" name="to" value="<?= e($to) ?>">
        </div>
        <div class="full">
            <button class="btn btn-primary" type="submit">Tampilkan laporan</button>
        </div>
    </form>
</section>

<div class="report-print-only">
    <div class="report-print-meta"><span><?= e(date('H:i')) ?></span><span><?= e(date('d F Y')) ?></span></div>
    <header class="report-print-header">
        <strong><?= e(APP_NAME) ?></strong>
        <h1>Laporan Transaksi</h1>
        <p><?= e(date('l, d F Y', strtotime($from))) ?> – <?= e(date('l, d F Y', strtotime($to))) ?></p>
        <p>Kasir: <?= e($selectedCashier['nama'] ?? 'Semua kasir') ?></p>
    </header>

    <?php foreach ($cashierReports as $cashierReport): ?>
        <section class="report-cashier-section">
            <div class="report-cashier-heading">
                <strong><?= e($cashierReport['name']) ?></strong>
                <span><?= e($cashierReport['username']) ?></span>
            </div>
            <table class="report-ledger-table">
                <thead>
                    <tr><th>Tanggal</th><th>No. Ref.</th><th>Metode</th><th>Keterangan</th><th>Total</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($cashierReport['transactions'] as $transaction): ?>
                        <tr>
                            <td><?= e(date('d/m/Y H:i', strtotime($transaction['paid_at']))) ?></td>
                            <td><?= e($transaction['nomor_transaksi']) ?></td>
                            <td><?= e(strtoupper($transaction['payment_method'])) ?></td>
                            <td>Penjualan barang</td>
                            <td class="numeric"><?= e(money($transaction['total'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$cashierReport['transactions']): ?>
                        <tr><td colspan="5" class="report-empty">Tidak ada transaksi dibayar pada periode ini.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="report-cashier-summary">
                <div><span>Jumlah transaksi</span><strong><?= e($cashierReport['count']) ?></strong></div>
                <div><span>Tunai</span><strong><?= e(money($cashierReport['cash'])) ?></strong></div>
                <div><span>QRIS</span><strong><?= e(money($cashierReport['qris'])) ?></strong></div>
                <div><span>Total omzet</span><strong><?= e(money($cashierReport['total'])) ?></strong></div>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="report-grand-summary">
        <strong><?= e($selectedCashier ? 'Ringkasan total' : 'Ringkasan semua kasir') ?></strong>
        <div><span>Jumlah transaksi</span><strong><?= e($printTotals['count']) ?></strong></div>
        <div><span>Tunai</span><strong><?= e(money($printTotals['cash'])) ?></strong></div>
        <div><span>QRIS</span><strong><?= e(money($printTotals['qris'])) ?></strong></div>
        <div><span>Total omzet</span><strong><?= e(money($printTotals['total'])) ?></strong></div>
    </section>
</div>

<section class="card report-screen-only">
    <h2>Laporan transaksi</h2>
    <p class="muted small">Kasir: <?= e($selectedCashier['nama'] ?? 'Semua kasir') ?> · Periode <?= e(date('d/m/Y', strtotime($from))) ?> – <?= e(date('d/m/Y', strtotime($to))) ?></p>
</section>

<div class="grid stats-grid report-screen-only" style="margin-top:18px">
    <div class="card stat-card"><div class="label">Total omzet</div><div class="number"><?= money($summary['omzet']) ?></div></div>
    <div class="card stat-card green"><div class="label">Jumlah transaksi</div><div class="number"><?= e($summary['jumlah_transaksi']) ?></div></div>
    <div class="card stat-card orange"><div class="label">Rata-rata transaksi</div><div class="number"><?= money($summary['jumlah_transaksi'] ? (float) $summary['omzet'] / $summary['jumlah_transaksi'] : 0) ?></div></div>
    <div class="card stat-card red"><div class="label">Kembalian tercatat</div><div class="number"><?= money($summary['kembalian']) ?></div></div>
</div>

<section class="card report-screen-only">
    <div class="section-head"><h2>Rincian transaksi dibayar</h2></div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Nomor transaksi</th><th>Tanggal</th><th>Kasir</th><th>Metode</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?= e($transaction['nomor_transaksi']) ?></td>
                        <td><?= e(date('d/m/Y H:i', strtotime($transaction['paid_at']))) ?></td>
                        <td><?= e($transaction['kasir']) ?></td>
                        <td><?= e(strtoupper($transaction['payment_method'])) ?></td>
                        <td class="amount"><?= money($transaction['total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$transactions): ?><tr><td colspan="5" class="empty">Tidak ada transaksi dibayar pada filter ini.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="grid two-col report-screen-only">
    <section class="card">
        <div class="section-head"><h2>Penjualan per hari</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Tanggal</th><th>Transaksi</th><th>Omzet</th></tr></thead>
                <tbody>
                    <?php foreach ($daily as $row): ?>
                        <tr><td><?= e(date('d F Y', strtotime($row['tanggal']))) ?></td><td><?= e($row['jumlah']) ?></td><td class="amount"><?= money($row['omzet']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$daily): ?><tr><td colspan="3" class="empty">Tidak ada data pada rentang tanggal ini.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <div class="section-head"><h2>Produk terlaris</h2></div>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>Barang</th><th>Qty</th><th>Omzet</th></tr></thead>
                <tbody>
                    <?php foreach ($products as $row): ?>
                        <tr><td><?= e($row['nama_barang']) ?></td><td><?= e($row['qty']) ?></td><td class="amount"><?= money($row['omzet']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$products): ?><tr><td colspan="3" class="empty">Belum ada produk terjual.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
