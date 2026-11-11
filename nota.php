<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int) ($_GET['view'] ?? 0);
$stmt = db()->prepare('SELECT t.*,u.nama kasir,p.nama_pelanggan pelanggan FROM app_transaksi t JOIN app_users u ON u.id=t.user_id LEFT JOIN pelanggan p ON p.id_pelanggan=t.pelanggan_id WHERE t.id=?');
$stmt->execute([$id]);
$transaction = $stmt->fetch();
if (!$transaction) { http_response_code(404); exit('Transaksi tidak ditemukan.'); }
$stmt = db()->prepare('SELECT * FROM app_detail_transaksi WHERE transaksi_id=? ORDER BY id');
$stmt->execute([$id]);
$details = $stmt->fetchAll();

function receipt_amount(mixed $amount): string
{
    return number_format((float) $amount, 0, ',', '.');
}
?><!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Nota <?= e($transaction['nomor_transaksi']) ?></title>
    <style>
        *{box-sizing:border-box}
        html,body{margin:0;padding:0;color:#111;font-family:"Courier New",monospace;font-size:9px}
        body{background:#eef1f5}
        .receipt{width:58mm;min-height:40mm;margin:12px auto;background:#fff;padding:3mm 2mm;overflow-wrap:break-word}
        .center{text-align:center}.brand{font-size:14px;font-weight:700;letter-spacing:1px;margin:0}.store-subtitle{font-size:8px;margin:1px 0 4px}.address{font-size:8px;margin:0 0 5px}
        .line{border-top:1px dashed #111;margin:4px 0}
        .meta{display:flex;justify-content:space-between;gap:4px;font-size:8px;white-space:nowrap;line-height:1.3}.meta span{min-width:0;overflow:hidden;text-overflow:ellipsis}.meta span:last-child{text-align:right}
        .items-head,.item{display:grid;grid-template-columns:minmax(0,1fr) 4ch 8ch 10ch;column-gap:1px;align-items:start;line-height:1.25}.items-head{font-weight:700;margin-bottom:3px}.item{margin:2px 0}.item-name{min-width:0;overflow-wrap:anywhere}.qty{text-align:center}.price,.item-total{text-align:right;white-space:nowrap}
        .summary{width:100%;max-width:43mm;margin-left:auto}.summary-row{display:flex;justify-content:space-between;gap:5px;margin:2px 0;white-space:nowrap;line-height:1.25}.summary-row span:last-child{min-width:18mm;text-align:right}.grand-total{font-size:11px;font-weight:700;margin-top:4px}.saving{text-align:right;margin:3px 0;font-weight:700}.thanks{text-align:center;margin:7px 0 2px;font-size:8px;font-weight:700}.footer{text-align:center;font-size:8px;line-height:1.35;margin:0}
        .print-actions{text-align:center;margin:12px auto}.print-actions button,.print-actions a{border:0;border-radius:5px;background:#2764e7;color:#fff;padding:8px 12px;text-decoration:none;font:12px Segoe UI,Arial,sans-serif;cursor:pointer}.print-actions a{background:#758297;margin-left:5px}
        @page{size:58mm auto;margin:0}
        @media print{html,body{width:58mm;margin:0;padding:0;background:#fff}.receipt{width:58mm;min-height:40mm;margin:0;padding:3mm 2mm;background:#fff;overflow:visible}.print-actions{display:none}}
    </style>
</head>
<body>
<main class="receipt">
    <header class="center"><p class="brand"><?= e(APP_NAME) ?></p><p class="store-subtitle">POINT OF SALE</p><p class="address">NOTA PENJUALAN</p></header>
    <div class="meta"><span><?= e(date('d/m/Y H:i', strtotime($transaction['created_at']))) ?></span><span><?= e($transaction['nomor_transaksi']) ?></span></div>
    <div class="meta"><span>KASIR: <?= e($transaction['kasir']) ?></span><span><?= e($transaction['pelanggan'] ?: 'UMUM') ?></span></div>
    <div class="line"></div>
    <div class="items-head"><span>BARANG</span><span class="qty">QTY</span><span class="price">HARGA</span><span class="item-total">TOTAL</span></div>
    <?php foreach ($details as $detail): ?><div class="item"><span class="item-name"><?= e(strtoupper($detail['nama_barang'])) ?></span><span class="qty"><?= e($detail['qty']) ?></span><span class="price"><?= receipt_amount($detail['harga']) ?></span><span class="item-total"><?= receipt_amount($detail['subtotal']) ?></span></div><?php endforeach; ?>
    <div class="line"></div>
    <section class="summary"><div class="summary-row"><span>HARGA JUAL :</span><span><?= receipt_amount($transaction['subtotal']) ?></span></div><div class="summary-row"><span>DISKON :</span><span>(<?= receipt_amount($transaction['diskon']) ?>)</span></div><div class="line"></div><div class="summary-row grand-total"><span>TOTAL :</span><span><?= receipt_amount($transaction['total']) ?></span></div><div class="summary-row"><span>TUNAI :</span><span><?= receipt_amount($transaction['bayar']) ?></span></div><div class="summary-row"><span>KEMBALI :</span><span><?= receipt_amount($transaction['kembalian']) ?></span></div><?php if ((float) $transaction['diskon'] > 0): ?><div class="saving">ANDA HEMAT : <?= receipt_amount($transaction['diskon']) ?></div><?php endif; ?></section>
    <div class="line"></div><p class="thanks">TERIMA KASIH. SELAMAT BELANJA KEMBALI</p><p class="footer">Simpan nota ini sebagai bukti pembayaran.<br><?= e(APP_NAME) ?></p>
</main>
<div class="print-actions"><button type="button" onclick="window.print()">Cetak nota</button><a href="<?= e(url('transaksi.php?view=' . $transaction['id'])) ?>">Kembali</a></div>
<?php if (($_GET['print'] ?? '') === '1'): ?><script>window.addEventListener('load',function(){setTimeout(function(){window.print()},250)});</script><?php endif; ?>
</body>
</html>
