-- TokoKu POS - skema tambahan yang aman untuk database toko Anda.
-- Database lama tetap dipertahankan; tabel aplikasi baru memakai prefix app_.
CREATE DATABASE IF NOT EXISTS `toko` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `toko`;

-- Tabel pelanggan lama pada database Anda memakai id_pelanggan/nama_pelanggan/no_hp.
CREATE TABLE IF NOT EXISTS pelanggan (
  id_pelanggan INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nama_pelanggan VARCHAR(100) NOT NULL,
  alamat VARCHAR(150) NULL,
  no_hp VARCHAR(15) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nama VARCHAR(100) NOT NULL,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  aktif TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS barang (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  kode_barang VARCHAR(40) NOT NULL UNIQUE,
  nama_barang VARCHAR(150) NOT NULL,
  kategori VARCHAR(80) NULL,
  harga DECIMAL(14,2) NOT NULL DEFAULT 0,
  stok INT NOT NULL DEFAULT 0,
  satuan VARCHAR(20) NOT NULL DEFAULT 'pcs',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE barang ADD COLUMN IF NOT EXISTS foto VARCHAR(255) NULL AFTER satuan;
ALTER TABLE barang ADD COLUMN IF NOT EXISTS aktif TINYINT(1) NOT NULL DEFAULT 1 AFTER foto;

CREATE TABLE IF NOT EXISTS app_transaksi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nomor_transaksi VARCHAR(60) NOT NULL UNIQUE,
  pelanggan_id INT NULL,
  user_id INT UNSIGNED NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  diskon DECIMAL(14,2) NOT NULL DEFAULT 0,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  bayar DECIMAL(14,2) NOT NULL DEFAULT 0,
  kembalian DECIMAL(14,2) NOT NULL DEFAULT 0,
  status ENUM('unpaid','paid','cancelled') NOT NULL DEFAULT 'unpaid',
  paid_at DATETIME NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_trx_pelanggan FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id_pelanggan) ON DELETE SET NULL,
  CONSTRAINT fk_app_trx_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE RESTRICT,
  INDEX idx_app_trx_tanggal (created_at), INDEX idx_app_trx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_detail_transaksi (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT UNSIGNED NOT NULL,
  barang_id INT UNSIGNED NOT NULL,
  nama_barang VARCHAR(150) NOT NULL,
  harga DECIMAL(14,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL,
  subtotal DECIMAL(14,2) NOT NULL DEFAULT 0,
  CONSTRAINT fk_app_detail_trx FOREIGN KEY (transaksi_id) REFERENCES app_transaksi(id) ON DELETE RESTRICT,
  CONSTRAINT fk_app_detail_barang FOREIGN KEY (barang_id) REFERENCES barang(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_log_kasir (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  aksi VARCHAR(80) NOT NULL,
  keterangan VARCHAR(255) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_log_user FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE RESTRICT,
  INDEX idx_app_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_log_pelanggan (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  pelanggan_id INT NOT NULL,
  transaksi_id INT UNSIGNED NULL,
  aksi VARCHAR(80) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_log_pelanggan FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id_pelanggan) ON DELETE RESTRICT,
  CONSTRAINT fk_app_log_pelanggan_trx FOREIGN KEY (transaksi_id) REFERENCES app_transaksi(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_total_pelanggan (
  pelanggan_id INT PRIMARY KEY,
  total_transaksi INT NOT NULL DEFAULT 0,
  total_belanja DECIMAL(14,2) NOT NULL DEFAULT 0,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_total_pelanggan FOREIGN KEY (pelanggan_id) REFERENCES pelanggan(id_pelanggan) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS app_laporan_transaksi (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  transaksi_id INT UNSIGNED NOT NULL,
  tanggal DATE NOT NULL,
  total DECIMAL(14,2) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_app_laporan_trx FOREIGN KEY (transaksi_id) REFERENCES app_transaksi(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO app_users (nama, username, password_hash, role) VALUES
('Administrator', 'admin', '$2y$10$CAr8vV7/oPNaGE/.qGyArO.0e4dJ2SVXsk.BvqztAMr3ljbiEucci', 'admin'),
('Kasir Utama', 'kasir', '$2y$10$ruahDyS7X2X89wtgwXHyru9FNiMh8YChiv4zXifjpgMh6.XdYMtLi', 'kasir')
ON DUPLICATE KEY UPDATE nama=VALUES(nama), password_hash=VALUES(password_hash), role=VALUES(role), aktif=1;

INSERT INTO pelanggan (nama_pelanggan, alamat, no_hp)
SELECT 'Pelanggan Umum', NULL, NULL
WHERE NOT EXISTS (SELECT 1 FROM pelanggan WHERE nama_pelanggan='Pelanggan Umum');

INSERT INTO barang (kode_barang, nama_barang, kategori, harga, stok, satuan) VALUES
('BRG-001','Air Mineral 600ml','Minuman',4000,50,'botol'),
('BRG-002','Mie Instan Goreng','Makanan',3500,40,'pcs'),
('BRG-003','Kopi Sachet','Minuman',2500,30,'sachet')
ON DUPLICATE KEY UPDATE nama_barang=VALUES(nama_barang);
