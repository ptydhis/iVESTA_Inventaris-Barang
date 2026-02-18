-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 18, 2026 at 10:37 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `ivesta`
--

-- --------------------------------------------------------

--
-- Table structure for table `t_barang`
--

CREATE TABLE `t_barang` (
  `id_barang` int(11) NOT NULL,
  `nama_barang` varchar(100) NOT NULL,
  `merk` varchar(50) NOT NULL,
  `unit` int(11) NOT NULL DEFAULT 1,
  `foto_barang` varchar(100) DEFAULT NULL,
  `tanggal_input` timestamp NOT NULL DEFAULT current_timestamp(),
  `milik` enum('Prodi','Lab','Lab 1','Lab 2','Lab 3') NOT NULL DEFAULT 'Prodi'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `t_barang`
--

INSERT INTO `t_barang` (`id_barang`, `nama_barang`, `merk`, `unit`, `foto_barang`, `tanggal_input`, `milik`) VALUES
(3, 'Laptop', 'Asus', 2, 'barang_683897847ecba6.62729150.png', '2025-05-25 18:52:13', 'Prodi'),
(4, 'Proyektor', 'Cannon', 2, 'barang_683890188b4036.90475859.png', '2025-05-25 20:32:45', 'Prodi'),
(5, 'Printer', 'Epson', 2, 'barang_68388fec0cc910.18598670.png', '2025-05-26 04:34:23', 'Lab 1'),
(14, 'Kamera', 'Cannon', 2, 'barang_6838f51a3c8d82.50371830.png', '2025-05-29 17:24:03', 'Lab 2'),
(15, 'Tripod Proyektor', 'Sharp', 2, 'barang_683d9f1b36f463.42400916.png', '2025-06-02 12:54:51', 'Prodi'),
(16, 'Speaker', 'Harman Kardon', 2, 'barang_683d9f5a90d387.02874745.png', '2025-06-02 12:55:54', 'Lab 3'),
(19, 'Laptop', 'Lenovo', 2, '', '2025-08-13 07:01:54', 'Prodi');

-- --------------------------------------------------------

--
-- Table structure for table `t_barang_detail`
--

CREATE TABLE `t_barang_detail` (
  `id_detail` int(11) NOT NULL,
  `id_barang` int(11) NOT NULL,
  `kode_barang` varchar(20) NOT NULL,
  `kondisi` enum('Baik','Rusak','Hilang') NOT NULL DEFAULT 'Baik',
  `status` enum('Tersedia','Dipinjam','Menunggu Verifikasi','Hilang','Dibooking') DEFAULT 'Tersedia',
  `tanggal_rusak` datetime DEFAULT NULL,
  `tanggal_hilang` datetime DEFAULT NULL,
  `tanggal_maintenance` datetime DEFAULT NULL,
  `note` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `t_barang_detail`
--

INSERT INTO `t_barang_detail` (`id_detail`, `id_barang`, `kode_barang`, `kondisi`, `status`, `tanggal_rusak`, `tanggal_hilang`, `tanggal_maintenance`, `note`) VALUES
(1, 3, 'KDA98F0-001', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(2, 3, 'KDA999B-002', 'Baik', 'Dipinjam', NULL, NULL, NULL, NULL),
(11, 4, 'KDBFD8B-001', 'Baik', 'Dibooking', NULL, NULL, NULL, NULL),
(12, 4, 'KDC0538-002', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(16, 5, 'KF86126-001', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(17, 5, 'KF865EF-002', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(96, 14, 'K3988D2-001', 'Hilang', 'Hilang', NULL, '2025-07-01 00:41:00', NULL, 'Hilang 1'),
(97, 14, 'K39912D-002', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(98, 15, 'KB39E96-001', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(99, 15, 'KB3BFE8-002', 'Rusak', 'Tersedia', '2025-06-29 09:59:00', NULL, '2025-07-01 00:40:00', 'Rusak 1'),
(100, 16, 'KA918B8-001', 'Hilang', 'Hilang', NULL, '2025-07-01 00:40:00', NULL, 'Hilang 2'),
(101, 16, 'KA91A44-002', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(106, 19, 'K26238D-001', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL),
(107, 19, 'K262518-002', 'Baik', 'Tersedia', NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `t_pinjam`
--

CREATE TABLE `t_pinjam` (
  `id_pinjam` int(11) NOT NULL,
  `id_detail` int(11) NOT NULL,
  `id_nip` varchar(20) NOT NULL,
  `tanggal_pinjam` datetime DEFAULT current_timestamp(),
  `tanggal_kembali` datetime DEFAULT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `status_peminjaman` enum('Menunggu Verifikasi','Dipinjam','Dikembalikan','Hilang','Ditolak','Telat','Selesai','Dibooking') DEFAULT 'Menunggu Verifikasi',
  `keterangan` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `t_pinjam`
--

INSERT INTO `t_pinjam` (`id_pinjam`, `id_detail`, `id_nip`, `tanggal_pinjam`, `tanggal_kembali`, `jumlah`, `status_peminjaman`, `keterangan`) VALUES
(18, 11, 'NIP02', '2025-05-31 15:52:00', '2025-06-02 20:48:38', 1, 'Selesai', ''),
(22, 1, 'NIP02', '2025-06-02 22:44:00', '2025-06-02 22:53:36', 1, 'Selesai', ''),
(23, 1, 'NIP02', '2025-06-02 22:56:00', '2025-06-02 22:57:09', 1, 'Dikembalikan', ''),
(25, 1, 'NIP02', '2025-06-03 00:33:00', '2025-06-03 00:51:43', 1, 'Dikembalikan', ''),
(28, 1, 'NIP02', '2025-06-09 21:50:00', '2025-06-09 21:50:48', 1, 'Selesai', 'AAA'),
(31, 1, 'NIP02', '2025-06-12 00:04:00', '2025-06-12 00:04:49', 1, 'Selesai', 'RUSAK PARAH'),
(40, 96, 'NIP02', '2025-06-18 05:35:00', NULL, 1, 'Hilang', 'WOW'),
(41, 97, 'NIP02', '2025-06-18 05:35:00', NULL, 1, 'Hilang', 'WOW'),
(49, 1, 'NIP02', '2025-06-25 21:31:00', '2025-07-02 21:31:00', 1, 'Ditolak', 'BAUK'),
(61, 11, 'NIP02', '2025-07-09 05:06:00', '2025-09-01 13:33:13', 1, 'Dikembalikan', 'bbb'),
(63, 2, 'NIP02', '2025-08-30 15:22:00', '2025-09-01 13:33:11', 1, 'Dikembalikan', '123'),
(64, 1, 'NIP02', '2025-09-23 13:37:00', '2025-09-30 13:37:00', 1, 'Dipinjam', 'Booking'),
(65, 2, 'NIP02', '2025-09-27 00:49:00', '2025-09-30 00:49:00', 1, 'Dibooking', 'Booking'),
(66, 2, 'NIP02', '2025-09-03 01:15:00', '2025-09-10 01:15:00', 1, 'Telat', 'tes booking 2'),
(67, 11, 'NIP02', '2025-09-03 02:11:00', '2025-09-30 02:06:00', 1, 'Dipinjam', 'tes');

-- --------------------------------------------------------

--
-- Table structure for table `t_user`
--

CREATE TABLE `t_user` (
  `id_nip` varchar(20) NOT NULL,
  `fullName` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `noHP` varchar(20) DEFAULT NULL,
  `fotoP` varchar(255) DEFAULT NULL,
  `role` enum('admin','pegawai','kaprodi','kaleb') NOT NULL DEFAULT 'pegawai'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `t_user`
--

INSERT INTO `t_user` (`id_nip`, `fullName`, `email`, `password`, `noHP`, `fotoP`, `role`) VALUES
('NIP01', 'super_admin', 'admin@gmail.com', '$2y$10$NGslPHkpoYGQDSsVxpxNK.abY4fdlH3IKeg9bU0d92N.EfT7RrIUu', '123', 'profile_6832b6d757a982.74259808.png', 'admin'),
('NIP02', 'user', 'user@gmail.com', '$2y$10$9MGtvSdh9t9vS4XjGPy0T.juchHwZZHyQ4bqAPBHufX0l6O/mUH1y', '123', '', 'pegawai'),
('NIP03', 'kaprodi', 'kaprodi@gmail.com', '$2y$10$/haDkdZ5rW79OmF1DtKsaejHvW/usBmSiQhLHH.VJpc1F46IlEhq2', '123', '', 'kaprodi'),
('NIP04', 'yudhis', 'yudhis@gmail.com', '$2y$10$NGslPHkpoYGQDSsVxpxNK.abY4fdlH3IKeg9bU0d92N.EfT7RrIUu', '123', NULL, 'pegawai'),
('NIP05', 'Siti Nurhaliza', 'siti@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567891', NULL, 'pegawai'),
('NIP06', 'Budi Santoso', 'budi@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567892', NULL, 'pegawai'),
('NIP07', 'Rina Putri', 'rina@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567893', NULL, 'pegawai'),
('NIP08', 'Dodi Perdana', 'dodi@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567894', NULL, 'pegawai'),
('NIP09', 'Maya Sari', 'maya@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567895', NULL, 'pegawai'),
('NIP10', 'Andi Saputra', 'andi@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567896', NULL, 'pegawai'),
('NIP11', 'Lia Kartika', 'lia@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567897', NULL, 'pegawai'),
('NIP12', 'Hendra Wijaya', 'hendra@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567898', NULL, 'pegawai'),
('NIP13', 'Anisa Rahma', 'anisa@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567899', NULL, 'pegawai'),
('NIP14', 'Indra Perdana Sinaga', 'indra@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567800', NULL, 'pegawai'),
('NIP15', 'Desi Anggraini', 'desi@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567801', NULL, 'pegawai'),
('NIP16', 'Faisal Malik', 'faisal@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567802', NULL, 'pegawai'),
('NIP17', 'Tia Puspita', 'tia@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567803', NULL, 'pegawai'),
('NIP18', 'Rizal Praditha', 'rizal@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567804', NULL, 'pegawai'),
('NIP19', 'Mira Yulianti', 'mira@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567805', NULL, 'pegawai'),
('NIP20', 'Aditya Pratama', 'adit@example.com', 'ef92b778bafe771e89245b89ecbc08a44a4e166c06659911881f383d4473e94f', '081234567806', NULL, 'pegawai');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `t_barang`
--
ALTER TABLE `t_barang`
  ADD PRIMARY KEY (`id_barang`);

--
-- Indexes for table `t_barang_detail`
--
ALTER TABLE `t_barang_detail`
  ADD PRIMARY KEY (`id_detail`),
  ADD UNIQUE KEY `kode_barang` (`kode_barang`),
  ADD KEY `id_barang` (`id_barang`);

--
-- Indexes for table `t_pinjam`
--
ALTER TABLE `t_pinjam`
  ADD PRIMARY KEY (`id_pinjam`),
  ADD KEY `id_detail` (`id_detail`),
  ADD KEY `id_nip` (`id_nip`);

--
-- Indexes for table `t_user`
--
ALTER TABLE `t_user`
  ADD PRIMARY KEY (`id_nip`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `t_barang`
--
ALTER TABLE `t_barang`
  MODIFY `id_barang` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `t_barang_detail`
--
ALTER TABLE `t_barang_detail`
  MODIFY `id_detail` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT for table `t_pinjam`
--
ALTER TABLE `t_pinjam`
  MODIFY `id_pinjam` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=68;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `t_barang_detail`
--
ALTER TABLE `t_barang_detail`
  ADD CONSTRAINT `t_barang_detail_ibfk_1` FOREIGN KEY (`id_barang`) REFERENCES `t_barang` (`id_barang`) ON DELETE CASCADE;

--
-- Constraints for table `t_pinjam`
--
ALTER TABLE `t_pinjam`
  ADD CONSTRAINT `t_pinjam_ibfk_1` FOREIGN KEY (`id_detail`) REFERENCES `t_barang_detail` (`id_detail`),
  ADD CONSTRAINT `t_pinjam_ibfk_2` FOREIGN KEY (`id_nip`) REFERENCES `t_user` (`id_nip`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
