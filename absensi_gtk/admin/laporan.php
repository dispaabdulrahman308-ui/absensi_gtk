<?php
$page_title = 'Laporan - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data pengaturan
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Ambil data untuk filter
$stmt = $pdo->query("SELECT id_guru, nama FROM guru WHERE role = 'guru' AND status = 'aktif' ORDER BY nama");
$guru_list = $stmt->fetchAll();

$stmt = $pdo->query("SELECT * FROM jam_kerja ORDER BY jam_ke");
$jam_list = $stmt->fetchAll();

// Filter
$filter_tipe = $_GET['tipe'] ?? 'bulanan';
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '';
$filter_tanggal_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : '';
$filter_guru = isset($_GET['guru']) ? $_GET['guru'] : '';
$filter_jam = isset($_GET['jam']) ? $_GET['jam'] : '';

// Ambil jam kerja untuk batas telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja");
$jam_kerja = [];
while ($row = $stmt_jam->fetch()) {
    $jam_kerja[$row['jam_ke']] = [
        'jam_mulai' => $row['jam_mulai'],
        'batas_telat' => date('H:i:s', strtotime($row['jam_mulai'] . ' +5 minutes'))
    ];
}

// ========== LAPORAN BULANAN ==========
// Ambil semua guru aktif
$stmt = $pdo->query("SELECT id_guru, nama, nip, jabatan, gaji_perjam, gaji_perhari, gaji_pokok FROM guru WHERE role = 'guru' AND status = 'aktif' ORDER BY nama");
$all_guru = $stmt->fetchAll();

$laporan_data = [];
$total_gaji_keseluruhan = 0;
$total_menit_telat_all = 0;

foreach ($all_guru as $guru) {
    // HITUNG ABSENSI (termasuk telat dalam menit)
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
    
    // Jika tidak ada absensi, SKIP guru ini
    if ($total_hari == 0 && $total_jam == 0) {
        continue;
    }
    
    // HITUNG LEMBUR
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
    
    // Hitung gaji absensi
    if ($gaji_perjam > 0) {
        $gaji_absensi = $total_jam * $gaji_perjam;
        $tipe_gaji = 'per jam';
        $nilai_gaji = $gaji_perjam;
    } else {
        $gaji_absensi = $total_hari * $gaji_perhari;
        $tipe_gaji = 'per hari';
        $nilai_gaji = $gaji_perhari;
    }
    
    $gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;
    $total_gaji = $gaji_pangkat + $gaji_absensi + $gaji_lembur;
    $total_gaji_keseluruhan += $total_gaji;
    $total_menit_telat_all += $total_menit_telat;
    
    $laporan_data[] = [
        'id_guru' => $guru['id_guru'],
        'nama' => $guru['nama'],
        'nip' => $guru['nip'],
        'jabatan' => $guru['jabatan'],
        'total_hari' => $total_hari,
        'total_jam' => $total_jam,
        'total_menit_telat' => $total_menit_telat,
        'gaji_pangkat' => $gaji_pangkat,
        'gaji_absensi' => $gaji_absensi,
        'tipe_gaji' => $tipe_gaji,
        'nilai_gaji' => $nilai_gaji,
        'gaji_lembur' => $gaji_lembur,
        'total_gaji' => $total_gaji
    ];
}

// Format periode
$periode_text = (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) ? 
    date('d/m/Y', strtotime($filter_tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($filter_tanggal_selesai)) : 
    'Semua Data';

// ========== EXPORT EXCEL ==========
if (isset($_GET['export_excel'])) {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Laporan_Gaji.xls"');
    
    echo '<html><head><meta charset="UTF-8"><title>Laporan Gaji</title></head><body>';
    echo '<h2>LAPORAN GAJI GURU</h2>';
    echo '<p>Periode: ' . $periode_text . '</p>';
    echo '<table border="1">';
    echo '<tr style="background:#c41e3a;color:white;">';
    echo '<th>No</th><th>Nama Guru</th><th>NIP</th><th>Total Hari</th><th>Total Jam</th><th>Terlambat</th><th>Gaji Pangkat</th><th>Gaji Absensi</th><th>Gaji Lembur</th><th>Total Gaji</th>';
    echo '</tr>';
    
    $no = 1;
    foreach ($laporan_data as $d) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($d['nama']) . '</td>';
        echo '<td>' . $d['nip'] . '</td>';
        echo '<td>' . $d['total_hari'] . ' hari</td>';
        echo '<td>' . $d['total_jam'] . ' jam</td>';
        echo '<td>' . $d['total_menit_telat'] . ' menit</td>';
        echo '<td>Rp ' . number_format($d['gaji_pangkat'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($d['gaji_absensi'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($d['gaji_lembur'], 0, ',', '.') . '</td>';
        echo '<td><strong>Rp ' . number_format($d['total_gaji'], 0, ',', '.') . '</strong></td>';
        echo '</tr>';
    }
    
    echo '<tr style="background:#f1f5f9;">';
    echo '<td colspan="9"><strong>GRAND TOTAL</strong></td>';
    echo '<td><strong>Rp ' . number_format($total_gaji_keseluruhan, 0, ',', '.') . '</strong></td>';
    echo '</tr>';
    echo '</table>';
    echo '<p>Total Terlambat: ' . $total_menit_telat_all . ' menit</p>';
    echo '</body></html>';
    exit();
}

// ========== EXPORT PDF ==========
if (isset($_GET['export_pdf'])) {
    header("Location: download_laporan_pdf.php?" . http_build_query($_GET));
    exit();
}

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title">📄 Laporan Absensi & Gaji</h1>
                <p class="page-subtitle">Lihat dan export laporan absensi harian atau bulanan</p>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-info">
                <span class="filter-badge">📋 Tipe: Bulanan</span>
                <?php if(!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)): ?>
                    <span class="filter-badge">📅 Periode: <?= date('d/m/Y', strtotime($filter_tanggal_mulai)) ?> - <?= date('d/m/Y', strtotime($filter_tanggal_selesai)) ?></span>
                <?php else: ?>
                    <span class="filter-badge">📅 Semua Data</span>
                <?php endif; ?>
                <span class="filter-badge">👥 Total: <?= count($laporan_data) ?> guru</span>
                <span class="filter-badge">💰 Total Gaji: Rp <?= number_format($total_gaji_keseluruhan, 0, ',', '.') ?></span>
            </div>
            
            <div class="filter-actions">
                <form method="GET" class="filter-form">
                    <input type="date" name="tanggal_mulai" class="filter-input" value="<?= $filter_tanggal_mulai ?>" placeholder="Dari">
                    <span class="filter-sep">-</span>
                    <input type="date" name="tanggal_selesai" class="filter-input" value="<?= $filter_tanggal_selesai ?>" placeholder="Sampai">
                    <select name="guru" class="filter-select">
                        <option value="">Semua Guru</option>
                        <?php foreach($guru_list as $guru): ?>
                        <option value="<?= $guru['id_guru'] ?>" <?= $filter_guru == $guru['id_guru'] ? 'selected' : '' ?>><?= htmlspecialchars($guru['nama']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="filter-btn">Filter</button>
                    <a href="laporan.php" class="filter-reset">Reset</a>
                </form>
                
                <div class="action-buttons">
                    <a href="?export_excel=1&<?= http_build_query($_GET) ?>" class="btn-excel">📊 Export Excel</a>
                    <a href="?export_pdf=1&<?= http_build_query($_GET) ?>" class="btn-pdf" target="_blank">📄 Export PDF</a>
                </div>
            </div>
        </div>
        
        <!-- Tabel Laporan -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Laporan Absensi & Gaji</div>
            </div>
            <div class="table-responsive">
                <?php if(count($laporan_data) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Guru</th>
                            <th>NIP</th>
                            <th>Total Hari</th>
                            <th>Total Jam</th>
                            <th>Terlambat</th>
                            <th>Gaji Pangkat</th>
                            <th>Gaji Absensi</th>
                            <th>Gaji Lembur</th>
                            <th>Total Gaji</th>
                            <th>Slip</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($laporan_data as $d): ?>
                        <tr>
                            <td data-label="No" class="text-center"><?= $no++ ?></td>
                            <td data-label="Nama Guru">
                                <strong><?= htmlspecialchars($d['nama']) ?></strong>
                                <div class="jabatan-text"><?= htmlspecialchars($d['jabatan']) ?></div>
                            </td>
                            <td data-label="NIP" class="text-center"><?= $d['nip'] ?></td>
                            <td data-label="Total Hari" class="text-center"><?= $d['total_hari'] ?> hari</td>
                            <td data-label="Total Jam" class="text-center"><?= $d['total_jam'] ?> jam</td>
                            <td data-label="Terlambat" class="text-center">
                                <span class="telat-badge <?= $d['total_menit_telat'] > 0 ? 'has-telat' : '' ?>">
                                    <?= $d['total_menit_telat'] ?> menit
                                </span>
                            </td>
                            <td data-label="Gaji Pangkat" class="text-right">Rp <?= number_format($d['gaji_pangkat'], 0, ',', '.') ?></td>
                            <td data-label="Gaji Absensi" class="text-right">
                                Rp <?= number_format($d['gaji_absensi'], 0, ',', '.') ?>
                                <div class="gaji-tipe">(<?= $d['tipe_gaji'] ?> <?= number_format($d['nilai_gaji'], 0, ',', '.') ?>)</div>
                            </td>
                            <td data-label="Gaji Lembur" class="text-right">Rp <?= number_format($d['gaji_lembur'], 0, ',', '.') ?></td>
                            <td data-label="Total Gaji" class="text-right total-gaji">Rp <?= number_format($d['total_gaji'], 0, ',', '.') ?></td>
                            <td data-label="Slip" class="text-center">
                                <a href="download_slip_pdf.php?id=<?= $d['id_guru'] ?>&<?= http_build_query($_GET) ?>" class="btn-slip" target="_blank">📄 Slip</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="grand-total">
                            <td colspan="9" class="text-right"><strong>GRAND TOTAL</strong></td>
                            <td class="text-right"><strong>Rp <?= number_format($total_gaji_keseluruhan, 0, ',', '.') ?></strong></td>
                            <td></td>
                        </tr>
                        <tr class="grand-total">
                            <td colspan="5" class="text-right"><strong>Total Terlambat</strong></td>
                            <td class="text-left"><strong><?= $total_menit_telat_all ?> menit</strong></td>
                            <td colspan="5"></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">📭 Tidak ada data laporan</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informasi Batas Waktu -->
        <div class="info-card">
            <div class="info-header">📋 Informasi Batas Waktu Terlambat</div>
            <div class="info-body">
                <div class="info-grid">
                    <?php foreach($jam_kerja as $jam_ke => $j): ?>
                    <div class="info-item">
                        <div class="info-label">Jam ke-<?= $jam_ke ?></div>
                        <div class="info-value"><?= substr($j['jam_mulai'], 0, 5) ?> WIB</div>
                        <div class="info-batas">Batas telat: <strong><?= substr($j['batas_telat'], 0, 5) ?> WIB</strong></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-note">
                    <span>ℹ️</span> Keterlambatan dihitung dalam MENIT jika absen melebihi batas waktu (jam mulai + 5 menit)
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
.filter-bar{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;margin-bottom:24px;padding-bottom:16px;border-bottom:1px solid #e2e8f0;}
.filter-info{display:flex;gap:12px;flex-wrap:wrap;}
.filter-badge{background:#f1f5f9;padding:6px 12px;border-radius:20px;font-size:12px;color:#475569;}
.filter-actions{display:flex;align-items:center;gap:16px;flex-wrap:wrap;}
.filter-form{display:flex;align-items:center;gap:8px;background:white;padding:4px 12px;border-radius:10px;border:1px solid #e2e8f0;flex-wrap:wrap;}
.filter-input,.filter-select{padding:6px 10px;border:1px solid #e2e8f0;border-radius:6px;font-size:12px;}
.filter-btn{background:#c41e3a;color:white;border:none;padding:6px 14px;border-radius:6px;cursor:pointer;}
.filter-reset{background:#e2e8f0;color:#1e293b;padding:6px 14px;border-radius:6px;text-decoration:none;}
.action-buttons{display:flex;gap:8px;}
.btn-excel,.btn-pdf{padding:6px 14px;border-radius:6px;text-decoration:none;font-size:12px;font-weight:500;}
.btn-excel{background:#10b981;color:white;}
.btn-pdf{background:#dc2626;color:white;}
.table-container{background:white;border-radius:20px;border:1px solid #e2e8f0;overflow:hidden;}
.table-header{padding:16px 20px;border-bottom:1px solid #e2e8f0;background:white;}
.table-title{font-weight:600;font-size:16px;color:#1e293b;}
.table-responsive{overflow-x:auto;}
.modern-table{width:100%;border-collapse:collapse;font-size:13px;}
.modern-table th,.modern-table td{padding:12px;border:1px solid #e2e8f0;text-align:left;vertical-align:middle;}
.modern-table th{background:#f8fafc;font-weight:600;}
.text-center{text-align:center;}
.text-right{text-align:right;}
.total-gaji{font-weight:700;color:#1e293b;}
.grand-total{background:#f1f5f9;font-weight:600;}
.jabatan-text{font-size:11px;color:#64748b;margin-top:2px;}
.gaji-tipe{font-size:10px;color:#94a3b8;}
.btn-slip{background:#3b82f6;color:white;padding:5px 12px;border-radius:6px;text-decoration:none;font-size:11px;}
.telat-badge{display:inline-block;padding:2px 8px;background:#e2e8f0;color:#64748b;border-radius:12px;font-size:11px;}
.telat-badge.has-telat{background:#fed7aa;color:#9a3412;}
.empty-state{text-align:center;padding:40px;color:#94a3b8;}

.info-card{background:white;border-radius:20px;border:1px solid #e2e8f0;overflow:hidden;margin-top:24px;}
.info-header{padding:14px 20px;background:#f8fafc;font-weight:600;border-bottom:1px solid #e2e8f0;}
.info-body{padding:20px;}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;}
.info-item{text-align:center;padding:12px;background:#f8fafc;border-radius:12px;}
.info-label{font-size:11px;color:#64748b;margin-bottom:4px;}
.info-value{font-size:16px;font-weight:600;color:#1e293b;}
.info-batas{font-size:11px;color:#c41e3a;margin-top:4px;}
.info-note{margin-top:16px;padding:12px;background:#fef3c7;border-radius:12px;font-size:12px;color:#92400e;display:flex;align-items:center;gap:8px;}

@media (max-width:768px){
.filter-bar,.filter-actions{flex-direction:column;align-items:stretch;}
.filter-form{flex-wrap:wrap;}
.filter-input,.filter-select{width:100%;}
.action-buttons a{flex:1;text-align:center;}
.modern-table th{display:none;}
.modern-table tr{display:block;margin-bottom:15px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;}
.modern-table td{display:flex;justify-content:space-between;padding:10px 15px;border:none;border-bottom:1px solid #e8ecf2;text-align:right;}
.modern-table td:before{content:attr(data-label);font-weight:600;width:45%;text-align:left;}
.info-grid{grid-template-columns:repeat(2,1fr);}
}
</style>

<?php require_once '../includes/footer.php'; ?>