<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/transactions.php';
require_login();

$pdo = db();
$transactionId = (int) ($_GET['view'] ?? 0);
$transactionQuery = 'SELECT t.*,u.nama kasir,p.nama_pelanggan pelanggan FROM app_transaksi t JOIN app_users u ON u.id=t.user_id LEFT JOIN pelanggan p ON p.id_pelanggan=t.pelanggan_id WHERE t.id=? AND t.payment_method=\'qris\'';
$transactionParams = [$transactionId];
if (!is_admin()) {
    $transactionQuery .= ' AND t.user_id=?';
    $transactionParams[] = (int) current_user()['id'];
}
$stmt = $pdo->prepare($transactionQuery);
$stmt->execute($transactionParams);
$transaction = $stmt->fetch();
if (!$transaction) {
    http_response_code(404);
    exit('Transaksi QRIS tidak ditemukan.');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    try {
        $action = $_POST['action'] ?? '';
        if ($action === 'confirm') {
            complete_qris_transaction($pdo, $transactionId);
            flash('success', 'Pembayaran QRIS ' . $transaction['nomor_transaksi'] . ' berhasil dikonfirmasi.');
            header('Location: ' . url(is_customer() ? 'kasir.php' : 'transaksi.php?view=' . $transactionId));
            exit;
        }
        if ($action === 'cancel') {
            cancel_qris_transaction($pdo, $transactionId);
            flash('success', 'Transaksi QRIS dibatalkan dan stok dikembalikan.');
            header('Location: ' . url(is_customer() ? 'kasir.php' : 'transaksi.php'));
            exit;
        }
        throw new RuntimeException('Aksi pembayaran tidak valid.');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    $transactionQuery = 'SELECT * FROM app_transaksi WHERE id=? AND payment_method=\'qris\'';
    $transactionParams = [$transactionId];
    if (!is_admin()) {
        $transactionQuery .= ' AND user_id=?';
        $transactionParams[] = (int) current_user()['id'];
    }
    $stmt = $pdo->prepare($transactionQuery);
    $stmt->execute($transactionParams);
    $transaction = $stmt->fetch();
}

$stmt = $pdo->prepare('SELECT nama_barang, harga, qty, subtotal FROM app_detail_transaksi WHERE transaksi_id=? ORDER BY id');
$stmt->execute([$transactionId]);
$items = $stmt->fetchAll();

$pageTitle = 'Pembayaran QRIS';
$activePage = 'kasir';
include __DIR__ . '/includes/header.php';
?>
<section class="card">
    <div class="section-head">
        <div>
            <h2>Scan QRIS untuk membayar</h2>
            <p class="muted small">Transaksi <?= e($transaction['nomor_transaksi']) ?></p>
        </div>
        <a class="btn btn-light" href="<?= e(url(is_customer() ? 'kasir.php' : 'transaksi.php')) ?>">Kembali</a>
    </div>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <?php if ($transaction['status'] === 'unpaid'): ?>
        <div class="qris-payment-layout">
            <div class="qris-code-panel">
                <img class="qris-image" src="<?= e(url('assets/images/qris.png')) ?>" alt="QRIS bisnis TokoKu untuk pembayaran">
                <strong>Pindai QRIS bisnis TokoKu</strong>
                <span class="muted small">Gunakan aplikasi pembayaran yang mendukung QRIS.</span>
            </div>
            <div class="qris-payment-info">
                <p class="muted">Total yang harus dibayar</p>
                <strong class="qris-amount"><?= e(money($transaction['total'])) ?></strong>
                <p>Pastikan jumlah pembayaran sesuai dengan total transaksi di atas.</p>

                <div class="qris-order-items">
                    <?php foreach ($items as $item): ?>
                        <div class="qris-order-row">
                            <span><?= e($item['nama_barang']) ?> × <?= e($item['qty']) ?></span>
                            <strong><?= e(money($item['subtotal'])) ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="alert warning qris-verification-warning">
                    Kasir harus memeriksa pembayaran di aplikasi atau riwayat merchant sebelum mengonfirmasi. Jangan konfirmasi hanya berdasarkan bukti layar pelanggan.
                </div>

                <div class="qris-actions">
                    <form method="post">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="confirm">
                        <button class="btn btn-primary" type="submit">Konfirmasi pembayaran masuk</button>
                    </form>
                    <form method="post" onsubmit="return confirm('Batalkan transaksi QRIS ini? Stok barang akan dikembalikan.')">
                        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <button class="btn btn-danger" type="submit">Batalkan transaksi</button>
                    </form>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert <?= $transaction['status'] === 'paid' ? 'success' : 'error' ?>">
            Transaksi ini berstatus <?= e($transaction['status'] === 'paid' ? 'sudah dibayar' : 'dibatalkan') ?>.
        </div>
        <?php if ($transaction['status'] === 'paid'): ?>
            <a class="btn btn-primary" href="<?= e(url('transaksi.php?view=' . $transactionId)) ?>">Lihat detail transaksi</a>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
