# TokoKu POS

Aplikasi kasir berbasis PHP native dan MySQL untuk XAMPP. Aplikasi ini memiliki dua role pengguna: `admin` dan `kasir`.

## Teknologi

- PHP 8+
- MySQL/MariaDB
- PDO
- HTML dan CSS native
- Session login

## Instalasi

1. Jalankan Apache dan MySQL melalui XAMPP.
2. Pastikan database bernama `toko` tersedia.
3. Import [database.sql](database.sql) melalui phpMyAdmin.
4. Buka [http://localhost/toko-ku/](http://localhost/toko-ku/).

File SQL tidak menghapus tabel lama. Data aplikasi baru menggunakan tabel ber-prefix `app_`, sedangkan tabel `pelanggan` lama tetap digunakan.

## Konfigurasi database

Konfigurasi database berada di [config/config.php](config/config.php):

```php
const DB_HOST = '127.0.0.1';
const DB_NAME = 'toko';
const DB_USER = 'root';
const DB_PASS = '';
```

Sesuaikan `DB_USER` dan `DB_PASS` jika MySQL Anda memakai password.

## Login awal

| Role | Username | Password |
|---|---|---|
| Admin | `admin` | `admin123` |
| Kasir | `kasir` | `kasir123` |
| Kasir 1 | `kasir1` | `kasir123` |
| Kasir 2 | `kasir2` | `kasir123` |
| Kasir 3 | `kasir3` | `kasir123` |

Segera ganti password default pada database untuk penggunaan nyata.

## Payment gateway Duitku

Checkout pelanggan menyediakan pembayaran melalui Duitku. Selain `API_KEY`, `MERHANT` (atau `MERCHANT`), dan `MODE`, atur URL publik callback agar Duitku dapat mengirim notifikasi pembayaran:

```env
DUITKU_PAYMENT_METHOD=VC
DUITKU_CALLBACK_URL=https://domain-anda/toko-ku/duitku-callback.php
DUITKU_RETURN_URL=https://domain-anda/toko-ku/duitku-return.php
```

`DUITKU_CALLBACK_URL` wajib dapat diakses dari internet. Status transaksi hanya diubah setelah signature callback dan nominal transaksi tervalidasi.

## Fitur utama

- Dashboard penjualan dan stok.
- Data barang dengan foto JPG, PNG, atau WEBP maksimal 2 MB.
- Foto barang ditampilkan dengan rasio 4:5.
- Kategori barang: `Sepatu`, `Jaket`, dan `Perlengkapan`.
- Pencarian dan filter barang di halaman kasir.
- Keranjang belanja melalui ikon di pojok kanan atas.
- Perubahan jumlah barang, pengosongan keranjang, dan pembayaran dari keranjang.
- Pembayaran tunai dan QRIS menggunakan gambar QR statis milik toko.
- Transaksi QRIS menunggu konfirmasi kasir setelah pembayaran diverifikasi; stok dikembalikan jika transaksi dibatalkan.
- Stok tunai berkurang setelah pembayaran; stok QRIS ditahan selama menunggu konfirmasi dan dikembalikan jika dibatalkan.
- Format harga mendukung `3.000`, `4.000,00`, dan `1.250.000`.
- Riwayat transaksi dan detail pembayaran.
- Nota thermal lebar 58 mm dengan tinggi mengikuti isi transaksi.
- Laporan transaksi per akun kasir atau semua kasir, dapat dicetak.
- Log aktivitas kasir.
- Logout dan proteksi role di sisi server.

## Aturan akses

### Kasir

- Membuat transaksi dan melakukan pembayaran.
- Menambah atau mengubah barang.
- Tidak dapat menghapus barang.
- Tidak dapat menghapus transaksi.
- Hanya dapat melihat riwayat, detail, nota, dan transaksi QRIS miliknya sendiri.
- Tidak dapat membuka laporan dan log aktivitas admin.

### Admin

- Memiliki akses ke seluruh fitur aplikasi.
- Dapat melihat riwayat transaksi dari semua kasir.
- Dapat menghapus barang yang belum pernah digunakan dalam transaksi.
- Barang yang sudah masuk transaksi akan dinonaktifkan, bukan dihapus permanen, agar nota lama tetap aman.
- Transaksi berstatus `paid` tidak dapat dihapus, termasuk oleh admin.

## Cetak nota

1. Selesaikan transaksi di menu **Kasir**.
2. Buka detail transaksi.
3. Klik **Cetak nota 58x40 mm**.
4. Pada dialog printer gunakan pengaturan:
   - Lebar kertas: `58 mm`
   - Scale: `100%`
   - Margin: `None`
   - Header dan footer browser: `Off`

Nota mencetak semua barang, jumlah, harga, subtotal, diskon, total, tunai, dan kembalian.

## Pembayaran QRIS

1. Tambahkan barang ke keranjang, buka ikon keranjang, lalu klik **Bayar dengan QRIS**.
2. Pelanggan memindai QR dan memasukkan nominal yang ditampilkan. Nominal tidak terisi otomatis.
3. Kasir memeriksa pembayaran pada aplikasi/riwayat merchant, lalu klik **Konfirmasi pembayaran masuk**. Jangan konfirmasi hanya berdasarkan bukti layar pelanggan.
4. Jika transaksi dibatalkan, stok yang ditahan akan dikembalikan. Transaksi yang belum selesai bisa dilanjutkan dari detail transaksi.

Gunakan QR merchant yang berlaku tetap. Jika foto yang dikirim berasal dari permintaan pembayaran sekali pakai atau sudah kedaluwarsa, ganti `assets/images/qris.png` dengan gambar QR merchant yang valid.

Untuk instalasi lama, tambahkan kolom metode pembayaran melalui phpMyAdmin atau MySQL:

```sql
ALTER TABLE app_transaksi
ADD COLUMN IF NOT EXISTS payment_method VARCHAR(20) NOT NULL DEFAULT 'cash' AFTER bayar;
```

Database baru mendapatkan kolom tersebut saat menjalankan `database.sql`.

## Struktur penting

```text
config/             Konfigurasi aplikasi dan koneksi database
includes/           Auth, header, footer, dan helper transaksi
assets/style.css    Style utama
assets/theme.css    Tema visual modern
assets/uploads/     Penyimpanan foto barang
assets/images/qris.png  QRIS statis toko
barang.php          Manajemen barang
kasir.php           Transaksi, scan barcode, dan pembayaran
qris.php            Pembayaran QRIS dan konfirmasi kasir
transaksi.php       Riwayat transaksi
nota.php            Format cetak nota
laporan.php         Laporan penjualan
log.php             Log aktivitas admin
database.sql        Struktur dan data awal database
```

## Troubleshooting

**Halaman database error**

Pastikan MySQL aktif, database `toko` tersedia, dan konfigurasi pada `config/config.php` benar.

**Foto tidak tersimpan**

Pastikan format file JPG/PNG/WEBP, ukuran maksimal 2 MB, dan folder `assets/uploads/barang` dapat ditulis oleh Apache.

**Barang tidak bisa dihapus**

Barang yang sudah digunakan dalam transaksi memang dinonaktifkan agar riwayat transaksi tidak rusak.
