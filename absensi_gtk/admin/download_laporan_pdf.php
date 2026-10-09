<?php
require_once '../config/database.php';
require_once '../TCPDF/tcpdf.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak!");
}

// Ambil filter dari URL
$filter_tipe = $_GET['tipe'] ?? 'bulanan';
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$filter_bulan = $_GET['bulan'] ?? date('Y-m');
$filter_guru = $_GET['guru'] ?? '';
$filter_jam = $_GET['jam'] ?? '';

$tahun = substr($filter_bulan, 0, 4);
$bulan_angka = substr($filter_bulan, 5, 2);
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil data pengaturan untuk kop surat
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Ambil data jam kerja untuk batas telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja");
$jam_kerja = [];
while ($row = $stmt_jam->fetch()) {
    $jam_kerja[$row['jam_ke']] = [
        'jam_mulai' => $row['jam_mulai'],
        'batas_telat' => date('H:i:s', strtotime($row['jam_mulai'] . ' +5 minutes'))
    ];
}

// Ambil data absensi untuk laporan bulanan
$sql = "
    SELECT a.*, g.nama, g.nip, g.jabatan, g.gaji_perjam, g.gaji_perhari, g.gaji_pokok, j.jam_mulai, j.jam_selesai
    FROM absensi a
    JOIN guru g ON a.id_guru = g.id_guru
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE DATE_FORMAT(a.tanggal, '%Y-%m') = ?
";
$params = [$filter_bulan];

if ($filter_guru) {
    $sql .= " AND a.id_guru = ?";
    $params[] = $filter_guru;
}

$sql .= " ORDER BY g.nama, a.tanggal, a.jam_ke";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$absensi_list = $stmt->fetchAll();

// Kelompokkan data per guru
$gaji_data = [];
$total_gaji_keseluruhan = 0;
$total_menit_telat_all = 0;

foreach ($absensi_list as $absen) {
    $id_guru = $absen['id_guru'];
    
    if (!isset($gaji_data[$id_guru])) {
        $gaji_data[$id_guru] = [
            'id_guru' => $id_guru,
            'nama' => $absen['nama'],
            'nip' => $absen['nip'],
            'jabatan' => $absen['jabatan'],
            'gaji_pangkat' => $absen['gaji_pokok'] ?? 0,
            'gaji_perjam' => $absen['gaji_perjam'] ?? 0,
            'gaji_perhari' => $absen['gaji_perhari'] ?? 0,
            'total_hari' => 0,
            'total_jam' => 0,
            'total_menit_telat' => 0,
            'hari_tercatat' => []
        ];
    }
    
    // Hitung menit telat
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = isset($jam_kerja[$absen['jam_ke']]['batas_telat']) ? $jam_kerja[$absen['jam_ke']]['batas_telat'] : date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    $menit_telat = 0;
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $menit_telat = max($menit_telat, 0);
        $gaji_data[$id_guru]['total_menit_telat'] += $menit_telat;
    }
    
    $gaji_data[$id_guru]['total_jam']++;
    if (!in_array($absen['tanggal'], $gaji_data[$id_guru]['hari_tercatat'])) {
        $gaji_data[$id_guru]['hari_tercatat'][] = $absen['tanggal'];
        $gaji_data[$id_guru]['total_hari']++;
    }
}

// Hitung total gaji untuk setiap guru
foreach ($gaji_data as $id => &$guru) {
    if ($guru['gaji_perjam'] > 0) {
        $gaji_absensi = $guru['total_jam'] * $guru['gaji_perjam'];
        $tipe_gaji = 'per jam';
        $nilai_gaji = $guru['gaji_perjam'];
    } else {
        $gaji_absensi = $guru['total_hari'] * $guru['gaji_perhari'];
        $tipe_gaji = 'per hari';
        $nilai_gaji = $guru['gaji_perhari'];
    }
    
    // Hitung lembur
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
        FROM ajuan 
        WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
        AND DATE_FORMAT(tanggal_mulai, '%Y-%m') = ?
    ");
    $stmt->execute([$id, $filter_bulan]);
    $lembur = $stmt->fetch();
    $gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;
    
    $guru['gaji_absensi'] = $gaji_absensi;
    $guru['tipe_gaji'] = $tipe_gaji;
    $guru['nilai_gaji'] = $nilai_gaji;
    $guru['gaji_lembur'] = $gaji_lembur;
    $guru['total_gaji'] = $guru['gaji_pangkat'] + $gaji_absensi + $gaji_lembur;
    
    $total_gaji_keseluruhan += $guru['total_gaji'];
    $total_menit_telat_all += $guru['total_menit_telat'];
}

// Jika tidak ada data, tampilkan pesan
if (empty($gaji_data)) {
    // Buat PDF dengan pesan
    class LaporanPDF extends TCPDF {
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
            $this->SetY(45);
        }
        
        public function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }
    
    $pdf = new LaporanPDF('L', 'mm', 'A4', true, 'UTF-8', false);
    $pdf->setPrintHeader(true);
    $pdf->setPrintFooter(true);
    $pdf->SetMargins(10, 50, 10);
    $logo_path = '../assets/uploads/logo/' . ($settings['logo_sekolah'] ?? '');
    $pdf->setData($settings, $logo_path);
    $pdf->AddPage();
    
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 8, 'LAPORAN ABSENSI & GAJI BULANAN', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 11);
    $pdf->Cell(0, 6, 'Periode: ' . $nama_bulan[$bulan_angka] . ' ' . $tahun, 0, 1, 'C');
    $pdf->Ln(20);
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, '📭 Belum ada data absensi untuk periode ini', 0, 1, 'C');
    $pdf->Output('Laporan_Bulanan_' . $filter_bulan . '.pdf', 'D');
    exit();
}

// Sort berdasarkan nama
usort($gaji_data, function($a, $b) {
    return strcmp($a['nama'], $b['nama']);
});

// ========== KELAS PDF CUSTOM ==========
class LaporanPDF extends TCPDF {
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
        $this->SetY(45);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat PDF
$pdf = new LaporanPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(10, 50, 10);
$logo_path = '../assets/uploads/logo/' . ($settings['logo_sekolah'] ?? '');
$pdf->setData($settings, $logo_path);
$pdf->AddPage();

// Judul
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'LAPORAN ABSENSI & GAJI BULANAN', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Periode: ' . $nama_bulan[$bulan_angka] . ' ' . $tahun, 0, 1, 'C');
$pdf->Ln(8);

// Tabel
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(196, 30, 58);
$pdf->SetTextColor(255, 255, 255);

// Lebar kolom
$w = array(10, 45, 30, 15, 15, 20, 25, 30, 25, 25, 30);

// Header tabel
$headers = ['No', 'Nama Guru', 'NIP', 'Total Hari', 'Total Jam', 'Terlambat', 'Gaji Pangkat', 'Gaji Absensi', 'Gaji Lembur', 'Total Gaji', 'Slip'];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Data tabel
$pdf->SetFont('helvetica', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFillColor(255, 255, 255);

$no = 1;
foreach ($gaji_data as $data) {
    $pdf->Cell($w[0], 7, $no, 1, 0, 'C');
    $pdf->Cell($w[1], 7, htmlspecialchars($data['nama']), 1, 0, 'L');
    $pdf->Cell($w[2], 7, $data['nip'], 1, 0, 'C');
    $pdf->Cell($w[3], 7, $data['total_hari'] . ' hr', 1, 0, 'C');
    $pdf->Cell($w[4], 7, $data['total_jam'] . ' jm', 1, 0, 'C');
    $pdf->Cell($w[5], 7, $data['total_menit_telat'] . ' mnt', 1, 0, 'C');
    $pdf->Cell($w[6], 7, 'Rp ' . number_format($data['gaji_pangkat'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($w[7], 7, 'Rp ' . number_format($data['gaji_absensi'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($w[8], 7, 'Rp ' . number_format($data['gaji_lembur'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($w[9], 7, 'Rp ' . number_format($data['total_gaji'], 0, ',', '.'), 1, 0, 'R');
    $pdf->Cell($w[10], 7, '📄', 1, 1, 'C');
    $no++;
}

// Grand Total
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(0, 0, 0);

$pdf->Cell(array_sum(array_slice($w, 0, 10)), 7, 'GRAND TOTAL', 1, 0, 'R', 1);
$pdf->Cell($w[10], 7, 'Rp ' . number_format($total_gaji_keseluruhan, 0, ',', '.'), 1, 1, 'R', 1);

// Total Terlambat
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(array_sum(array_slice($w, 0, 5)), 7, '', 0, 0, 'L');
$pdf->Cell($w[5], 7, 'Total Terlambat: ' . $total_menit_telat_all . ' menit', 0, 1, 'L');

// Tanda Tangan
$pdf->Ln(15);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, htmlspecialchars($settings['kota'] ?? '') . ', ' . date('d F Y'), 0, 1, 'R');
$pdf->Ln(20);
$pdf->Cell(0, 5, 'Kepala Sekolah', 0, 1, 'R');
$pdf->Ln(15);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, htmlspecialchars($settings['kepala_sekolah'] ?? '_________________'), 0, 1, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'NIP. ' . htmlspecialchars($settings['nip_kepsek'] ?? '-'), 0, 1, 'R');

$pdf->Output('Laporan_Bulanan_' . $filter_bulan . '.pdf', 'D');
exit();
?>