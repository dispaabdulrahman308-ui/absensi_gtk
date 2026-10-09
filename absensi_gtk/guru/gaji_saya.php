<?php
$page_title = 'Gaji Saya - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];

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

// Filter bulan
$bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$tahun = substr($bulan, 0, 4);
$bulan_angka = substr($bulan, 5, 2);
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// ========== HITUNG GAJI REALTIME DARI ABSENSI ==========
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND DATE_FORMAT(a.tanggal, '%Y-%m') = ? AND a.status = 'hadir'
");
$stmt->execute([$id_guru, $bulan]);
$absensi_raw = $stmt->fetchAll();

$total_hari = 0;
$total_jam = 0;
$total_menit_telat = 0;
$hari_tercatat = [];

foreach ($absensi_raw as $absen) {
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'] ?? date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $total_menit_telat += $menit_telat;
    }
    $total_jam++;
    if (!in_array($absen['tanggal'], $hari_tercatat)) {
        $hari_tercatat[] = $absen['tanggal'];
        $total_hari++;
    }
}

// ========== HITUNG GAJI LEMBUR DARI AJUAN (REALTIME) ==========
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
    FROM ajuan 
    WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
    AND DATE_FORMAT(tanggal_mulai, '%Y-%m') = ?
");
$stmt->execute([$id_guru, $bulan]);
$lembur = $stmt->fetch();
$gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;

// Data gaji dari tabel guru
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

// TOTAL GAJI (BENAR - REALTIME)
$total_gaji = $gaji_pangkat + $gaji_absensi + $gaji_lembur;

// ========== AMBIL REKAP PER BULAN DARI GAJI (HISTORI) ==========
// Tapi kita hitung ulang juga agar sinkron
$rekap_bulanan = [];

// Ambil semua bulan yang ada di absensi
$stmt = $pdo->prepare("
    SELECT DISTINCT DATE_FORMAT(tanggal, '%Y-%m') as bulan
    FROM absensi 
    WHERE id_guru = ?
    ORDER BY bulan DESC
");
$stmt->execute([$id_guru]);
$bulan_list = $stmt->fetchAll();

foreach ($bulan_list as $b) {
    $bln = $b['bulan'];
    $thn = substr($bln, 0, 4);
    $bln_angka = substr($bln, 5, 2);
    
    // Hitung absensi untuk bulan tersebut
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT tanggal) as total_hari, COUNT(*) as total_jam
        FROM absensi 
        WHERE id_guru = ? AND DATE_FORMAT(tanggal, '%Y-%m') = ? AND status = 'hadir'
    ");
    $stmt->execute([$id_guru, $bln]);
    $absen_bulan = $stmt->fetch();
    
    // Hitung lembur untuk bulan tersebut
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(gaji_lembur), 0) as total_gaji_lembur
        FROM ajuan 
        WHERE id_guru = ? AND jenis = 'lembur' AND status = 'disetujui'
        AND DATE_FORMAT(tanggal_mulai, '%Y-%m') = ?
    ");
    $stmt->execute([$id_guru, $bln]);
    $lembur_bulan = $stmt->fetch();
    
    $total_hari_bulan = $absen_bulan['total_hari'] ?? 0;
    $total_jam_bulan = $absen_bulan['total_jam'] ?? 0;
    $gaji_lembur_bulan = $lembur_bulan['total_gaji_lembur'] ?? 0;
    
    // Hitung gaji
    if ($gaji_perjam > 0) {
        $gaji_absensi_bulan = $total_jam_bulan * $gaji_perjam;
    } else {
        $gaji_absensi_bulan = $total_hari_bulan * $gaji_perhari;
    }
    
    $total_gaji_bulan = $gaji_pangkat + $gaji_absensi_bulan + $gaji_lembur_bulan;
    
    $rekap_bulanan[] = [
        'bulan' => $bln,
        'total_jam' => $total_jam_bulan,
        'total_hari' => $total_hari_bulan,
        'gaji_pangkat' => $gaji_pangkat,
        'gaji_lembur' => $gaji_lembur_bulan,
        'total_gaji' => $total_gaji_bulan
    ];
}

// Jika bulan yang dipilih tidak ada di rekap, tambahkan
$bulan_dipilih = $bulan;
$found = false;
foreach ($rekap_bulanan as $r) {
    if ($r['bulan'] == $bulan_dipilih) {
        $found = true;
        break;
    }
}
if (!$found) {
    array_unshift($rekap_bulanan, [
        'bulan' => $bulan_dipilih,
        'total_jam' => $total_jam,
        'total_hari' => $total_hari,
        'gaji_pangkat' => $gaji_pangkat,
        'gaji_lembur' => $gaji_lembur,
        'total_gaji' => $total_gaji
    ]);
}

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <div class="page-header">
            <h1 class="page-title">💰 Gaji Saya</h1>
            <p class="page-subtitle">Lihat detail gaji dan download slip</p>
        </div>
        
        <!-- Filter Bulan -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">Pilih Periode</label>
                    <input type="month" name="bulan" class="filter-input" value="<?= $bulan ?>">
                </div>
                <button type="submit" class="btn-filter">Tampilkan</button>
                <a href="gaji_saya.php" class="btn-reset">Reset</a>
            </form>
        </div>
        
        <!-- Detail Gaji - Card Modern (Data REALTIME) -->
        <div class="gaji-detail-grid">
            <div class="gaji-card">
                <div class="gaji-card-icon">📊</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Total Jam Hadir</div>
                    <div class="gaji-card-value"><?= $total_jam ?> <span class="unit">jam</span></div>
                </div>
            </div>
            <div class="gaji-card">
                <div class="gaji-card-icon">📅</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Total Hari Hadir</div>
                    <div class="gaji-card-value"><?= $total_hari ?> <span class="unit">hari</span></div>
                </div>
            </div>
            <div class="gaji-card">
                <div class="gaji-card-icon">⏰</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Terlambat</div>
                    <div class="gaji-card-value <?= $total_menit_telat > 0 ? 'telat' : '' ?>">
                        <?= $total_menit_telat ?> <span class="unit">menit</span>
                    </div>
                </div>
            </div>
            <div class="gaji-card">
                <div class="gaji-card-icon">💰</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Gaji Pangkat</div>
                    <div class="gaji-card-value">Rp <?= number_format($gaji_pangkat, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="gaji-card">
                <div class="gaji-card-icon">✅</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Gaji Absensi</div>
                    <div class="gaji-card-value">Rp <?= number_format($gaji_absensi, 0, ',', '.') ?></div>
                    <div class="gaji-tipe">(<?= $tipe_gaji ?> Rp <?= number_format($nilai_gaji, 0, ',', '.') ?>)</div>
                </div>
            </div>
            <div class="gaji-card">
                <div class="gaji-card-icon">💪</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">Gaji Lembur</div>
                    <div class="gaji-card-value">Rp <?= number_format($gaji_lembur, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="gaji-card total">
                <div class="gaji-card-icon">🏆</div>
                <div class="gaji-card-content">
                    <div class="gaji-card-label">TOTAL GAJI</div>
                    <div class="gaji-card-value">Rp <?= number_format($total_gaji, 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        
        <!-- Tombol Download Slip -->
        <div class="slip-button-container">
            <a href="../admin/download_slip_pdf.php?id=<?= $id_guru ?>&bulan=<?= $bulan ?>" class="btn-download" target="_blank">
                📄 Download Slip Gaji
            </a>
        </div>
        
        <!-- Rekap Gaji per Bulan -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📊 Rekap Gaji per Bulan</div>
                <div class="table-info">Total: <?= count($rekap_bulanan) ?> bulan</div>
            </div>
            <div class="table-responsive">
                <?php if(count($rekap_bulanan) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Periode</th>
                            <th>Total Jam</th>
                            <th>Total Hari</th>
                            <th>Gaji Pangkat</th>
                            <th>Gaji Lembur</th>
                            <th>Total Gaji</th>
                            <th>Slip</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($rekap_bulanan as $r): 
                            $thn = substr($r['bulan'], 0, 4);
                            $bln = substr($r['bulan'], 5, 2);
                            $periode = $nama_bulan[$bln] . ' ' . $thn;
                            $is_current = ($r['bulan'] == $bulan);
                        ?>
                        <tr class="<?= $is_current ? 'current-row' : '' ?>">
                            <td data-label="Periode"><?= $periode ?></td>
                            <td data-label="Total Jam" class="text-center"><?= $r['total_jam'] ?> jam</td>
                            <td data-label="Total Hari" class="text-center"><?= $r['total_hari'] ?> hari</td>
                            <td data-label="Gaji Pangkat" class="text-right">Rp <?= number_format($r['gaji_pangkat'], 0, ',', '.') ?></td>
                            <td data-label="Gaji Lembur" class="text-right">Rp <?= number_format($r['gaji_lembur'], 0, ',', '.') ?></td>
                            <td data-label="Total Gaji" class="text-right total-gaji">Rp <?= number_format($r['total_gaji'], 0, ',', '.') ?></td>
                            <td data-label="Slip" class="text-center">
                                <a href="../admin/download_slip_pdf.php?id=<?= $id_guru ?>&bulan=<?= $r['bulan'] ?>" class="btn-slip" target="_blank">📄 Slip</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">📭 Belum ada data gaji</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Info Gaji -->
        <div class="info-card">
            <div class="info-header">
                <span>ℹ️</span>
                <span>Informasi Gaji</span>
            </div>
            <div class="info-body">
                <div class="info-row">
                    <div class="info-label">Gaji Per Jam</div>
                    <div class="info-value"><?= $guru['gaji_perjam'] ? 'Rp ' . number_format($guru['gaji_perjam'], 0, ',', '.') : 'Belum diatur' ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gaji Per Hari</div>
                    <div class="info-value"><?= $guru['gaji_perhari'] ? 'Rp ' . number_format($guru['gaji_perhari'], 0, ',', '.') : 'Belum diatur' ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gaji Pangkat</div>
                    <div class="info-value"><?= $guru['gaji_pokok'] ? 'Rp ' . number_format($guru['gaji_pokok'], 0, ',', '.') : 'Belum diatur' ?></div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" class="toast-notification">
    <div class="toast-content">
        <span id="toastIcon"></span>
        <span id="toastMessage"></span>
        <button class="toast-close" onclick="closeToast()">&times;</button>
    </div>
</div>

<script>
    function showToast(type, message) {
        const toast = document.getElementById('toastNotification');
        const toastIcon = document.getElementById('toastIcon');
        const toastMessage = document.getElementById('toastMessage');
        
        if (type === 'success') {
            toast.style.borderLeftColor = '#10b981';
            toastIcon.innerHTML = '✓';
            toastIcon.style.color = '#10b981';
        } else {
            toast.style.borderLeftColor = '#dc2626';
            toastIcon.innerHTML = '⚠️';
            toastIcon.style.color = '#dc2626';
        }
        
        toastMessage.innerHTML = message;
        toast.classList.add('show');
        
        setTimeout(() => {
            toast.classList.remove('show');
        }, 4000);
    }
    
    function closeToast() {
        document.getElementById('toastNotification').classList.remove('show');
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
</script>

<style>
    .filter-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        margin-bottom: 24px;
    }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 180px;
    }
    .filter-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
    }
    .filter-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
    }
    .btn-filter {
        background: #c41e3a;
        color: white;
        border: none;
        padding: 10px 24px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 500;
    }
    .btn-reset {
        background: #e2e8f0;
        color: #1e293b;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
    }
    
    .gaji-detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .gaji-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        padding: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .gaji-card.total {
        background: linear-gradient(135deg, #c41e3a, #a01830);
        border: none;
        color: white;
    }
    .gaji-card.total .gaji-card-icon { color: rgba(255,255,255,0.9); }
    .gaji-card.total .gaji-card-label { color: rgba(255,255,255,0.8); }
    .gaji-card.total .gaji-card-value { color: white; }
    .gaji-card-icon { font-size: 32px; color: #c41e3a; }
    .gaji-card-content { flex: 1; }
    .gaji-card-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .gaji-card-value { font-size: 20px; font-weight: 700; color: #1e293b; }
    .gaji-card-value .unit { font-size: 12px; font-weight: normal; color: #64748b; }
    .gaji-card-value.telat { color: #ea580c; }
    .gaji-tipe { font-size: 10px; color: #94a3b8; margin-top: 2px; }
    
    .slip-button-container {
        margin-bottom: 32px;
        text-align: right;
    }
    .btn-download {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        background: #10b981;
        color: white;
        padding: 12px 24px;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
    }
    
    .table-container {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 24px;
    }
    .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
    }
    .table-title { font-weight: 600; font-size: 16px; color: #1e293b; }
    .table-info { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 4px 12px; border-radius: 20px; }
    .table-responsive { overflow-x: auto; }
    .modern-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modern-table th, .modern-table td { padding: 12px 16px; border: 1px solid #e2e8f0; text-align: left; }
    .modern-table th { background: #f8fafc; font-weight: 600; white-space: nowrap; }
    .modern-table td.total-gaji { font-weight: 700; color: #1e293b; }
    .current-row { background: #fef3c7; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    
    .btn-slip {
        background: #3b82f6;
        color: white;
        padding: 6px 12px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 11px;
        display: inline-block;
    }
    
    .info-card {
        background: #f8fafc;
        border-radius: 16px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
    }
    .info-header {
        padding: 14px 18px;
        background: #f1f5f9;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #e2e8f0;
    }
    .info-body { padding: 16px 18px; }
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    .info-row:last-child { border-bottom: none; }
    .info-label { font-size: 13px; color: #64748b; }
    .info-value { font-size: 14px; font-weight: 600; color: #1e293b; }
    
    .empty-state { text-align: center; padding: 40px; color: #94a3b8; }
    
    .toast-notification {
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 9999;
        background: white;
        border-radius: 12px;
        padding: 14px 20px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        min-width: 280px;
        border-left: 4px solid;
        display: none;
    }
    .toast-notification.show { display: block; }
    .toast-content { display: flex; align-items: center; gap: 12px; }
    .toast-close { background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; margin-left: auto; }
    
    @media (max-width: 768px) {
        .gaji-detail-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .gaji-card { padding: 15px; }
        .gaji-card-icon { font-size: 24px; }
        .gaji-card-value { font-size: 16px; }
        .slip-button-container { text-align: center; }
        .btn-download { width: 100%; justify-content: center; }
        .modern-table thead { display: none; }
        .modern-table tr {
            display: block;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            overflow: hidden;
        }
        .modern-table td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 15px;
            border: none;
            border-bottom: 1px solid #e8ecf2;
            text-align: right;
        }
        .modern-table td:before {
            content: attr(data-label);
            font-weight: 600;
            color: #475569;
            text-align: left;
            width: 45%;
        }
        .filter-form { flex-direction: column; }
        .btn-filter, .btn-reset { width: 100%; text-align: center; }
        .toast-notification { top: 10px; right: 10px; left: 10px; min-width: auto; }
    }
    
    @media (max-width: 480px) {
        .gaji-detail-grid { grid-template-columns: 1fr; }
        .gaji-card { padding: 12px; }
        .modern-table td { font-size: 12px; padding: 8px 12px; }
        .modern-table td:before { font-size: 11px; width: 50%; }
    }
</style>

<?php require_once '../includes/footer.php'; ?>