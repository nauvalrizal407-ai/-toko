<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/transactions.php';
require_login();

$orderNumber = trim((string) ($_GET['merchantOrderId'] ?? ''));
$message = 'Pembayaran sedang diproses. Status akan diperbarui setelah gateway mengonfirmasi transaksi.';
$result = 'pending';
$historyUrl = 'riwayat.php';
try {
    if ($orderNumber !== '') {
        $pdo = db();
        $stmt = $pdo->prepare("SELECT id,total,status,user_id FROM app_transaksi WHERE nomor_transaksi=? AND payment_method='duitku' LIMIT 1");
        $stmt->execute([$orderNumber]);
        $transaction = $stmt->fetch();
        if ($transaction && (is_admin() || (int) $transaction['user_id'] === (int) current_user()['id'])) {
            $status = duitku_status($orderNumber);
            if (($status['merchantOrderId'] ?? '') === $orderNumber && (int) round((float) ($status['amount'] ?? 0)) === (int) round((float) $transaction['total']) && ($status['statusCode'] ?? '') === '00') {
                complete_duitku_transaction($pdo, (int) $transaction['id']);
                $message = 'Pembayaran berhasil dikonfirmasi.';
                $result = 'success';
            }
            $historyUrl .= '?order=' . rawurlencode($orderNumber) . '&result=' . $result;
        }
    }
} catch (Throwable $e) {
    error_log('Duitku return status check failed: ' . $e->getMessage());
}
flash('success', $message);
header('Location: ' . url($historyUrl));
exit;
