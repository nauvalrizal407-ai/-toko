# TokoKu POS

Aplikasi kasir PHP native untuk XAMPP dengan role `admin` dan `kasir`.

## Instalasi singkat

1. Pastikan Apache dan MySQL XAMPP aktif.
2. Import file `database.sql` ke database `toko` melalui phpMyAdmin. File ini hanya menambah tabel aplikasi ber-prefix `app_` dan `barang`; tabel lama Anda tidak dihapus.
3. Jika konfigurasi MySQL berbeda, ubah `config/config.php`.
4. Buka `http://localhost/toko-ku/`.

Login awal:

- Admin: `admin` / `admin123`
- Kasir: `kasir` / `kasir123`

Data pelanggan memakai tabel `pelanggan` lama (`id_pelanggan`, `nama_pelanggan`, `no_hp`). Data transaksi baru disimpan terpisah di tabel `app_transaksi` dan `app_detail_transaksi` karena struktur `transaksi` lama Anda masih berupa data baris transaksi.

Aturan akses dijaga di server, bukan hanya disembunyikan dari menu:

- Kasir dapat menambah/mengubah barang dan membuat transaksi.
- Setiap barang dapat memiliki foto JPG, PNG, atau WEBP maksimal 2 MB.
- Nota transaksi dapat dicetak dengan format thermal 58 × 40 mm dari detail transaksi.
- Kasir tidak dapat menghapus barang atau transaksi. Barang yang sudah masuk transaksi akan dinonaktifkan oleh admin agar riwayat nota tetap aman.
- Transaksi `paid` tidak dapat dihapus, termasuk oleh admin.
- Admin dapat melihat laporan dan seluruh log aktivitas serta menghapus barang/transaksi yang belum `paid`.
