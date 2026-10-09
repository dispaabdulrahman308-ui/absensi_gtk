<?php
require_once '../config/database.php';
require_once '../TCPDF/tcpdf.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    die("Akses ditolak!");
}

// Filter range tanggal
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '';
$filter_tanggal_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : '';

// Ambil data pengaturan
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
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

// Ambil semua guru aktif
$stmt = $pdo->query("SELECT id_guru, nama, nip, jabatan, gaji_perjam, gaji_perhari, gaji_pokok FROM guru WHERE role = 'guru' AND status = 'aktif' ORDER BY nama");
$guru_list = $stmt->fetchAll();

$gaji_data = [];
$total_keseluruhan = 0;
$total_menit_telat_all = 0;

foreach ($guru_list as $guru) {
    // Hitung absensi (termasuk telat dalam menit)
    if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
        $stmt = $pdo->prepare("
            SELECT a.*, j.jam_mulai 
            FROM absensi a
            JOIN jam_kerja j ON a.jam_ke = j.jam_ke
            WHERE a.id_guru = ? AND a.tanggal BETWEEN ? AND ?
        ");
        $stmt->execute([$guru['id_guru'], $filter_tanggal_mulai, $filter_tanggal_selesai]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, j.jam_mulai 
            FROM absensi a
            JOIN jam_kerja j ON a.jam_ke = j.jam_ke
            WHERE a.id_guru = ?
        ");
        $stmt->execute([$guru['id_guru']]);
    }
    $absensi_raw = $stmt->fetchAll();
    
    $total_hari = 0;
    $total_jam = 0;
    $total_menit_telat = 0;
    $hari_tercatat = [];
    
    foreach ($absensi_raw as $absen) {
        $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
        $batas_telat = isset($jam_kerja[$absen['jam_ke']]['batas_telat']) ? $jam_kerja[$absen['jam_ke']]['batas_telat'] : date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
        
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
    
    // Skip guru tanpa absensi
    if ($total_hari == 0 && $total_jam == 0) {
        continue;
    }
    
    // Hitung lembur
    if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
            FROM ajuan 
            WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
            AND tanggal_mulai BETWEEN ? AND ?
        ");
        $stmt->execute([$guru['id_guru'], $filter_tanggal_mulai, $filter_tanggal_selesai]);
    } else {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
            FROM ajuan 
            WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
        ");
        $stmt->execute([$guru['id_guru']]);
    }
    $lembur = $stmt->fetch();
    
    $gaji_pangkat = $guru['gaji_pokok'] ?? 0;
    $gaji_perjam = $guru['gaji_perjam'] ?? 0;
    $gaji_perhari = $guru['gaji_perhari'] ?? 0;
    
    if ($gaji_perjam > 0) {
        $gaji_absensi = $total_jam * $gaji_perjam;
    } else {
        $gaji_absensi = $total_hari * $gaji_perhari;
    }
    
    $gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;
    $total_gaji = $gaji_pangkat + $gaji_absensi + $gaji_lembur;
    $total_keseluruhan += $total_gaji;
    $total_menit_telat_all += $total_menit_telat;
    
    $gaji_data[] = [
        'nama' => $guru['nama'],
        'nip' => $guru['nip'],
        'jabatan' => $guru['jabatan'],
        'total_hari' => $total_hari,
        'total_jam' => $total_jam,
        'total_menit_telat' => $total_menit_telat,
        'gaji_pangkat' => $gaji_pangkat,
        'gaji_absensi' => $gaji_absensi,
        'gaji_lembur' => $gaji_lembur,
        'total_gaji' => $total_gaji
    ];
}

// Format periode
if (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) {
    $periode_text = date('d F Y', strtotime($filter_tanggal_mulai)) . ' s/d ' . date('d F Y', strtotime($filter_tanggal_selesai));
} else {
    $periode_text = 'Semua Data';
}

// ========== KELAS PDF CUSTOM ==========
class GajiPDF extends TCPDF {
    private $settings;
    private $logo_path;
    
    public function setData($settings, $logo_path) {
        $this->settings = $settings;
        $this->logo_path = $logo_path;
    }
    
    public function Header() {
        // Logo dari database
        $logo_html = '';
        if (!empty($this->logo_path) && file_exists($this->logo_path)) {
            $logo_html = '<img src="' . $this->logo_path . '" style="width: 55px; height: 55px;">';
        } else {
            // Fallback jika logo tidak ada
            $logo_html = '<div style="width: 55px; height: 55px; background: #c41e3a; border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">📚</div>';
        }
        
        // Nama Sekolah dan Alamat dari database
        $nama_sekolah = isset($this->settings['nama_sekolah']) && !empty($this->settings['nama_sekolah']) ? $this->settings['nama_sekolah'] : 'GTK System';
        $alamat = isset($this->settings['alamat_sekolah']) ? $this->settings['alamat_sekolah'] : '';
        $telepon = isset($this->settings['telepon_sekolah']) ? $this->settings['telepon_sekolah'] : '-';
        $email = isset($this->settings['email_sekolah']) ? $this->settings['email_sekolah'] : '-';
        
        // Kop Surat
        $html = '
        <table style="width: 100%; border-bottom: 2px solid #c41e3a; padding-bottom: 8px; margin-bottom: 15px;">
            <tr>
                <td style="width: 20%; text-align: center;">' . $logo_html . '</td>
                <td style="width: 60%; text-align: center;">
                    <div style="font-size: 16pt; font-weight: bold; color: #c41e3a;">' . htmlspecialchars($nama_sekolah) . '</div>
                    <div style="font-size: 9pt; margin-top: 4px;">' . htmlspecialchars($alamat) . '</div>
                    <div style="font-size: 8pt; margin-top: 2px;">
                        📞 Telp: ' . htmlspecialchars($telepon) . ' | ✉️ Email: ' . htmlspecialchars($email) . '
                    </div>
                </td>
                <td style="width: 20%;"></td>
            </tr>
        </table>
        ';
        $this->writeHTML($html, true, false, true, false, '');
        $this->SetY(48);
    }
    
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Dicetak: ' . date('d/m/Y H:i:s') . ' | Halaman ' . $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
}

// Buat PDF Landscape
$pdf = new GajiPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setPrintHeader(true);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 55, 15);
$pdf->SetAutoPageBreak(true, 20);
$logo_path = '../assets/uploads/logo/' . ($settings['logo_sekolah'] ?? '');
$pdf->setData($settings, $logo_path);
$pdf->AddPage();

// Judul Laporan
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'LAPORAN GAJI GURU', 0, 1, 'C');
$pdf->SetFont('helvetica', '', 11);
$pdf->Cell(0, 6, 'Periode: ' . $periode_text, 0, 1, 'C');
$pdf->Ln(10);

// Tabel
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(196, 30, 58);
$pdf->SetTextColor(255, 255, 255);

// Lebar kolom
$w = array(10, 50, 30, 18, 18, 22, 30, 32, 30, 30);

// Header tabel
$headers = ['No', 'Nama Guru', 'NIP', 'Total Hari', 'Total Jam', 'Terlambat', 'Gaji Pangkat', 'Gaji Absensi', 'Gaji Lembur', 'Total Gaji'];

for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($w[$i], 8, $headers[$i], 1, 0, 'C', 1);
}
$pdf->Ln();

// Data tabel
$pdf->SetFont('helvetica', '', 9);
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
    $pdf->Cell($w[9], 7, 'Rp ' . number_format($data['total_gaji'], 0, ',', '.'), 1, 1, 'R');
    $no++;
}

// Grand Total
$pdf->SetFont('helvetica', 'B', 9);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(0, 0, 0);

$pdf->Cell(array_sum(array_slice($w, 0, 9)), 7, 'GRAND TOTAL', 1, 0, 'R', 1);
$pdf->Cell($w[9], 7, 'Rp ' . number_format($total_keseluruhan, 0, ',', '.'), 1, 1, 'R', 1);

// Total Terlambat
$pdf->SetFont('helvetica', 'I', 8);
$pdf->Cell(array_sum(array_slice($w, 0, 5)), 7, '', 0, 0, 'L');
$pdf->Cell($w[5], 7, 'Total Terlambat: ' . $total_menit_telat_all . ' menit', 0, 1, 'L');

// Tanda Tangan
$pdf->Ln(20);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, htmlspecialchars($settings['kota'] ?? '') . ', ' . date('d F Y'), 0, 1, 'R');
$pdf->Ln(25);
$pdf->Cell(0, 5, 'Kepala Sekolah', 0, 1, 'R');
$pdf->Ln(20);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(0, 5, htmlspecialchars($settings['kepala_sekolah'] ?? '_________________'), 0, 1, 'R');
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'NIP. ' . htmlspecialchars($settings['nip_kepsek'] ?? '-'), 0, 1, 'R');

$pdf->Output('Laporan_Gaji.pdf', 'D');
exit();
?>