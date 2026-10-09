<?php
$page_title = 'Dashboard - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];
$tanggal_hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM guru WHERE id_guru = ?");
$stmt->execute([$id_guru]);
$guru = $stmt->fetch();

// Ambil jam kerja untuk batas telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja");
$jam_kerja = [];
while ($row = $stmt_jam->fetch()) {
    $jam_kerja[$row['jam_ke']] = [
        'jam_mulai' => $row['jam_mulai'],
        'batas_telat' => date('H:i:s', strtotime($row['jam_mulai'] . ' +5 minutes'))
    ];
}

// Statistik absensi hari ini (hitung telat dalam menit)
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND a.tanggal = ?
");
$stmt->execute([$id_guru, $tanggal_hari_ini]);
$absensi_hari_ini_raw = $stmt->fetchAll();

$total_hadir_hari = 0;
$total_menit_telat_hari = 0;

foreach ($absensi_hari_ini_raw as $absen) {
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'] ?? date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $total_menit_telat_hari += $menit_telat;
    }
    $total_hadir_hari++;
}

// Statistik bulan ini
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
");
$stmt->execute([$id_guru, $bulan_ini]);
$absensi_bulan_raw = $stmt->fetchAll();

$total_hari_bulan = 0;
$total_jam_bulan = 0;
$total_menit_telat_bulan = 0;
$hari_tercatat = [];

foreach ($absensi_bulan_raw as $absen) {
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'] ?? date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $total_menit_telat_bulan += $menit_telat;
    }
    $total_jam_bulan++;
    if (!in_array($absen['tanggal'], $hari_tercatat)) {
        $hari_tercatat[] = $absen['tanggal'];
        $total_hari_bulan++;
    }
}

// HITUNG GAJI LEMBUR BULAN INI
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
    FROM ajuan 
    WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
    AND DATE_FORMAT(tanggal_mulai, '%Y-%m') = ?
");
$stmt->execute([$id_guru, $bulan_ini]);
$lembur = $stmt->fetch();
$total_gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;

// Cek status absensi hari ini (belum absen untuk jam yang sedang berlangsung)
$waktu_sekarang = date('H:i:s');
$stmt = $pdo->query("SELECT * FROM jam_kerja ORDER BY jam_ke");
$jam_kerja_list = $stmt->fetchAll();

$jam_sekarang = null;
$belum_absen = false;
$jam_ke_aktif = null;

foreach ($jam_kerja_list as $jam) {
    if ($waktu_sekarang >= $jam['jam_mulai'] && $waktu_sekarang <= $jam['jam_selesai']) {
        $jam_sekarang = $jam;
        $jam_ke_aktif = $jam['jam_ke'];
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE id_guru = ? AND tanggal = ? AND jam_ke = ?");
        $stmt->execute([$id_guru, $tanggal_hari_ini, $jam['jam_ke']]);
        if ($stmt->fetchColumn() == 0) {
            $belum_absen = true;
        }
        break;
    }
}

// Data absensi 7 hari terakhir untuk chart
$stmt = $pdo->prepare("
    SELECT 
        tanggal,
        COUNT(*) as total_absen
    FROM absensi 
    WHERE id_guru = ? AND tanggal >= DATE_SUB(?, INTERVAL 7 DAY)
    GROUP BY tanggal
    ORDER BY tanggal ASC
");
$stmt->execute([$id_guru, $tanggal_hari_ini]);
$chart_data = $stmt->fetchAll();

$max_absen = 1;
if (!empty($chart_data)) {
    $max_absen = max(array_column($chart_data, 'total_absen'));
    $max_absen = $max_absen > 0 ? $max_absen : 1;
}

// Generate 7 hari terakhir
$dates = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[$date] = 0;
}
foreach($chart_data as $d) {
    $dates[$d['tanggal']] = $d['total_absen'];
}

// Hitung estimasi gaji (termasuk gaji lembur)
$estimasi_gaji = 0;
$gaji_pangkat = $guru['gaji_pokok'] ?? 0;
$gaji_perjam = $guru['gaji_perjam'] ?? 0;
$gaji_perhari = $guru['gaji_perhari'] ?? 0;

if ($gaji_perjam > 0) {
    $gaji_dari_absensi = $total_jam_bulan * $gaji_perjam;
    $tipe_gaji = 'per jam';
    $nilai_gaji = $gaji_perjam;
} else {
    $gaji_dari_absensi = $total_hari_bulan * $gaji_perhari;
    $tipe_gaji = 'per hari';
    $nilai_gaji = $gaji_perhari;
}

$estimasi_gaji = $gaji_pangkat + $gaji_dari_absensi + $total_gaji_lembur;

// Ambil riwayat absensi hari ini untuk ditampilkan
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai, j.jam_selesai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND a.tanggal = ?
    ORDER BY a.jam_ke ASC
");
$stmt->execute([$id_guru, $tanggal_hari_ini]);
$riwayat_hari_ini = $stmt->fetchAll();

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h1>Selamat Datang, <?= htmlspecialchars($guru['nama']) ?>!</h1>
                <p>Semangat mengajar hari ini! 📚</p>
            </div>
            <div class="welcome-date">
                <div class="date-day"><?= date('d') ?></div>
                <div class="date-month"><?= date('F Y') ?></div>
            </div>
        </div>
        
        <!-- Alert Jika Belum Absen -->
        <?php if($belum_absen && $jam_sekarang): ?>
        <div class="alert-warning">
            <div class="alert-icon">⏰</div>
            <div class="alert-content">
                <div class="alert-title">Belum Absen!</div>
                <div class="alert-message">Anda belum melakukan absen untuk Jam ke-<?= $jam_sekarang['jam_ke'] ?> (<?= substr($jam_sekarang['jam_mulai'], 0, 5) ?> - <?= substr($jam_sekarang['jam_selesai'], 0, 5) ?>)</div>
            </div>
            <a href="absensi.php" class="alert-btn">Absen Sekarang</a>
        </div>
        <?php endif; ?>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-hadir">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $total_hadir_hari ?></div>
                <div class="stat-label">Hadir Hari Ini</div>
            </div>
            <div class="stat-card stat-telat">
                <div class="stat-icon">⏰</div>
                <div class="stat-value"><?= $total_menit_telat_hari ?> mnt</div>
                <div class="stat-label">Terlambat Hari Ini</div>
            </div>
            <div class="stat-card stat-hari">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= $total_hari_bulan ?> hari</div>
                <div class="stat-label">Total Hari Bulan Ini</div>
            </div>
            <div class="stat-card stat-jam">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value"><?= $total_jam_bulan ?> jam</div>
                <div class="stat-label">Total Jam Bulan Ini</div>
            </div>
        </div>
        
        <!-- Detail Terlambat -->
        <div class="telat-card">
            <div class="telat-icon">⏰</div>
            <div class="telat-content">
                <div class="telat-label">Total Keterlambatan Bulan Ini</div>
                <div class="telat-value <?= $total_menit_telat_bulan > 0 ? 'has-telat' : '' ?>">
                    <?= $total_menit_telat_bulan ?> menit
                </div>
                <?php if($total_menit_telat_bulan > 0): ?>
                <div class="telat-detail">⚠️ Perhatikan waktu kedatangan Anda</div>
                <?php else: ?>
                <div class="telat-detail">✓ Tepat waktu! Pertahankan</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Gaji Estimasi Card (Termasuk Lembur) -->
        <div class="gaji-card">
            <div class="gaji-icon">💰</div>
            <div class="gaji-content">
                <div class="gaji-label">Estimasi Gaji Bulan Ini</div>
                <div class="gaji-value">Rp <?= number_format($estimasi_gaji, 0, ',', '.') ?></div>
                <div class="gaji-detail">
                    <?php if($gaji_pangkat > 0): ?>
                        Gaji Pangkat: Rp <?= number_format($gaji_pangkat, 0, ',', '.') ?><br>
                    <?php endif; ?>
                    Gaji Absensi: <?= $total_jam_bulan ?> jam × Rp <?= number_format($nilai_gaji, 0, ',', '.') ?> = Rp <?= number_format($gaji_dari_absensi, 0, ',', '.') ?><br>
                    <?php if($total_gaji_lembur > 0): ?>
                        Gaji Lembur: Rp <?= number_format($total_gaji_lembur, 0, ',', '.') ?><br>
                    <?php endif; ?>
                    <strong>Total: Rp <?= number_format($estimasi_gaji, 0, ',', '.') ?></strong>
                </div>
            </div>
            <a href="gaji_saya.php" class="gaji-btn">Lihat Detail →</a>
        </div>
        
        <!-- Chart Absensi 7 Hari -->
        <div class="chart-card">
            <div class="chart-header">
                <span class="chart-title">📊 Grafik Absensi 7 Hari Terakhir</span>
            </div>
            <div class="chart-body">
                <div class="chart-container">
                    <?php foreach($dates as $date => $total): 
                        $height = ($total / $max_absen) * 120;
                        $height = max($height, 4);
                    ?>
                    <div class="chart-item">
                        <div class="chart-bar" style="height: <?= $height ?>px;"></div>
                        <div class="chart-label"><?= date('d/m', strtotime($date)) ?></div>
                        <div class="chart-value"><?= $total ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        
        <!-- Riwayat Absensi Hari Ini -->
        <div class="riwayat-card">
            <div class="riwayat-header">
                <span>📋 Riwayat Absensi Hari Ini</span>
                <span class="riwayat-date"><?= date('d F Y') ?></span>
            </div>
            <div class="riwayat-list">
                <?php if(count($riwayat_hari_ini) > 0): ?>
                    <?php foreach($riwayat_hari_ini as $absen): 
                        $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
                        $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'];
                        $is_telat = ($waktu_absen > $batas_telat);
                    ?>
                    <div class="riwayat-item">
                        <div class="riwayat-info">
                            <div class="riwayat-jam">Jam ke-<?= $absen['jam_ke'] ?></div>
                            <div class="riwayat-waktu"><?= substr($absen['jam_mulai'], 0, 5) ?> - <?= substr($absen['jam_selesai'], 0, 5) ?></div>
                        </div>
                        <div class="riwayat-time"><?= date('H:i', strtotime($absen['waktu_absen'])) ?></div>
                        <div class="riwayat-status <?= $is_telat ? 'status-telat' : 'status-hadir' ?>">
                            <?= $is_telat ? 'Terlambat' : 'Hadir' ?>
                        </div>
                        <?php if($absen['foto_selfie']): ?>
                            <button onclick="lihatFoto('<?= $absen['foto_selfie'] ?>')" class="riwayat-foto">📷</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="riwayat-empty">📭 Belum ada absensi hari ini</div>
                <?php endif; ?>
            </div>
            <div class="riwayat-footer">
                <a href="riwayat.php" class="riwayat-link">Lihat Semua Riwayat →</a>
            </div>
        </div>
        
        <!-- Informasi Batas Waktu -->
        <div class="info-card">
            <div class="info-header">📋 Informasi Batas Waktu</div>
            <div class="info-body">
                <div class="info-grid">
                    <?php foreach($jam_kerja as $jam_ke => $j): ?>
                    <div class="info-item">
                        <div class="info-label">Jam ke-<?= $jam_ke ?></div>
                        <div class="info-value"><?= substr($j['jam_mulai'], 0, 5) ?> WIB</div>
                        <div class="info-batas">Batas: <strong><?= substr($j['batas_telat'], 0, 5) ?> WIB</strong></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="info-note">
                    <span>ℹ️</span> Terlambat jika absen melebihi batas waktu (+5 menit dari jam mulai)
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Modal Foto -->
<div id="fotoModal" class="modal-foto">
    <div class="modal-foto-content">
        <h3>Foto Selfie</h3>
        <img id="fotoSelfie" src="">
        <button onclick="closeFotoModal()" class="btn-modal-close">Tutup</button>
    </div>
</div>

<script>
    function lihatFoto(filename) {
        document.getElementById('fotoSelfie').src = '../assets/uploads/selfie_absensi/' + filename;
        document.getElementById('fotoModal').style.display = 'flex';
    }
    
    function closeFotoModal() {
        document.getElementById('fotoModal').style.display = 'none';
    }
    
    document.getElementById('fotoModal').addEventListener('click', function(e) {
        if (e.target === this) closeFotoModal();
    });
</script>

<style>
    .welcome-banner {
        background: linear-gradient(135deg, #c41e3a, #a01830);
        border-radius: 20px;
        padding: 20px;
        margin-bottom: 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: white;
        flex-wrap: wrap;
    }
    .welcome-text h1 { font-size: 20px; margin-bottom: 4px; }
    .welcome-text p { font-size: 13px; opacity: 0.9; }
    .welcome-date { text-align: center; background: rgba(255,255,255,0.2); padding: 8px 16px; border-radius: 16px; }
    .date-day { font-size: 28px; font-weight: 700; line-height: 1; }
    .date-month { font-size: 11px; opacity: 0.9; }
    
    .alert-warning {
        background: #fef3c7;
        border-radius: 16px;
        padding: 16px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        border-left: 4px solid #f59e0b;
    }
    .alert-icon { font-size: 28px; }
    .alert-content { flex: 1; }
    .alert-title { font-weight: 600; color: #92400e; }
    .alert-message { font-size: 12px; color: #b45309; }
    .alert-btn { background: #f59e0b; color: white; padding: 8px 20px; border-radius: 10px; text-decoration: none; font-size: 13px; }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }
    .stat-card {
        border-radius: 20px;
        padding: 18px;
        color: white;
    }
    .stat-hadir { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-telat { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-hari { background: linear-gradient(135deg, #3b82f6, #2563eb); }
    .stat-jam { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .stat-icon { font-size: 28px; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; margin-bottom: 4px; }
    .stat-label { font-size: 12px; opacity: 0.9; }
    
    .telat-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .telat-icon { font-size: 40px; }
    .telat-content { flex: 1; }
    .telat-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .telat-value { font-size: 28px; font-weight: 700; color: #1e293b; }
    .telat-value.has-telat { color: #ea580c; }
    .telat-detail { font-size: 11px; color: #64748b; margin-top: 4px; }
    
    .gaji-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        margin-bottom: 24px;
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
    }
    .gaji-icon { font-size: 48px; background: #fef2f2; width: 70px; height: 70px; border-radius: 20px; display: flex; align-items: center; justify-content: center; }
    .gaji-content { flex: 1; }
    .gaji-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .gaji-value { font-size: 24px; font-weight: 700; color: #1e293b; }
    .gaji-detail { font-size: 11px; color: #64748b; margin-top: 4px; line-height: 1.5; }
    .gaji-btn { background: #c41e3a; color: white; padding: 10px 20px; border-radius: 12px; text-decoration: none; font-size: 13px; }
    
    .chart-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 24px; }
    .chart-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #fafcff; }
    .chart-title { font-weight: 600; font-size: 14px; }
    .chart-body { padding: 20px; }
    .chart-container { display: flex; align-items: flex-end; gap: 12px; height: 160px; }
    .chart-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; }
    .chart-bar { width: 100%; max-width: 35px; background: #c41e3a; border-radius: 8px; }
    .chart-label { font-size: 10px; color: #64748b; }
    .chart-value { font-size: 11px; font-weight: 600; color: #1e293b; }
    
    .riwayat-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-bottom: 24px; }
    .riwayat-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #fafcff; font-weight: 600; font-size: 14px; }
    .riwayat-date { font-size: 11px; color: #64748b; font-weight: normal; }
    .riwayat-list { padding: 0; }
    .riwayat-item { display: flex; justify-content: space-between; align-items: center; padding: 12px 20px; border-bottom: 1px solid #e8ecf2; flex-wrap: wrap; gap: 8px; }
    .riwayat-jam { font-weight: 600; font-size: 13px; }
    .riwayat-waktu { font-size: 10px; color: #94a3b8; margin-top: 2px; }
    .riwayat-time { font-size: 12px; color: #64748b; }
    .riwayat-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 500; }
    .status-hadir { background: #d1fae5; color: #065f46; }
    .status-telat { background: #fed7aa; color: #9a3412; }
    .riwayat-foto { background: none; border: none; font-size: 18px; cursor: pointer; }
    .riwayat-empty { text-align: center; padding: 40px; color: #94a3b8; font-size: 13px; }
    .riwayat-footer { padding: 12px 20px; border-top: 1px solid #e2e8f0; text-align: center; background: #fafcff; }
    .riwayat-link { color: #c41e3a; text-decoration: none; font-size: 12px; font-weight: 500; }
    
    .info-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
    .info-header { padding: 14px 20px; background: #f8fafc; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
    .info-body { padding: 20px; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; }
    .info-item { text-align: center; padding: 10px; background: #f8fafc; border-radius: 12px; }
    .info-label { font-size: 10px; color: #64748b; margin-bottom: 4px; }
    .info-value { font-size: 14px; font-weight: 600; color: #1e293b; }
    .info-batas { font-size: 10px; color: #c41e3a; margin-top: 2px; }
    .info-note { margin-top: 16px; padding: 10px; background: #fef3c7; border-radius: 10px; font-size: 11px; color: #92400e; display: flex; align-items: center; gap: 6px; }
    
    .modal-foto { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1100; align-items: center; justify-content: center; }
    .modal-foto-content { background: white; border-radius: 16px; max-width: 350px; width: 90%; padding: 20px; text-align: center; }
    .modal-foto-content img { width: 100%; border-radius: 12px; margin-bottom: 16px; }
    .btn-modal-close { background: #64748b; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; width: 100%; }
    
    @media (max-width: 768px) {
        .content-wrapper { padding: 16px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .stat-card { padding: 14px; }
        .stat-icon { font-size: 22px; }
        .stat-value { font-size: 22px; }
        .gaji-card { flex-direction: column; text-align: center; }
        .gaji-btn { width: 100%; text-align: center; }
        .telat-card { flex-direction: column; text-align: center; }
        .chart-container { gap: 8px; height: 130px; }
        .chart-bar { max-width: 25px; }
        .welcome-banner { flex-direction: column; text-align: center; gap: 12px; }
        .info-grid { grid-template-columns: repeat(2, 1fr); }
    }
    
    @media (max-width: 480px) {
        .stats-grid { gap: 8px; }
        .stat-value { font-size: 18px; }
        .stat-label { font-size: 10px; }
        .telat-value { font-size: 22px; }
        .gaji-value { font-size: 20px; }
    }
</style>

<?php require_once '../includes/footer.php'; ?>