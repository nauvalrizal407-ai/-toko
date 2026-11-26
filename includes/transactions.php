<?php
declare(strict_types=1);

require_once __DIR__ . '/duitku.php';

function finalize_cart_payment(PDO $pdo, array $cart, ?int $customerId, float $paid, string $paymentMethod): array
{
    if (!$cart) {
        throw new RuntimeException('Keranjang masih kosong.');
    }
    if (!in_array($paymentMethod, ['cash', 'qris'], true)) {
        throw new RuntimeException('Metode pembayaran tidak valid.');
    }

    $total = 0.0;
    foreach ($cart as $row) {
        $total += (float) $row['harga'] * (int) $row['qty'];
    }
    if ($paid < $total) {
        throw new RuntimeException('Nominal pembayaran kurang dari total transaksi.');
    }

    $pdo->beginTransaction();
    try {
        $number = 'TRX-' . date('ymdHis') . '-' . random_int(10, 99);
        $change = $paid - $total;
        $stmt = $pdo->prepare("INSERT INTO app_transaksi (nomor_transaksi,pelanggan_id,user_id,subtotal,diskon,total,bayar,kembalian,payment_method,status,paid_at) VALUES (?,?,?,?,?,?,?,?,?,'paid',NOW())");
        $stmt->execute([$number, $customerId, current_user()['id'], $total, 0, $total, $paid, $change, $paymentMethod]);
        $transactionId = (int) $pdo->lastInsertId();

        foreach ($cart as $row) {
            $quantity = (int) $row['qty'];
            $stmt = $pdo->prepare('INSERT INTO app_detail_transaksi (transaksi_id,barang_id,nama_barang,harga,qty,subtotal) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$transactionId, $row['id'], $row['nama'], $row['harga'], $quantity, $row['harga'] * $quantity]);

            $stmt = $pdo->prepare('UPDATE barang SET stok=stok-? WHERE id=? AND aktif=1 AND stok>=?');
            $stmt->execute([$quantity, $row['id'], $quantity]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Stok berubah. Silakan ulangi transaksi.');
            }
        }

        $stmt = $pdo->prepare('INSERT INTO app_laporan_transaksi (transaksi_id,tanggal,total,status) VALUES (?,CURDATE(),?,?)');
        $stmt->execute([$transactionId, $total, 'paid']);

        if ($customerId) {
            $stmt = $pdo->prepare('INSERT INTO app_log_pelanggan (pelanggan_id,transaksi_id,aksi) VALUES (?,?,?)');
            $stmt->execute([$customerId, $transactionId, 'Pembelian']);
            $stmt = $pdo->prepare('INSERT INTO app_total_pelanggan (pelanggan_id,total_transaksi,total_belanja) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_transaksi=total_transaksi+1,total_belanja=total_belanja+VALUES(total_belanja)');
            $stmt->execute([$customerId, 1, $total]);
        }

        log_activity('transaksi', 'Menyelesaikan transaksi ' . $number);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['id' => $transactionId, 'number' => $number, 'total' => $total, 'change' => $change];
}

function create_qris_transaction(PDO $pdo, array $cart, ?int $customerId): array
{
    if (!$cart) {
        throw new RuntimeException('Keranjang masih kosong.');
    }

    $total = 0.0;
    foreach ($cart as $row) {
        $total += (float) $row['harga'] * (int) $row['qty'];
    }

    $pdo->beginTransaction();
    try {
        $number = 'TRX-' . date('ymdHis') . '-' . random_int(10, 99);
        $stmt = $pdo->prepare("INSERT INTO app_transaksi (nomor_transaksi,pelanggan_id,user_id,subtotal,diskon,total,bayar,kembalian,payment_method,status,paid_at) VALUES (?,?,?,?,?,?,0,0,'qris','unpaid',NULL)");
        $stmt->execute([$number, $customerId, current_user()['id'], $total, 0, $total]);
        $transactionId = (int) $pdo->lastInsertId();

        foreach ($cart as $row) {
            $quantity = (int) $row['qty'];
            $stmt = $pdo->prepare('INSERT INTO app_detail_transaksi (transaksi_id,barang_id,nama_barang,harga,qty,subtotal) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$transactionId, $row['id'], $row['nama'], $row['harga'], $quantity, $row['harga'] * $quantity]);

            $stmt = $pdo->prepare('UPDATE barang SET stok=stok-? WHERE id=? AND aktif=1 AND stok>=?');
            $stmt->execute([$quantity, $row['id'], $quantity]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Stok berubah. Silakan ulangi transaksi.');
            }
        }

        log_activity('qris_pending', 'Menunggu pembayaran QRIS untuk transaksi ' . $number);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['id' => $transactionId, 'number' => $number, 'total' => $total];
}

function create_duitku_transaction(PDO $pdo, array $cart, ?int $customerId, string $paymentMethod): array
{
    if (!$cart) throw new RuntimeException('Keranjang masih kosong.');
    $total = 0;
    foreach ($cart as $row) $total += (int) round((float) $row['harga']) * (int) $row['qty'];
    if ($total < 10000) throw new RuntimeException('Minimal transaksi payment gateway adalah Rp 10.000.');

    $pdo->beginTransaction();
    try {
        $number = 'TRX-' . date('ymdHis') . '-' . random_int(10, 99);
        $stmt = $pdo->prepare("INSERT INTO app_transaksi (nomor_transaksi,pelanggan_id,user_id,subtotal,diskon,total,bayar,kembalian,payment_method,status,paid_at) VALUES (?,?,?,?,?,?,0,0,'duitku','unpaid',NULL)");
        $stmt->execute([$number, $customerId, current_user()['id'], $total, 0, $total]);
        $transactionId = (int) $pdo->lastInsertId();
        foreach ($cart as $row) {
            $quantity = (int) $row['qty'];
            $price = (int) round((float) $row['harga']);
            $stmt = $pdo->prepare('INSERT INTO app_detail_transaksi (transaksi_id,barang_id,nama_barang,harga,qty,subtotal) VALUES (?,?,?,?,?,?)');
            $stmt->execute([$transactionId, $row['id'], $row['nama'], $price, $quantity, $price * $quantity]);
            $stmt = $pdo->prepare('UPDATE barang SET stok=stok-? WHERE id=? AND aktif=1 AND stok>=?');
            $stmt->execute([$quantity, $row['id'], $quantity]);
            if ($stmt->rowCount() !== 1) throw new RuntimeException('Stok berubah. Silakan ulangi transaksi.');
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }

    try {
        $gateway = duitku_create_invoice(['nomor_transaksi' => $number, 'total' => $total], $cart, $paymentMethod);
        $stmt = $pdo->prepare('UPDATE app_transaksi SET gateway_reference=?, gateway_payment_url=? WHERE id=? AND status=?');
        $stmt->execute([$gateway['reference'], $gateway['payment_url'], $transactionId, 'unpaid']);
        log_activity('duitku_pending', 'Menunggu pembayaran Duitku untuk transaksi ' . $number);
        return ['id' => $transactionId, 'number' => $number, 'total' => $total, 'payment_url' => $gateway['payment_url']];
    } catch (Throwable $e) {
        cancel_duitku_transaction($pdo, $transactionId);
        throw $e;
    }
}

function complete_duitku_transaction(PDO $pdo, int $transactionId): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM app_transaksi WHERE id=? AND payment_method='duitku' FOR UPDATE");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        if (!$transaction || $transaction['status'] !== 'unpaid') throw new RuntimeException('Transaksi Duitku tidak ditemukan atau sudah diproses.');
        $total = (float) $transaction['total'];
        $stmt = $pdo->prepare("UPDATE app_transaksi SET status='paid', bayar=total, kembalian=0, paid_at=NOW() WHERE id=? AND status='unpaid'");
        $stmt->execute([$transactionId]);
        if ($stmt->rowCount() !== 1) throw new RuntimeException('Status transaksi berubah.');
        $stmt = $pdo->prepare('INSERT INTO app_laporan_transaksi (transaksi_id,tanggal,total,status) VALUES (?,CURDATE(),?,?)');
        $stmt->execute([$transactionId, $total, 'paid']);
        if ($transaction['pelanggan_id']) {
            $customerId = (int) $transaction['pelanggan_id'];
            $pdo->prepare('INSERT INTO app_log_pelanggan (pelanggan_id,transaksi_id,aksi) VALUES (?,?,?)')->execute([$customerId, $transactionId, 'Pembelian']);
            $pdo->prepare('INSERT INTO app_total_pelanggan (pelanggan_id,total_transaksi,total_belanja) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_transaksi=total_transaksi+1,total_belanja=total_belanja+VALUES(total_belanja)')->execute([$customerId, 1, $total]);
        }
        log_activity('transaksi', 'Menyelesaikan pembayaran Duitku ' . $transaction['nomor_transaksi']);
        $pdo->commit();
        return ['id' => $transactionId, 'number' => $transaction['nomor_transaksi'], 'total' => $total];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function cancel_duitku_transaction(PDO $pdo, int $transactionId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT status FROM app_transaksi WHERE id=? AND payment_method='duitku' FOR UPDATE");
        $stmt->execute([$transactionId]);
        if ($stmt->fetchColumn() !== 'unpaid') throw new RuntimeException('Transaksi Duitku tidak dapat dibatalkan.');
        $stmt = $pdo->prepare('SELECT barang_id,qty FROM app_detail_transaksi WHERE transaksi_id=?');
        $stmt->execute([$transactionId]);
        foreach ($stmt->fetchAll() as $row) $pdo->prepare('UPDATE barang SET stok=stok+? WHERE id=? AND aktif=1')->execute([(int) $row['qty'], (int) $row['barang_id']]);
        $pdo->prepare("UPDATE app_transaksi SET status='cancelled' WHERE id=? AND status='unpaid'")->execute([$transactionId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function complete_qris_transaction(PDO $pdo, int $transactionId): array
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM app_transaksi WHERE id=? AND payment_method='qris' FOR UPDATE");
        $stmt->execute([$transactionId]);
        $transaction = $stmt->fetch();
        if (!$transaction || $transaction['status'] !== 'unpaid') {
            throw new RuntimeException('Transaksi QRIS tidak ditemukan atau sudah diproses.');
        }

        $total = (float) $transaction['total'];
        $stmt = $pdo->prepare("UPDATE app_transaksi SET status='paid', bayar=total, kembalian=0, paid_at=NOW() WHERE id=? AND status='unpaid'");
        $stmt->execute([$transactionId]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Status transaksi berubah. Muat ulang halaman.');
        }

        $stmt = $pdo->prepare('INSERT INTO app_laporan_transaksi (transaksi_id,tanggal,total,status) VALUES (?,CURDATE(),?,?)');
        $stmt->execute([$transactionId, $total, 'paid']);

        if ($transaction['pelanggan_id']) {
            $customerId = (int) $transaction['pelanggan_id'];
            $stmt = $pdo->prepare('INSERT INTO app_log_pelanggan (pelanggan_id,transaksi_id,aksi) VALUES (?,?,?)');
            $stmt->execute([$customerId, $transactionId, 'Pembelian']);
            $stmt = $pdo->prepare('INSERT INTO app_total_pelanggan (pelanggan_id,total_transaksi,total_belanja) VALUES (?,?,?) ON DUPLICATE KEY UPDATE total_transaksi=total_transaksi+1,total_belanja=total_belanja+VALUES(total_belanja)');
            $stmt->execute([$customerId, 1, $total]);
        }

        log_activity('transaksi', 'Mengonfirmasi pembayaran QRIS ' . $transaction['nomor_transaksi']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

    return ['id' => $transactionId, 'number' => $transaction['nomor_transaksi'], 'total' => $total];
}

function cancel_qris_transaction(PDO $pdo, int $transactionId): void
{
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT status FROM app_transaksi WHERE id=? AND payment_method='qris' FOR UPDATE");
        $stmt->execute([$transactionId]);
        if ($stmt->fetchColumn() !== 'unpaid') {
            throw new RuntimeException('Transaksi QRIS tidak ditemukan atau sudah diproses.');
        }

        $stmt = $pdo->prepare('SELECT barang_id,qty FROM app_detail_transaksi WHERE transaksi_id=?');
        $stmt->execute([$transactionId]);
        foreach ($stmt->fetchAll() as $row) {
            $pdo->prepare('UPDATE barang SET stok=stok+? WHERE id=? AND aktif=1')->execute([(int) $row['qty'], (int) $row['barang_id']]);
        }

        $pdo->prepare("UPDATE app_transaksi SET status='cancelled' WHERE id=? AND status='unpaid'")->execute([$transactionId]);
        log_activity('qris_cancelled', 'Membatalkan transaksi QRIS #' . $transactionId);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }

}
