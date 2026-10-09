<?php
require_once '../config/database.php';
require_once '../TCPDF/tcpdf.php';

// Cek login (boleh admin atau guru)
if (!isset($_SESSION['user_id'])) {
    die("Akses ditolak!");
}

// Ambil parameter
$id_guru = $_GET['id'] ?? 0;
$tanggal_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '';
$tanggal_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : '';

// Jika tidak ada filter range, gunakan bulan dari parameter lama untuk kompatibilitas
if (empty($tanggal_mulai) && empty($tanggal_selesai)) {
    $bulan = $_GET['bulan'] ?? date('Y-m');
    $tanggal_mulai = $bulan . '-01';
    $tanggal_selesai = date('Y-m-t', strtotime($bulan));
}

// Jika yang login adalah guru, pastikan dia hanya bisa download slipnya sendiri
if ($_SESSION['role'] == 'guru' && $_SESSION['user_id'] != $id_guru) {
    die("Akses ditolak! Anda hanya bisa mengunduh slip gaji sendiri.");
}

$tahun_mulai = substr($tanggal_mulai, 0, 4);
$bulan_mulai = substr($tanggal_mulai, 5, 2);
$tahun_selesai = substr($tanggal_selesai, 0, 4);
$bulan_selesai = substr($tanggal_selesai, 5, 2);

$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil data pengaturan
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM guru WHERE id_guru = ?");
$stmt->execute([$id_guru]);
$guru = $stmt->fetch();

if (!$guru) {
    die("Data guru tidak ditemukan!");
}

// Ambil jam kerja untuk batas telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja");
$jam_kerja = [];
while ($row = $stmt_jam->fetch()) {
    $jam_kerja[$row['jam_ke']] = [
        'jam_mulai' => $row['jam_mulai'],
        'batas_telat' => date('H:i:s', strtotime($row['jam_mulai'] . ' +5 minutes'))
    ];
}

// Ambil data absensi untuk periode tersebut (hitung telat berdasarkan waktu)
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND a.tanggal BETWEEN ? AND ?
");
$stmt->execute([$id_guru, $tanggal_mulai, $tanggal_selesai]);
$absensi_raw = $stmt->fetchAll();

$total_hari = 0;
$total_jam = 0;
$total_menit_telat = 0;
$hari_tercatat = [];

foreach ($absensi_raw as $absen) {
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'] ?? date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    // Hitung menit keterlambatan
    $menit_telat = 0;
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $menit_telat = max($menit_telat, 0);
        $total_menit_telat += $menit_telat;
    }
    
    if ($absen['status'] == 'hadir' || $absen['status'] == 'telat') {
        $total_jam++;
        if (!in_array($absen['tanggal'], $hari_tercatat)) {
            $hari_tercatat[] = $absen['tanggal'];
            $total_hari++;
        }
    }
}

// Ambil data gaji lembur
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
    FROM ajuan 
    WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui' 
    AND tanggal_mulai BETWEEN ? AND ?
");
$stmt->execute([$id_guru, $tanggal_mulai, $tanggal_selesai]);
$lembur = $stmt->fetch();

$gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;

// Hitung gaji
$gaji_pangkat = $guru['gaji_pokok'] ?? 0;
$gaji_perjam = $guru['gaji_perjam'] ?? 0;
$gaji_perhari = $guru['gaji_perhari'] ?? 0;

if ($gaji_perjam > 0) {
    $gaji_dari_absensi = $total_jam * $gaji_perjam;
    $tipe_gaji = 'per jam';
    $nilai_gaji = $gaji_perjam;
} else {
    $gaji_dari_absensi = $total_hari * $gaji_perhari;
    $tipe_gaji = 'per hari';
    $nilai_gaji = $gaji_perhari;
}

$total_gaji = $gaji_pangkat + $gaji_dari_absensi + $gaji_lembur;

// Format periode untuk ditampilkan
if ($tanggal_mulai == $tanggal_selesai) {
    $periode_text = date('d/m/Y', strtotime($tanggal_mulai));
} elseif (substr($tanggal_mulai, 0, 7) == substr($tanggal_selesai, 0, 7)) {
    $periode_text = $nama_bulan[$bulan_mulai] . ' ' . $tahun_mulai;
} else {
    $periode_text = date('d/m/Y', strtotime($tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($tanggal_selesai));
}

function terbilang($angka) {
    $angka = abs($angka);
    $bilangan = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];
    if ($angka < 12) return $bilangan[$angka];
    if ($angka < 20) return $bilangan[$angka - 10] . ' belas';
    if ($angka < 100) return $bilangan[floor($angka / 10)] . ' puluh ' . $bilangan[$angka % 10];
    if ($angka < 200) return 'seratus ' . terbilang($angka - 100);
    if ($angka < 1000) return $bilangan[floor($angka / 100)] . ' ratus ' . terbilang($angka % 100);
    if ($angka < 2000) return 'seribu ' . terbilang($angka - 1000);
    if ($angka < 1000000) return terbilang(floor($angka / 1000)) . ' ribu ' . terbilang($angka % 1000);
    if ($angka < 1000000000) return terbilang(floor($angka / 1000000)) . ' juta ' . terbilang($angka % 1000000);
    return '';
}

// ========== KELAS PDF CUSTOM ==========
class SlipPDF extends TCPDF {
    private $settings;
    private $logo_path;
    
    public function setData($settings, $logo_path) {
        $this->settings = $settings;
        $this->logo_path = $logo_path;
    }
    
    public function Header() {
        $logo_html = '';
        if (file_exists($this->logo_path) && !empty($this->logo_path)) {
            $logo_html = '<img src="' . $this->logo_path . '" style="width: 50px; height: 50px;">';
        }
        
        $html = '
        <table style="width: 100%; border-bottom: 2px solid #c41e3a; padding-bottom: 8px;">
            <tr>
                <td style="width: 15%; text-align: center;">' . $logo_html . '</td>
                <td style="width: 70%; text-align: center;">
                    <h2 style="margin: 0; color: #c41e3a;">' . htmlspecialchars($this->settings['nama_sekolah'] ?? 'GTK System') . '</h2>
                    <p style="margin: 3px 0; font-size: 9px;">' . htmlspecialchars($this->settings['alamat_sekolah'] ?? '') . '</p>
                    <p style="margin: 0; font-size: 8px;">Telp: ' . htmlspecialchars($this->settings['telepon_sekolah'] ?? '-') . ' | Email: ' . htmlspecialchars($this->settings['email_sekolah'] ?? '-') . '</p>
                </td>
                <td style="width: 15%;"></td>
            </tr>
        </table>
        ';
        $this->writeHTML($html, true, false, true, false, '');
        $this->SetY(42);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat PDF
$pdf = new SlipPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 48, 15);
$logo_path = '../assets/uploads/logo/' . ($settings['logo_sekolah'] ?? '');
$pdf->setData($settings, $logo_path);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'SLIP GAJI', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Periode: ' . $periode_text, 0, 1, 'C');
$pdf->Ln(8);

// Data Guru
$html_guru = '
<style>
    td { padding: 5px; }
</style>
<table style="width: 100%; border: 1px solid #ddd; border-collapse: collapse;">
    <tr>
        <td style="width: 25%;"><strong>Nama Guru</strong></td>
        <td style="width: 75%;">: ' . htmlspecialchars($guru['nama']) . '</td>
    </tr>
    <tr>
        <td><strong>NIP</strong></td>
        <td>: ' . $guru['nip'] . '</td>
    </tr>
    <tr>
        <td><strong>Jabatan</strong></td>
        <td>: ' . htmlspecialchars($guru['jabatan']) . '</td>
    </tr>
    <tr>
        <td><strong>Periode</strong></td>
        <td>: ' . $periode_text . '</td>
    </tr>
    <tr>
        <td><strong>Total Kehadiran</strong></td>
        <td>: ' . $total_hari . ' hari / ' . $total_jam . ' jam<br>' .
        ($total_menit_telat > 0 ? '<span style="color: #f59e0b;">⏰ Terlambat: ' . $total_menit_telat . ' menit</span>' : '<span style="color: #10b981;">✅ Tepat waktu</span>') . '
        </td>
    </tr>
</table>
';

$pdf->writeHTML($html_guru, true, false, true, false, '');

// Rincian Gaji (Gaji Pangkat, bukan Gaji Pokok)
$html_rincian = '
<table style="width: 100%; margin-top: 15px; border: 1px solid #ddd; border-collapse: collapse;">
    <tr style="background: #c41e3a; color: white;">
        <th style="padding: 8px; text-align: left;">Rincian Gaji</th>
        <th style="padding: 8px; text-align: right;">Jumlah (Rp)</th>
    </tr>
    <tr>
        <td style="padding: 8px;">Gaji Pangkat</td>
        <td style="padding: 8px; text-align: right;">' . number_format($gaji_pangkat, 0, ',', '.') . '</td>
    </tr>
    <tr>
        <td style="padding: 8px;">Gaji Absensi (' . $total_jam . ' jam / ' . $total_hari . ' hari)</td>
        <td style="padding: 8px; text-align: right;">' . number_format($gaji_dari_absensi, 0, ',', '.') . '</td>
    </tr>
    <tr>
        <td style="padding: 8px;">Gaji Lembur</td>
        <td style="padding: 8px; text-align: right;">' . number_format($gaji_lembur, 0, ',', '.') . '</td>
    </tr>
    <tr style="background: #f1f5f9; font-weight: bold;">
        <td style="padding: 8px;">TOTAL GAJI</td>
        <td style="padding: 8px; text-align: right;">' . number_format($total_gaji, 0, ',', '.') . '</td>
    </tr>
</table>
';

$pdf->writeHTML($html_rincian, true, false, true, false, '');

// Terbilang
$html_terbilang = '
<p style="margin-top: 15px;"><strong>Terbilang:</strong> ' . terbilang($total_gaji) . ' Rupiah</p>
';

$pdf->writeHTML($html_terbilang, true, false, true, false, '');

// Tanda Tangan
$html_ttd = '
<div style="margin-top: 30px;">
    <table style="width: 100%;">
        <tr>
            <td style="width: 50%;">  </td>
            <td style="width: 50%; text-align: center;">
                <p>' . ($settings['kota'] ?? '') . ', ' . date('d F Y') . '</p>
                <p style="margin-top: 30px;">Kepala Sekolah</p>
                <p style="margin-top: 40px;"><strong>' . htmlspecialchars($settings['kepala_sekolah'] ?? '_________________') . '</strong></p>
                <p>NIP. ' . htmlspecialchars($settings['nip_kepsek'] ?? '-') . '</p>
            </td>
        </tr>
    </table>
</div>
';

$pdf->writeHTML($html_ttd, true, false, true, false, '');
$pdf->Output('Slip_Gaji_' . $guru['nama'] . '_' . date('Ymd', strtotime($tanggal_mulai)) . '.pdf', 'D');
exit();
?>