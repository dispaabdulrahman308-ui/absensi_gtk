-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Waktu pembuatan: 01 Bulan Mei 2026 pada 13.47
-- Versi server: 11.4.10-MariaDB
-- Versi PHP: 8.4.20

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `demoabse_gtk`
--

-- --------------------------------------------------------

--
-- Struktur dari tabel `absensi`
--

CREATE TABLE `absensi` (
  `id_absensi` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `jam_ke` int(11) NOT NULL,
  `waktu_absen` datetime NOT NULL,
  `foto_selfie` text DEFAULT NULL,
  `lokasi_gps` varchar(200) DEFAULT NULL,
  `status` enum('hadir','telat','alfa') DEFAULT 'hadir'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `ajuan`
--

CREATE TABLE `ajuan` (
  `id_ajuan` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `jenis` enum('sakit','izin','cuti','lembur') NOT NULL,
  `alasan` text DEFAULT NULL,
  `foto_bukti` text DEFAULT NULL,
  `tanggal_mulai` date NOT NULL,
  `tanggal_selesai` date NOT NULL,
  `status` enum('pending','disetujui','ditolak') DEFAULT 'pending',
  `gaji_lembur` decimal(10,2) DEFAULT NULL,
  `catatan_admin` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `backup_log`
--

CREATE TABLE `backup_log` (
  `id_backup` int(11) NOT NULL,
  `nama_file` varchar(100) DEFAULT NULL,
  `tanggal_backup` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `gaji`
--

CREATE TABLE `gaji` (
  `id_gaji` int(11) NOT NULL,
  `id_guru` int(11) NOT NULL,
  `bulan` date NOT NULL,
  `total_jam_hadir` int(11) DEFAULT 0,
  `total_hari_hadir` int(11) DEFAULT 0,
  `total_gaji` decimal(10,2) DEFAULT 0.00,
  `gaji_pokok` decimal(10,2) DEFAULT NULL,
  `gaji_lembur` decimal(10,2) DEFAULT NULL,
  `slip_pdf` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `guru`
--

CREATE TABLE `guru` (
  `id_guru` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `nip` varchar(50) NOT NULL,
  `jabatan` varchar(50) DEFAULT NULL,
  `no_hp` varchar(20) DEFAULT NULL,
  `foto` text DEFAULT NULL,
  `status` enum('aktif','nonaktif') DEFAULT 'aktif',
  `gaji_perjam` decimal(10,2) DEFAULT NULL,
  `gaji_perhari` decimal(10,2) DEFAULT NULL,
  `gaji_pokok` decimal(10,2) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','guru') DEFAULT 'guru',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `guru`
--

INSERT INTO `guru` (`id_guru`, `nama`, `nip`, `jabatan`, `no_hp`, `foto`, `status`, `gaji_perjam`, `gaji_perhari`, `gaji_pokok`, `username`, `password`, `role`, `created_at`) VALUES
(2, 'Administrator', 'ADMIN001', 'Administrator', '081234567890', NULL, 'aktif', NULL, NULL, NULL, 'admin', '$2y$10$WQO7CKR8nhMNEIw.4BiemOFjESHNQkFHjKAiQQgZZ0nP9oEEP7PyS', 'admin', '2026-04-29 12:54:25'),
(13, 'santoso', '2323', 'Waka Kurikulum', '082131290542', '1777617930_69f44c0a38130.png', 'aktif', NULL, NULL, NULL, 'santoso', '$2y$10$v9IseFfHwiFzXLo8pv82pevgFUzHePiu3NPzYrAHhhczQC5c6FiqG', 'guru', '2026-05-01 06:45:30');

-- --------------------------------------------------------

--
-- Struktur dari tabel `jam_kerja`
--

CREATE TABLE `jam_kerja` (
  `id_jam` int(11) NOT NULL,
  `jam_ke` int(11) NOT NULL,
  `jam_mulai` time NOT NULL,
  `jam_selesai` time NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- --------------------------------------------------------

--
-- Struktur dari tabel `pengaturan`
--

CREATE TABLE `pengaturan` (
  `id_setting` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

--
-- Dumping data untuk tabel `pengaturan`
--

INSERT INTO `pengaturan` (`id_setting`, `setting_key`, `setting_value`) VALUES
(1, 'radius_gps', '40'),
(2, 'whatsapp_gateway', 'https://api.whatsapp.com/send?phone='),
(3, 'nama_sekolah', 'SD Inpres Temi'),
(4, 'logo_sekolah', 'logo_1777480025.png'),
(5, 'alamat_sekolah', 'Jl. Pendidikan Dusun Temi,Kecamatan Huamual,Seram Bagian Barat'),
(7, 'enable_fake_detection', '1'),
(8, 'max_accuracy', '50'),
(9, 'max_time_diff', '5'),
(11, 'npsn', '34354345345'),
(13, 'kota', 'piru'),
(14, 'provinsi', 'Maluku'),
(15, 'kode_pos', '97562'),
(16, 'telepon_sekolah', '081222332463'),
(17, 'email_sekolah', 'sdinprestemi06@gmail.com'),
(18, 'website', '-'),
(19, 'kepala_sekolah', 'Jasna Ismail, S.Pd'),
(20, 'nip_kepsek', '197911102009042003'),
(22, 'api_wa_url', 'https://api.sidobe.com/wa/v1/send-message'),
(23, 'api_wa_key', 'GIeeuuiGoNEwokyWXpsRMWySASuoSfU'),
(24, 'wa_notification_enabled', '1'),
(33, 'sekolah_lat', '-7.859549'),
(34, 'sekolah_lng', '113.282991'),
(41, 'sekolah_alamat', 'Pendidikan dusun temi, Huamual, Piru, 97562, Indonesia');

--
-- Indexes for dumped tables
--

--
-- Indeks untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD PRIMARY KEY (`id_absensi`),
  ADD KEY `id_guru` (`id_guru`);

--
-- Indeks untuk tabel `ajuan`
--
ALTER TABLE `ajuan`
  ADD PRIMARY KEY (`id_ajuan`),
  ADD KEY `id_guru` (`id_guru`);

--
-- Indeks untuk tabel `backup_log`
--
ALTER TABLE `backup_log`
  ADD PRIMARY KEY (`id_backup`);

--
-- Indeks untuk tabel `gaji`
--
ALTER TABLE `gaji`
  ADD PRIMARY KEY (`id_gaji`),
  ADD KEY `id_guru` (`id_guru`);

--
-- Indeks untuk tabel `guru`
--
ALTER TABLE `guru`
  ADD PRIMARY KEY (`id_guru`),
  ADD UNIQUE KEY `nip` (`nip`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indeks untuk tabel `jam_kerja`
--
ALTER TABLE `jam_kerja`
  ADD PRIMARY KEY (`id_jam`);

--
-- Indeks untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  ADD PRIMARY KEY (`id_setting`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- AUTO_INCREMENT untuk tabel yang dibuang
--

--
-- AUTO_INCREMENT untuk tabel `absensi`
--
ALTER TABLE `absensi`
  MODIFY `id_absensi` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `ajuan`
--
ALTER TABLE `ajuan`
  MODIFY `id_ajuan` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `backup_log`
--
ALTER TABLE `backup_log`
  MODIFY `id_backup` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `gaji`
--
ALTER TABLE `gaji`
  MODIFY `id_gaji` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT untuk tabel `guru`
--
ALTER TABLE `guru`
  MODIFY `id_guru` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT untuk tabel `jam_kerja`
--
ALTER TABLE `jam_kerja`
  MODIFY `id_jam` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT untuk tabel `pengaturan`
--
ALTER TABLE `pengaturan`
  MODIFY `id_setting` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- Ketidakleluasaan untuk tabel pelimpahan (Dumped Tables)
--

--
-- Ketidakleluasaan untuk tabel `absensi`
--
ALTER TABLE `absensi`
  ADD CONSTRAINT `absensi_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `ajuan`
--
ALTER TABLE `ajuan`
  ADD CONSTRAINT `ajuan_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE;

--
-- Ketidakleluasaan untuk tabel `gaji`
--
ALTER TABLE `gaji`
  ADD CONSTRAINT `gaji_ibfk_1` FOREIGN KEY (`id_guru`) REFERENCES `guru` (`id_guru`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
