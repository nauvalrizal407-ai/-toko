<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
$pageTitle = 'Kasir';
$activePage = 'kasir';
$error = '';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'add') {
            $id = (int) $_POST['barang_id'];
            $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = ? AND aktif = 1'); $stmt->execute([$id]); $item = $stmt->fetch();
            if (!$item) throw new RuntimeException('Barang tidak ditemukan.');
            $current = $_SESSION['cart'][$id]['qty'] ?? 0;
            if ($current + 1 > (int) $item['stok']) throw new RuntimeException('Stok barang tidak mencukupi.');
            $_SESSION['cart'][$id] = ['id'=>$item['id'], 'kode'=>$item['kode_barang'], 'nama'=>$item['nama_barang'], 'harga'=>$item['harga'], 'qty'=>$current+1];
        } elseif ($action === 'update') {
            foreach (($_POST['qty'] ?? []) as $id => $qty) {
                $id = (int) $id; $qty = (int) $qty;
                if (!isset($_SESSION['cart'][$id])) continue;
                $stmt = $pdo->prepare('SELECT stok FROM barang WHERE id = ? AND aktif = 1'); $stmt->execute([$id]); $stock = (int) $stmt->fetchColumn();
                if ($qty <= 0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]['qty'] = min($qty, $stock);
            }
        } elseif ($action === 'clear') {
            $_SESSION['cart'] = [];
        } elseif ($action === 'checkout') {
            if (!$_SESSION['cart']) throw new RuntimeException('Keranjang masih kosong.');
            $customerId = (int) ($_POST['pelanggan_id'] ?? 0) ?: null; $paid = (float) ($_POST['bayar'] ?? 0); $total = 0;
            foreach ($_SESSION['cart'] as $row) $total += $row['harga'] * $row['qty'];
            if ($paid < $total) throw new RuntimeException('Nominal pembayaran kurang dari total transaksi.');
            $pdo->beginTransaction(); $number = 'TRX-' . date('ymdHis') . '-' . random_int(10, 99);
            $stmt = $pdo->prepare("INSERT INTO app_transaksi (nomor_transaksi,pelanggan_id,user_id,subtotal,diskon,total,bayar,kembalian,status,paid_at) VALUES (?,?,?,?,?,?,?,?, 'paid', NOW())");
            $stmt->execute([$number, $customerId, current_user()['id'], $total, 0, $total, $paid, $paid - $total]); $transactionId = (int) $pdo->lastInsertId();
            foreach ($_SESSION['cart'] as $row) {
                $stmt = $pdo->prepare('INSERT INTO app_detail_transaksi (transaksi_id,barang_id,nama_barang,harga,qty,subtotal) VALUES (?,?,?,?,?,?)'); $stmt->execute([$transactionId, $row['id'], $row['nama'], $row['harga'], $row['qty'], $row['harga'] * $row['qty']]);
                $stmt = $pdo->prepare('UPDATE barang SET stok=stok-? WHERE id=? AND aktif=1 AND stok>=?'); $stmt->execute([$row['qty'], $row['id'], $row['qty']]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('Stok berubah. Silakan ulangi transaksi.');
            }
            $stmt = $pdo->prepare('INSERT INTO app_laporan_transaksi (transaksi_id,tanggal,total,status) VALUES (?,CURDATE(),?,?)'); $stmt->execute([$transactionId, $total, 'paid']);
            if ($customerId) {
                $stmt = $pdo->prepare('INSERT INTO app_log_pelanggan (pelanggan_id,transaksi_id,aksi) VALUES (?,?,?)'); $stmt->execute([$customerId, $transactionId, 'Pembelian']);
                $stmt = $pdo->prepare('INSERT INTO app_total_pelanggan (pelanggan_id,total_transaksi,total_belanja) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_transaksi=total_transaksi+1,total_belanja=total_belanja+VALUES(total_belanja)'); $stmt->execute([$customerId, 1, $total]);
            }
            $pdo->commit(); log_activity('transaksi', 'Menyelesaikan transaksi ' . $number); $_SESSION['cart'] = [];
            flash('success', 'Transaksi ' . $number . ' berhasil dibayar. Kembalian: ' . money($paid - $total)); header('Location: ' . url('transaksi.php?view=' . $transactionId)); exit;
        }
    } catch (Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); $error = $e->getMessage(); }
}

$products = $pdo->query('SELECT * FROM barang WHERE aktif = 1 AND stok > 0 ORDER BY nama_barang')->fetchAll();
$categoryOptions = ['' => 'Semua', 'Sepatu' => 'Sepatu', 'Jaket' => 'Jaket', 'Perlengkapan' => 'Perlengkapan'];
$selectedCategory = trim($_GET['kategori'] ?? '');
if (!array_key_exists($selectedCategory, $categoryOptions)) $selectedCategory = '';
include __DIR__ . '/includes/header.php';
?>
<section class="card">
    <div class="section-head"><div><h2>Pilih barang</h2></div></div>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <form class="search-row" method="get"><input class="input" name="q" placeholder="Cari nama barang..." value="<?= e($_GET['q'] ?? '') ?>"><button class="btn btn-light">Cari</button></form>
    <div class="category-tabs"><?php foreach ($categoryOptions as $categoryValue => $categoryLabel): ?><a class="category-tab <?= $selectedCategory === $categoryValue ? 'active' : '' ?>" href="<?= e(url('kasir.php' . ($categoryValue !== '' ? '?kategori=' . rawurlencode($categoryValue) : ''))) ?>"><?= e($categoryLabel) ?></a><?php endforeach; ?></div>
    <div class="product-grid">
    <?php $q = strtolower(trim($_GET['q'] ?? '')); $shownProducts = 0; foreach ($products as $product): if ($selectedCategory !== '' && strtolower($product['kategori'] ?? '') !== strtolower($selectedCategory)) continue; if ($q && strpos(strtolower($product['nama_barang'] . ' ' . $product['kode_barang']), $q) === false) continue; $shownProducts++; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="barang_id" value="<?= e($product['id']) ?>"><button class="product-card" type="submit" style="width:100%;text-align:left"><?php if (!empty($product['foto'])): ?><img class="product-card-image" src="<?= e(url('assets/uploads/barang/' . $product['foto'])) ?>" alt="<?= e($product['nama_barang']) ?>"><?php else: ?><span class="product-card-image placeholder">▤</span><?php endif; ?><strong><?= e($product['nama_barang']) ?></strong><div class="price"><?= money($product['harga']) ?></div><div class="stock"><?= e($product['kategori'] ?: 'Tanpa kategori') ?> · Stok <?= e($product['stok']) ?> · <?= e($product['kode_barang']) ?></div></button></form><?php endforeach; if (!$shownProducts): ?><p class="muted">Belum ada barang pada kategori ini.</p><?php endif; ?></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
