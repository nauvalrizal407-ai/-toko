<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/transactions.php';
require_login();
$pdo = db();
$pageTitle = is_customer() ? 'Belanja Produk' : 'Kasir';
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
        } elseif ($action === 'scan') {
            $barcode = trim((string) ($_POST['barcode'] ?? ''));
            if ($barcode === '') throw new RuntimeException('Kode barcode belum dimasukkan.');
            $stmt = $pdo->prepare('SELECT * FROM barang WHERE kode_barang = ? AND aktif = 1');
            $stmt->execute([$barcode]);
            $item = $stmt->fetch();
            if (!$item) throw new RuntimeException('Barcode "' . $barcode . '" tidak ditemukan.');
            if ((int) $item['stok'] <= 0) throw new RuntimeException('Stok barang "' . $item['nama_barang'] . '" sudah habis.');
            $id = (int) $item['id'];
            $current = $_SESSION['cart'][$id]['qty'] ?? 0;
            if ($current + 1 > (int) $item['stok']) throw new RuntimeException('Stok barang tidak mencukupi.');
            $_SESSION['cart'][$id] = ['id'=>$id, 'kode'=>$item['kode_barang'], 'nama'=>$item['nama_barang'], 'harga'=>$item['harga'], 'qty'=>$current + 1];
            flash('success', $item['nama_barang'] . ' ditambahkan ke keranjang.');
            header('Location: ' . url('kasir.php'));
            exit;
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
            $customerId = is_customer()
                ? current_customer_id()
                : ((int) ($_POST['pelanggan_id'] ?? 0) ?: null);
            if (is_customer() && !$customerId) {
                throw new RuntimeException('Profil pelanggan belum tersedia. Hubungi admin toko.');
            }
            $paymentMethod = (string) ($_POST['payment_method'] ?? 'cash');
            if (!in_array($paymentMethod, ['cash', 'qris', 'duitku'], true)) {
                throw new RuntimeException('Metode pembayaran tidak valid.');
            }
            if ($paymentMethod === 'duitku') {
                $duitkuMethod = strtoupper(trim((string) ($_POST['duitku_method'] ?? env_value('DUITKU_PAYMENT_METHOD', 'VC'))));
                if (!preg_match('/^[A-Z0-9]{2,10}$/', $duitkuMethod)) throw new RuntimeException('Metode pembayaran Duitku tidak valid.');
                $transaction = create_duitku_transaction($pdo, $_SESSION['cart'], $customerId, $duitkuMethod);
                $_SESSION['cart'] = [];
                header('Location: ' . $transaction['payment_url']);
                exit;
            }
            if ($paymentMethod === 'qris') {
                $transaction = create_qris_transaction($pdo, $_SESSION['cart'], $customerId);
                $_SESSION['cart'] = [];
                header('Location: ' . url('qris.php?view=' . $transaction['id']));
                exit;
            }
            $paid = (float) ($_POST['bayar'] ?? 0);
            $transaction = finalize_cart_payment($pdo, $_SESSION['cart'], $customerId, $paid, $paymentMethod);
            $_SESSION['cart'] = [];
            flash('success', 'Transaksi ' . $transaction['number'] . ' berhasil dibayar. Kembalian: ' . money($transaction['change']));
            header('Location: ' . url(is_customer() ? 'kasir.php' : 'transaksi.php?view=' . $transaction['id']));
            exit;
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

$products = $pdo->query('SELECT * FROM barang WHERE aktif = 1 AND stok > 0 ORDER BY nama_barang')->fetchAll();
$duitkuMethods = [];
if (is_customer() && duitku_configured() && $_SESSION['cart']) {
    $cartAmount = 0;
    foreach ($_SESSION['cart'] as $cartRow) $cartAmount += (int) round((float) $cartRow['harga']) * (int) $cartRow['qty'];
    $cache = $_SESSION['_duitku_methods'] ?? null;
    if (!$cache || empty($cache['methods']) || (int) ($cache['expires'] ?? 0) < time() || (int) ($cache['amount'] ?? 0) !== $cartAmount) {
        try {
            $cache = ['expires' => time() + 300, 'amount' => $cartAmount, 'methods' => duitku_payment_methods($cartAmount)];
            $_SESSION['_duitku_methods'] = $cache;
        } catch (Throwable $e) {
            $cache = ['expires' => time() + 60, 'amount' => $cartAmount, 'methods' => []];
            $_SESSION['_duitku_methods'] = $cache;
        }
    }
    $duitkuMethods = $cache['methods'] ?? [];
}
$categoryOptions = ['' => 'Semua', 'Sepatu' => 'Sepatu', 'Jaket' => 'Jaket', 'Perlengkapan' => 'Perlengkapan'];
$selectedCategory = trim($_GET['kategori'] ?? '');
if (!array_key_exists($selectedCategory, $categoryOptions)) $selectedCategory = '';
include __DIR__ . '/includes/header.php';
?>
<section class="card">
    <div class="section-head"><div><h2><?= is_customer() ? 'Pilih produk untuk dibeli' : 'Pilih atau scan barang' ?></h2><p class="muted small"><?= is_customer() ? 'Tambahkan produk ke keranjang, lalu lanjutkan pembayaran.' : 'Gunakan pencarian atau scanner untuk mempercepat transaksi.' ?></p></div></div>
    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
    <?php if (!is_customer()): ?><form class="barcode-scan-row" method="post" autocomplete="off">
        <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="scan">
        <label for="barcode-input">Scan barcode</label>
        <div class="barcode-scan-controls">
            <input id="barcode-input" class="input" name="barcode" placeholder="Arahkan scanner ke sini, lalu scan..." autofocus>
            <button class="btn btn-primary" type="submit">Tambah</button>
        </div>
        <small class="muted">Scanner barcode akan menambahkan barang secara otomatis setelah kode dibaca.</small>
    </form><?php endif; ?>
    <form class="search-row" method="get"><input class="input" name="q" placeholder="Cari nama barang..." value="<?= e($_GET['q'] ?? '') ?>"><button class="btn btn-light">Cari</button></form>
    <div class="category-tabs"><?php foreach ($categoryOptions as $categoryValue => $categoryLabel): ?><a class="category-tab <?= $selectedCategory === $categoryValue ? 'active' : '' ?>" href="<?= e(url('kasir.php' . ($categoryValue !== '' ? '?kategori=' . rawurlencode($categoryValue) : ''))) ?>"><?= e($categoryLabel) ?></a><?php endforeach; ?></div>
    <div class="product-grid">
    <?php $q = strtolower(trim($_GET['q'] ?? '')); $shownProducts = 0; foreach ($products as $product): if ($selectedCategory !== '' && strtolower($product['kategori'] ?? '') !== strtolower($selectedCategory)) continue; if ($q && strpos(strtolower($product['nama_barang'] . ' ' . $product['kode_barang']), $q) === false) continue; $shownProducts++; ?><form method="post"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="barang_id" value="<?= e($product['id']) ?>"><button class="product-card" type="submit" style="width:100%;text-align:left"><?php if (!empty($product['foto'])): ?><img class="product-card-image" src="<?= e(url('assets/uploads/barang/' . $product['foto'])) ?>" alt="<?= e($product['nama_barang']) ?>"><?php else: ?><span class="product-card-image placeholder">▤</span><?php endif; ?><strong><?= e($product['nama_barang']) ?></strong><div class="price"><?= money($product['harga']) ?></div><div class="stock"><?= e($product['kategori'] ?: 'Tanpa kategori') ?> · Stok <?= e($product['stok']) ?> · <?= e($product['kode_barang']) ?></div></button></form><?php endforeach; if (!$shownProducts): ?><p class="muted">Belum ada barang pada kategori ini.</p><?php endif; ?></div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
