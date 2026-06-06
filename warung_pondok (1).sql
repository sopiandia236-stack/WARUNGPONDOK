-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jun 06, 2026 at 02:25 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `warung_pondok`
--

-- --------------------------------------------------------

--
-- Table structure for table `barang`
--

CREATE TABLE `barang` (
  `id` int(11) NOT NULL,
  `kode_barang` varchar(50) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `kategori` enum('Kopi/Minuman','Rokok','Cemilan','Mie','Alat Tulis','Buku & Kitab','Lainnya') NOT NULL,
  `harga_beli` int(11) DEFAULT NULL,
  `harga_jual` int(11) DEFAULT NULL,
  `satuan_dasar` varchar(20) NOT NULL COMMENT 'batang/pcs/bungkus',
  `harga_per_satuan_dasar` decimal(10,2) DEFAULT NULL,
  `isi_per_bungkus` int(11) DEFAULT 1,
  `harga_per_bungkus` decimal(10,2) DEFAULT NULL,
  `isi_per_pak` int(11) DEFAULT 1,
  `harga_per_pak` decimal(10,2) DEFAULT NULL,
  `isi_per_dus` int(11) DEFAULT 1,
  `harga_per_dus` decimal(10,2) DEFAULT NULL,
  `isi_per_enceng` int(11) DEFAULT 1,
  `harga_per_enceng` decimal(10,2) DEFAULT NULL,
  `stok` int(11) NOT NULL DEFAULT 0,
  `stok_minimum` int(11) NOT NULL DEFAULT 5,
  `stok_maksimum` int(11) NOT NULL DEFAULT 100,
  `terakhir_terjual` date DEFAULT NULL,
  `opsi1_nama` varchar(100) DEFAULT NULL,
  `opsi1_harga` int(11) DEFAULT NULL,
  `opsi2_nama` varchar(100) DEFAULT NULL,
  `opsi2_harga` int(11) DEFAULT NULL,
  `opsi3_nama` varchar(100) DEFAULT NULL,
  `opsi3_harga` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barang`
--

INSERT INTO `barang` (`id`, `kode_barang`, `nama_barang`, `kategori`, `harga_beli`, `harga_jual`, `satuan_dasar`, `harga_per_satuan_dasar`, `isi_per_bungkus`, `harga_per_bungkus`, `isi_per_pak`, `harga_per_pak`, `isi_per_dus`, `harga_per_dus`, `isi_per_enceng`, `harga_per_enceng`, `stok`, `stok_minimum`, `stok_maksimum`, `terakhir_terjual`, `opsi1_nama`, `opsi1_harga`, `opsi2_nama`, `opsi2_harga`, `opsi3_nama`, `opsi3_harga`) VALUES
(1, 'RKG001', 'Rokok Surya 16', 'Rokok', 20000, 25000, 'batang', 1700.00, 16, 25000.00, 160, 240000.00, 1600, 2200000.00, 16000, 20000000.00, 500, 50, 2000, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'KOPI001', 'Kopi Kapal Api', 'Kopi/Minuman', 5000, 7000, 'pcs', 700.00, 12, 7000.00, 48, 26000.00, 480, 250000.00, 4800, 2400000.00, 99, 10, 500, '2026-06-02', NULL, NULL, NULL, NULL, NULL, NULL),
(3, 'MCD001', 'Indomie Goreng', 'Cemilan', 2500, 3500, 'pcs', 3500.00, 1, 3500.00, 40, 130000.00, 400, 1250000.00, 4000, 12000000.00, 200, 20, 1000, NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(4, '6764683', 'kopi', 'Kopi/Minuman', 2000, 0, '', NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, -5, 50, 100, '2026-06-05', 'sachet', 2000, 'di seduh', 999, '', NULL),
(5, '6756', 'mie instan soto', 'Mie', 3000, 0, '', NULL, 1, NULL, 1, NULL, 1, NULL, 1, NULL, 50, 75, 100, NULL, 'sachet', 4000, 'di seduh', 5000, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `detail_penjualan`
--

CREATE TABLE `detail_penjualan` (
  `id` int(11) NOT NULL,
  `penjualan_id` int(11) DEFAULT NULL,
  `barang_id` int(11) DEFAULT NULL,
  `jumlah` int(11) NOT NULL,
  `satuan_terpakai` varchar(20) NOT NULL,
  `harga_saat_jual` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `opsi_terpakai` varchar(50) DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_penjualan`
--

INSERT INTO `detail_penjualan` (`id`, `penjualan_id`, `barang_id`, `jumlah`, `satuan_terpakai`, `harga_saat_jual`, `subtotal`, `opsi_terpakai`) VALUES
(1, 1, 2, 1, 'pcs', 7000.00, 7000.00, ''),
(2, 2, 4, 1, '', 2000.00, 2000.00, 'sachet'),
(10, 10, 4, 1, '', 999.00, 999.00, 'di seduh'),
(11, 11, 4, 1, '', 999.00, 999.00, 'di seduh');

-- --------------------------------------------------------

--
-- Table structure for table `kas_warung`
--

CREATE TABLE `kas_warung` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jenis` enum('pemasukan','pengeluaran') NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `saldo_akhir` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kas_warung`
--

INSERT INTO `kas_warung` (`id`, `tanggal`, `jenis`, `keterangan`, `jumlah`, `saldo_akhir`) VALUES
(1, '2026-06-02', 'pemasukan', 'Penjualan TRX20260602090100572', 7000.00, 7000.00),
(2, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603152123315', 2000.00, 9000.00),
(3, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603155839587', 2000.00, 11000.00),
(4, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603160640386', 2000.00, 13000.00),
(5, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603172446681', 2000.00, 15000.00),
(6, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603172544784', 2000.00, 17000.00),
(7, '2026-06-03', 'pemasukan', 'Penjualan TRX20260603172650101', 12000.00, 29000.00),
(8, '2026-06-05', 'pemasukan', 'Penjualan TRX20260605123407478', 999.00, 29999.00),
(9, '2026-06-05', 'pemasukan', 'Penjualan TRX20260605123433489', 2000.00, 31999.00),
(10, '2026-06-05', 'pemasukan', 'Penjualan TRX20260605135051244', 999.00, 32998.00),
(11, '2026-06-05', 'pemasukan', 'Penjualan TRX20260605135136168', 999.00, 33997.00);

-- --------------------------------------------------------

--
-- Table structure for table `pelanggan`
--

CREATE TABLE `pelanggan` (
  `id` int(11) NOT NULL,
  `kode_pelanggan` varchar(20) NOT NULL,
  `nama_pelanggan` varchar(100) NOT NULL,
  `no_telp` varchar(15) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `total_belanja` decimal(10,2) DEFAULT 0.00,
  `terakhir_belanja` date DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pelanggan`
--

INSERT INTO `pelanggan` (`id`, `kode_pelanggan`, `nama_pelanggan`, `no_telp`, `alamat`, `total_belanja`, `terakhir_belanja`, `status`) VALUES
(1, 'PLG001', 'Ahmad Faizal', '08123456701', 'Jl. Merdeka No.10', 7000.00, '2026-06-02', 'nonaktif'),
(4, 'PLG6187', 'Andi sopiandi', '08123456789', 'carulang', 4000.00, '2026-06-03', 'nonaktif'),
(13, 'PLG4615', 'asep', '08123456789', '5', 0.00, NULL, 'aktif'),
(14, 'PLG7247', 'KAKA', '08123456789', '3', 0.00, NULL, 'aktif');

-- --------------------------------------------------------

--
-- Table structure for table `pengeluaran`
--

CREATE TABLE `pengeluaran` (
  `id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `keterangan` varchar(255) DEFAULT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `jenis` enum('beli_stok','operasional','gaji_karyawan','lainnya') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `penjualan`
--

CREATE TABLE `penjualan` (
  `id` int(11) NOT NULL,
  `no_faktur` varchar(50) NOT NULL,
  `tanggal` datetime NOT NULL,
  `pelanggan_id` int(11) DEFAULT NULL,
  `karyawan_id` int(11) DEFAULT NULL,
  `total_harga` decimal(10,2) NOT NULL,
  `uang_bayar` decimal(10,2) NOT NULL,
  `uang_kembali` decimal(10,2) NOT NULL,
  `santri_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penjualan`
--

INSERT INTO `penjualan` (`id`, `no_faktur`, `tanggal`, `pelanggan_id`, `karyawan_id`, `total_harga`, `uang_bayar`, `uang_kembali`, `santri_id`) VALUES
(1, 'TRX20260602090100572', '2026-06-02 00:01:00', 1, 1, 7000.00, 10000.00, 3000.00, NULL),
(2, 'TRX20260603152123315', '2026-06-03 06:21:23', 4, 2, 2000.00, 10000.00, 8000.00, NULL),
(10, 'TRX20260605135051244', '2026-06-05 04:50:51', NULL, 1, 999.00, 10000.00, 9001.00, NULL),
(11, 'TRX20260605135136168', '2026-06-05 04:51:36', NULL, 1, 999.00, 50000.00, 49001.00, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `profil_toko`
--

CREATE TABLE `profil_toko` (
  `id` int(11) NOT NULL DEFAULT 1,
  `nama_toko` varchar(100) NOT NULL DEFAULT 'Warung Pondok',
  `alamat` text DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `logo` varchar(100) DEFAULT NULL,
  `footer_text` varchar(255) DEFAULT 'Terima kasih atas kunjungan Anda',
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `profil_toko`
--

INSERT INTO `profil_toko` (`id`, `nama_toko`, `alamat`, `no_telp`, `email`, `logo`, `footer_text`, `created_at`, `updated_at`) VALUES
(1, 'Warung Pondok', 'Jl.Babakan Negla RT/RW 01/04 Desa Cipangramatan Kec Cikajang Garut Jawa Barat', '08123456789', 'warungpondok@gmail.com', 'uploads/logo_1780654877.jpeg', 'Terima kasih atas kunjungan Anda', '2026-06-01 23:49:28', '2026-06-05 03:22:15');

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_jajan_santri`
--

CREATE TABLE `riwayat_jajan_santri` (
  `id` int(11) NOT NULL,
  `santri_id` int(11) DEFAULT NULL,
  `penjualan_id` int(11) DEFAULT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `sisa_saldo` decimal(10,2) NOT NULL,
  `tanggal` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `riwayat_jatah_harian`
--

CREATE TABLE `riwayat_jatah_harian` (
  `id` int(11) NOT NULL,
  `santri_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jatah_harian` decimal(10,2) DEFAULT 0.00,
  `terpakai` decimal(10,2) DEFAULT 0.00,
  `sisa` decimal(10,2) DEFAULT 0.00,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `riwayat_jatah_harian`
--

INSERT INTO `riwayat_jatah_harian` (`id`, `santri_id`, `tanggal`, `jatah_harian`, `terpakai`, `sisa`, `created_at`) VALUES
(17, 5, '2026-06-05', 10000.00, 0.00, 10000.00, '2026-06-05 08:00:39');

-- --------------------------------------------------------

--
-- Table structure for table `santri`
--

CREATE TABLE `santri` (
  `id` int(11) NOT NULL,
  `nis` varchar(20) NOT NULL,
  `nama_santri` varchar(100) NOT NULL,
  `kelas` varchar(20) DEFAULT NULL,
  `jatah_harian` decimal(10,2) DEFAULT 10000.00,
  `saldo_tabungan` decimal(10,2) DEFAULT 0.00,
  `tanggal_reset` date DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `total_tabungan` decimal(10,2) DEFAULT 0.00,
  `jumlah_hari_jatah` int(11) DEFAULT 30,
  `jatah_per_hari` decimal(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `santri`
--

INSERT INTO `santri` (`id`, `nis`, `nama_santri`, `kelas`, `jatah_harian`, `saldo_tabungan`, `tanggal_reset`, `created_at`, `total_tabungan`, `jumlah_hari_jatah`, `jatah_per_hari`) VALUES
(5, '01', 'kaka', '4', 10000.00, 50000.00, '2026-06-05', '2026-06-05 08:00:39', 50000.00, 30, 10000.00);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','karyawan') NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `no_telp` varchar(15) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `nama_lengkap`, `no_telp`) VALUES
(1, 'admin', '$2y$10$.cK8iuMoMqE3p6DI/LDcie.JyqWFkMza0aO4fyC6tBpSYpT0bsXIy', 'admin', 'Pemilik Warung', '08123456789'),
(2, 'karyawan1', '$2y$10$uMxBpasvMl3E4RsvDdJ.Ze2CHK11/5LDy6SCVx2TjBdtJz1nkgYMW', 'karyawan', 'jaenal', '08123456788'),
(3, 'karyawan2', '123456', 'karyawan', 'endin ', '08123456787');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barang`
--
ALTER TABLE `barang`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`);

--
-- Indexes for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `penjualan_id` (`penjualan_id`),
  ADD KEY `barang_id` (`barang_id`);

--
-- Indexes for table `kas_warung`
--
ALTER TABLE `kas_warung`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `pelanggan`
--
ALTER TABLE `pelanggan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode_pelanggan` (`kode_pelanggan`);

--
-- Indexes for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `no_faktur` (`no_faktur`),
  ADD KEY `pelanggan_id` (`pelanggan_id`),
  ADD KEY `karyawan_id` (`karyawan_id`),
  ADD KEY `santri_id` (`santri_id`);

--
-- Indexes for table `profil_toko`
--
ALTER TABLE `profil_toko`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `riwayat_jajan_santri`
--
ALTER TABLE `riwayat_jajan_santri`
  ADD PRIMARY KEY (`id`),
  ADD KEY `santri_id` (`santri_id`),
  ADD KEY `penjualan_id` (`penjualan_id`);

--
-- Indexes for table `riwayat_jatah_harian`
--
ALTER TABLE `riwayat_jatah_harian`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_santri_tanggal` (`santri_id`,`tanggal`);

--
-- Indexes for table `santri`
--
ALTER TABLE `santri`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nis` (`nis`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `barang`
--
ALTER TABLE `barang`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `kas_warung`
--
ALTER TABLE `kas_warung`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `pelanggan`
--
ALTER TABLE `pelanggan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `pengeluaran`
--
ALTER TABLE `pengeluaran`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `penjualan`
--
ALTER TABLE `penjualan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `riwayat_jajan_santri`
--
ALTER TABLE `riwayat_jajan_santri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `riwayat_jatah_harian`
--
ALTER TABLE `riwayat_jatah_harian`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `santri`
--
ALTER TABLE `santri`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `detail_penjualan`
--
ALTER TABLE `detail_penjualan`
  ADD CONSTRAINT `detail_penjualan_ibfk_1` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`),
  ADD CONSTRAINT `detail_penjualan_ibfk_2` FOREIGN KEY (`barang_id`) REFERENCES `barang` (`id`);

--
-- Constraints for table `penjualan`
--
ALTER TABLE `penjualan`
  ADD CONSTRAINT `penjualan_ibfk_1` FOREIGN KEY (`pelanggan_id`) REFERENCES `pelanggan` (`id`),
  ADD CONSTRAINT `penjualan_ibfk_2` FOREIGN KEY (`karyawan_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `penjualan_ibfk_3` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`);

--
-- Constraints for table `riwayat_jajan_santri`
--
ALTER TABLE `riwayat_jajan_santri`
  ADD CONSTRAINT `riwayat_jajan_santri_ibfk_1` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`),
  ADD CONSTRAINT `riwayat_jajan_santri_ibfk_2` FOREIGN KEY (`penjualan_id`) REFERENCES `penjualan` (`id`);

--
-- Constraints for table `riwayat_jatah_harian`
--
ALTER TABLE `riwayat_jatah_harian`
  ADD CONSTRAINT `riwayat_jatah_harian_ibfk_1` FOREIGN KEY (`santri_id`) REFERENCES `santri` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
