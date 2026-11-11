<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
$pdo = db();
$pageTitle = 'Data Barang';
$activePage = 'barang';
$error = '';
$edit = null;
$newPhotoPath = null;
$uploadDir = __DIR__ . '/assets/uploads/barang';
$categoryOptions = ['Sepatu', 'Jaket', 'Perlengkapan'];

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM barang WHERE id = ? AND aktif = 1');
    $stmt->execute([(int) $_GET['edit']]);
    $edit = $stmt->fetch();
}

function upload_product_photo(array $file, string $directory): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Foto barang gagal diunggah.');
    }
    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        throw new RuntimeException('Ukuran foto maksimal 2 MB.');
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($extensions[$mime])) {
        throw new RuntimeException('Format foto harus JPG, PNG, atau WEBP.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Folder upload foto tidak dapat dibuat.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
    if (!move_uploaded_file($file['tmp_name'], $directory . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Foto barang tidak dapat disimpan.');
    }
    return $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save') {
            $id = (int) ($_POST['id'] ?? 0);
            $kode = trim($_POST['kode_barang'] ?? '');
            $nama = trim($_POST['nama_barang'] ?? '');
            $kategori = trim($_POST['kategori'] ?? '');
            $harga = parse_price($_POST['harga'] ?? '');
            $stok = (int) ($_POST['stok'] ?? 0);
            $satuan = trim($_POST['satuan'] ?? 'pcs') ?: 'pcs';
            if ($kode === '' || $nama === '' || $harga < 0 || $stok < 0) {
                throw new RuntimeException('Lengkapi data barang dengan benar.');
            }

            $photo = '';
            if (!empty($_FILES['foto']['name'])) {
                $photo = upload_product_photo($_FILES['foto'], $uploadDir);
                $newPhotoPath = $uploadDir . DIRECTORY_SEPARATOR . $photo;
            }

            if ($id) {
                if ($photo !== '') {
                    $stmt = $pdo->prepare('SELECT foto FROM barang WHERE id = ?');
                    $stmt->execute([$id]);
                    $oldPhoto = $stmt->fetchColumn();
                    $stmt = $pdo->prepare('UPDATE barang SET kode_barang=?, nama_barang=?, kategori=?, harga=?, stok=?, satuan=?, foto=? WHERE id=?');
                    $stmt->execute([$kode, $nama, $kategori, $harga, $stok, $satuan, $photo, $id]);
                    if ($oldPhoto) {
                        @unlink($uploadDir . DIRECTORY_SEPARATOR . basename((string) $oldPhoto));
                    }
                } else {
                    $stmt = $pdo->prepare('UPDATE barang SET kode_barang=?, nama_barang=?, kategori=?, harga=?, stok=?, satuan=? WHERE id=?');
                    $stmt->execute([$kode, $nama, $kategori, $harga, $stok, $satuan, $id]);
                }
                log_activity('ubah_barang', 'Mengubah barang ' . $nama);
                flash('success', 'Data barang berhasil diperbarui.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO barang (kode_barang,nama_barang,kategori,harga,stok,satuan,foto) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$kode, $nama, $kategori, $harga, $stok, $satuan, $photo ?: null]);
                log_activity('tambah_barang', 'Menambahkan barang ' . $nama);
                flash('success', 'Barang baru berhasil ditambahkan.');
            }
            header('Location: ' . url('barang.php'));
            exit;
        }

        if ($action === 'delete') {
            if (!is_admin()) {
                throw new RuntimeException('Kasir tidak memiliki akses menghapus barang.');
            }
            $id = (int) $_POST['id'];
            $stmt = $pdo->prepare('SELECT foto FROM barang WHERE id = ?');
            $stmt->execute([$id]);
            $photo = $stmt->fetchColumn();
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM app_detail_transaksi WHERE barang_id = ?');
            $stmt->execute([$id]);
            $usedInTransactions = (int) $stmt->fetchColumn() > 0;
            if ($usedInTransactions) {
                // Riwayat transaksi harus tetap utuh, jadi barang dibuat tidak aktif.
                $pdo->prepare('UPDATE barang SET aktif = 0, stok = 0 WHERE id = ?')->execute([$id]);
                log_activity('nonaktifkan_barang', 'Menonaktifkan barang #' . $id . ' karena sudah ada di transaksi');
                flash('success', 'Barang dinonaktifkan dari daftar. Riwayat transaksi tetap tersimpan.');
            } else {
                $pdo->prepare('DELETE FROM barang WHERE id = ?')->execute([$id]);
                if ($photo) {
                    @unlink($uploadDir . DIRECTORY_SEPARATOR . basename((string) $photo));
                }
                log_activity('hapus_barang', 'Menghapus barang #' . $id);
                flash('success', 'Barang berhasil dihapus.');
            }
            header('Location: ' . url('barang.php'));
            exit;
        }
    } catch (Throwable $e) {
        if ($newPhotoPath) {
            @unlink($newPhotoPath);
        }
        $error = $e instanceof PDOException && $e->getCode() === '23000'
            ? 'Kode barang sudah digunakan atau barang sudah dipakai dalam transaksi.'
            : $e->getMessage();
    }
}

$q = trim($_GET['q'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM barang WHERE aktif = 1 AND (kode_barang LIKE ? OR nama_barang LIKE ? OR kategori LIKE ?) ORDER BY nama_barang');
$like = '%' . $q . '%';
$stmt->execute([$like, $like, $like]);
$items = $stmt->fetchAll();
include __DIR__ . '/includes/header.php';
?>
<div class="grid two-col">
    <section class="card">
        <div class="section-head"><div><h2><?= $edit ? 'Ubah barang' : 'Tambah barang' ?></h2></div></div>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <div class="form-grid">
                <div><label>Kode barang</label><input class="input" name="kode_barang" required value="<?= e($edit['kode_barang'] ?? '') ?>"></div>
                <div><label>Nama barang</label><input class="input" name="nama_barang" required value="<?= e($edit['nama_barang'] ?? '') ?>"></div>
                <div><label>Kategori</label><select class="select" name="kategori" required><?php $currentCategory = trim($edit['kategori'] ?? ''); if ($currentCategory !== '' && !in_array($currentCategory, $categoryOptions, true)): ?><option value="<?= e($currentCategory) ?>" selected><?= e($currentCategory) ?> (kategori lama)</option><?php endif; ?><option value="">Pilih kategori</option><?php foreach ($categoryOptions as $category): ?><option value="<?= e($category) ?>" <?= $currentCategory === $category ? 'selected' : '' ?>><?= e($category) ?></option><?php endforeach; ?></select></div>
                <div><label>Satuan</label><input class="input" name="satuan" value="<?= e($edit['satuan'] ?? 'pcs') ?>"></div>
                <div><label>Harga jual</label><input class="input" type="text" inputmode="decimal" name="harga" required placeholder="Contoh: 4.000 atau 4.000,00" value="<?= e(isset($edit['harga']) ? price_input($edit['harga']) : '') ?>"></div>
                <div><label>Stok</label><input class="input" type="number" min="0" name="stok" required value="<?= e($edit['stok'] ?? 0) ?>"></div>
                <div class="full"><label>Foto barang <span class="muted">(rasio 4:5 · JPG, PNG, WEBP · maksimal 2 MB)</span></label><input class="input" type="file" name="foto" accept="image/jpeg,image/png,image/webp"><?php if (!empty($edit['foto'])): ?><div class="current-photo"><img class="product-thumb" src="<?= e(url('assets/uploads/barang/' . $edit['foto'])) ?>" alt="Foto <?= e($edit['nama_barang']) ?>"><span class="muted small">Foto saat ini. Pilih file baru untuk menggantinya.</span></div><?php endif; ?></div>
            </div>
            <div class="toolbar" style="margin-top:18px"><button class="btn btn-primary" type="submit"><?= $edit ? 'Simpan perubahan' : 'Tambah barang' ?></button><?php if ($edit): ?><a class="btn btn-light" href="<?= e(url('barang.php')) ?>">Batal</a><?php endif; ?></div>
        </form>
    </section>
    <section class="card">
        <div class="section-head"><h2>Daftar barang <span class="muted small">(<?= count($items) ?>)</span></h2></div>
        <form class="search-row" method="get"><input class="input" name="q" placeholder="Cari kode, nama, kategori..." value="<?= e($q) ?>"><button class="btn btn-light" type="submit">Cari</button></form>
        <div class="table-wrap"><table class="table"><thead><tr><th>Barang</th><th>Harga</th><th>Stok</th><th></th></tr></thead><tbody>
        <?php foreach ($items as $item): ?><tr><td><div class="item-with-photo"><?php if (!empty($item['foto'])): ?><img class="product-thumb" src="<?= e(url('assets/uploads/barang/' . $item['foto'])) ?>" alt="<?= e($item['nama_barang']) ?>"><?php else: ?><span class="product-thumb placeholder">▤</span><?php endif; ?><div><strong><?= e($item['nama_barang']) ?></strong><div class="muted small"><?= e($item['kode_barang']) ?> · <?= e($item['kategori'] ?: 'Tanpa kategori') ?></div></div></div></td><td class="amount"><?= money($item['harga']) ?></td><td><span class="badge <?= $item['stok'] <= 5 ? 'unpaid' : 'paid' ?>"><?= e($item['stok']) ?> <?= e($item['satuan']) ?></span></td><td><div class="actions"><a class="btn btn-light btn-sm" href="<?= e(url('barang.php?edit=' . $item['id'])) ?>">Ubah</a><?php if (is_admin()): ?><form method="post" onsubmit="return confirm('Hapus barang ini?')"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($item['id']) ?>"><button class="btn btn-danger btn-sm">Hapus</button></form><?php endif; ?></div></td></tr><?php endforeach; ?>
        <?php if (!$items): ?><tr><td colspan="4" class="empty">Barang belum tersedia.</td></tr><?php endif; ?></tbody></table></div>
    </section>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
