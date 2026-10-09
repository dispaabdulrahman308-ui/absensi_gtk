<?php
$page_title = 'Riwayat Absensi - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];

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
$filter_bulan = isset($_GET['bulan']) ? $_GET['bulan'] : date('Y-m');
$tahun = substr($filter_bulan, 0, 4);
$bulan_angka = substr($filter_bulan, 5, 2);
$nama_bulan = [
    '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
    '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
    '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
];

// Ambil data absensi
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai, j.jam_selesai
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND DATE_FORMAT(a.tanggal, '%Y-%m') = ?
    ORDER BY a.tanggal DESC, a.jam_ke ASC
");
$stmt->execute([$id_guru, $filter_bulan]);
$absensi_raw = $stmt->fetchAll();

// Proses data untuk menambahkan keterlambatan dalam menit
$absensi_list = [];
$total_hadir = 0;
$total_telat = 0;
$total_menit_telat = 0;
$total_hari = 0;
$hari_tercatat = [];

foreach ($absensi_raw as $absen) {
    $waktu_absen = date('H:i:s', strtotime($absen['waktu_absen']));
    $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'] ?? date('H:i:s', strtotime($absen['jam_mulai'] . ' +5 minutes'));
    
    $menit_telat = 0;
    $is_telat = false;
    if ($waktu_absen > $batas_telat) {
        $waktu_absen_ts = strtotime($waktu_absen);
        $batas_ts = strtotime($batas_telat);
        $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
        $menit_telat = max($menit_telat, 0);
        $is_telat = true;
        $total_telat++;
        $total_menit_telat += $menit_telat;
    } else {
        $total_hadir++;
    }
    
    if (!in_array($absen['tanggal'], $hari_tercatat)) {
        $hari_tercatat[] = $absen['tanggal'];
    }
    
    $absensi_list[] = [
        'id_absensi' => $absen['id_absensi'],
        'tanggal' => $absen['tanggal'],
        'jam_ke' => $absen['jam_ke'],
        'jam_mulai' => $absen['jam_mulai'],
        'jam_selesai' => $absen['jam_selesai'],
        'waktu_absen' => $absen['waktu_absen'],
        'status' => $absen['status'],
        'foto_selfie' => $absen['foto_selfie'],
        'menit_telat' => $menit_telat,
        'is_telat' => $is_telat
    ];
}
$total_hari = count($hari_tercatat);

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <div class="page-header">
            <h1 class="page-title">📝 Riwayat Absensi</h1>
            <p class="page-subtitle">Lihat riwayat absensi lengkap</p>
        </div>
        
        <!-- Filter Bulan -->
        <div class="filter-card">
            <form method="GET" action="" class="filter-form">
                <div class="filter-group">
                    <label class="filter-label">Pilih Periode</label>
                    <input type="month" name="bulan" class="filter-input" value="<?= $filter_bulan ?>">
                </div>
                <button type="submit" class="btn-filter">Tampilkan</button>
                <a href="riwayat.php" class="btn-reset">Reset</a>
            </form>
        </div>
        
        <!-- Statistik Card Berwarna -->
        <div class="stats-grid">
            <div class="stat-card stat-hadir">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $total_hadir ?></div>
                <div class="stat-label">Hadir</div>
            </div>
            <div class="stat-card stat-telat">
                <div class="stat-icon">⏰</div>
                <div class="stat-value"><?= $total_telat ?></div>
                <div class="stat-label">Terlambat</div>
            </div>
            <div class="stat-card stat-menit">
                <div class="stat-icon">⏱️</div>
                <div class="stat-value"><?= $total_menit_telat ?></div>
                <div class="stat-label">Total Terlambat (menit)</div>
            </div>
            <div class="stat-card stat-hari">
                <div class="stat-icon">📅</div>
                <div class="stat-value"><?= $total_hari ?></div>
                <div class="stat-label">Total Hari</div>
            </div>
        </div>
        
        <!-- Tabel Riwayat -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Daftar Absensi</div>
                <div class="table-info">Periode: <?= $nama_bulan[$bulan_angka] ?> <?= $tahun ?></div>
            </div>
            <div class="table-responsive">
                <?php if(count($absensi_list) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Hari</th>
                            <th>Jam</th>
                            <th>Waktu Absen</th>
                            <th>Status</th>
                            <th>Keterlambatan</th>
                            <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $hari_indonesia = [
                            'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu',
                            'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu', 'Sunday' => 'Minggu'
                        ];
                        foreach($absensi_list as $absen): 
                        ?>
                        <tr>
                            <td data-label="Tanggal"><?= date('d/m/Y', strtotime($absen['tanggal'])) ?></td>
                            <td data-label="Hari"><?= $hari_indonesia[date('l', strtotime($absen['tanggal']))] ?></td>
                            <td data-label="Jam">
                                <strong>Jam ke-<?= $absen['jam_ke'] ?></strong><br>
                                <span class="time-text"><?= substr($absen['jam_mulai'], 0, 5) ?> - <?= substr($absen['jam_selesai'], 0, 5) ?></span>
                            </td>
                            <td data-label="Waktu Absen"><?= date('H:i:s', strtotime($absen['waktu_absen'])) ?></td>
                            <td data-label="Status">
                                <span class="status-badge <?= $absen['is_telat'] ? 'status-telat' : 'status-hadir' ?>">
                                    <?= $absen['is_telat'] ? 'Terlambat' : 'Hadir' ?>
                                </span>
                            </td>
                            <td data-label="Keterlambatan" class="text-center">
                                <?php if($absen['is_telat']): ?>
                                    <span class="late-badge"><?= $absen['menit_telat'] ?> menit</span>
                                <?php else: ?>
                                    <span class="ontime-badge">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Foto">
                                <?php if($absen['foto_selfie']): ?>
                                    <button onclick="lihatFoto('<?= $absen['foto_selfie'] ?>')" class="btn-foto">📷 Lihat</button>
                                <?php else: ?>
                                    <span class="no-photo">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">📭 Belum ada data absensi untuk periode ini</div>
                <?php endif; ?>
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
    /* Filter Card */
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
    
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 20px;
        margin-bottom: 24px;
    }
    
    .stat-card {
        border-radius: 20px;
        padding: 20px;
        text-align: center;
        color: white;
    }
    
    .stat-hadir {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .stat-telat {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .stat-menit {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    
    .stat-hari {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }
    
    .stat-icon {
        font-size: 32px;
        margin-bottom: 8px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        margin-bottom: 4px;
    }
    
    .stat-label {
        font-size: 12px;
        opacity: 0.9;
    }
    
    /* Table Container */
    .table-container {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .table-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        background: white;
    }
    
    .table-title {
        font-weight: 600;
        font-size: 16px;
        color: #1e293b;
    }
    
    .table-info {
        font-size: 12px;
        color: #64748b;
        background: #f1f5f9;
        padding: 4px 12px;
        border-radius: 20px;
    }
    
    .table-responsive {
        overflow-x: auto;
    }
    
    .modern-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    
    .modern-table th,
    .modern-table td {
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: middle;
    }
    
    .modern-table th {
        background: #f8fafc;
        color: #475569;
        font-weight: 600;
        font-size: 12px;
        white-space: nowrap;
    }
    
    .modern-table tr:hover td {
        background: #fafcff;
    }
    
    .time-text {
        font-size: 10px;
        color: #94a3b8;
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .status-hadir {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-telat {
        background: #fed7aa;
        color: #9a3412;
    }
    
    .late-badge {
        background: #fed7aa;
        color: #9a3412;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        display: inline-block;
    }
    
    .ontime-badge {
        color: #94a3b8;
        font-size: 11px;
    }
    
    .btn-foto {
        background: #8b5cf6;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 11px;
    }
    
    .btn-foto:hover {
        background: #7c3aed;
    }
    
    .no-photo {
        color: #94a3b8;
        font-size: 12px;
    }
    
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }
    
    /* Info Card */
    .info-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-top: 24px;
    }
    
    .info-header {
        padding: 14px 20px;
        background: #f8fafc;
        font-weight: 600;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .info-body {
        padding: 20px;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
        gap: 12px;
    }
    
    .info-item {
        text-align: center;
        padding: 10px;
        background: #f8fafc;
        border-radius: 12px;
    }
    
    .info-label {
        font-size: 10px;
        color: #64748b;
        margin-bottom: 4px;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }
    
    .info-batas {
        font-size: 10px;
        color: #c41e3a;
        margin-top: 2px;
    }
    
    .info-note {
        margin-top: 16px;
        padding: 10px;
        background: #fef3c7;
        border-radius: 10px;
        font-size: 11px;
        color: #92400e;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    /* Modal Foto */
    .modal-foto {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.8);
        z-index: 1100;
        align-items: center;
        justify-content: center;
    }
    
    .modal-foto-content {
        background: white;
        border-radius: 16px;
        max-width: 400px;
        width: 90%;
        padding: 20px;
        text-align: center;
    }
    
    .modal-foto-content h3 {
        margin-bottom: 16px;
        font-size: 16px;
    }
    
    .modal-foto-content img {
        width: 100%;
        border-radius: 12px;
        margin-bottom: 16px;
    }
    
    .btn-modal-close {
        background: #64748b;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .stat-card {
            padding: 15px;
        }
        
        .stat-icon {
            font-size: 24px;
        }
        
        .stat-value {
            font-size: 22px;
        }
        
        .stat-label {
            font-size: 11px;
        }
        
        .modern-table thead {
            display: none;
        }
        
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
            width: 40%;
        }
        
        .modern-table td:last-child {
            border-bottom: none;
        }
        
        .table-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .filter-form {
            flex-direction: column;
        }
        
        .btn-filter, .btn-reset {
            width: 100%;
            text-align: center;
        }
        
        .info-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
    
    @media (max-width: 480px) {
        .content-wrapper {
            padding: 12px;
        }
        
        .stats-grid {
            gap: 8px;
        }
        
        .stat-card {
            padding: 12px;
        }
        
        .stat-value {
            font-size: 18px;
        }
        
        .modern-table td {
            font-size: 12px;
            padding: 8px 12px;
        }
        
        .modern-table td:before {
            font-size: 11px;
            width: 45%;
        }
        
        .btn-foto {
            padding: 4px 10px;
            font-size: 10px;
        }
        
        .info-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>