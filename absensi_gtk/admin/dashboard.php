<?php
$page_title = 'Dashboard - Admin SD Inpres Temi';
require_once '../config/database.php';
require_once '../includes/header.php';
require_once '../includes/sidebar.php';

$tanggal_hari_ini = date('Y-m-d');
$bulan_ini = date('Y-m');

// Statistik hari ini
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT a.id_guru) as total_absen,
        COUNT(CASE WHEN a.status = 'hadir' THEN 1 END) as hadir,
        COUNT(CASE WHEN a.status = 'telat' THEN 1 END) as telat
    FROM absensi a 
    WHERE a.tanggal = ?
");
$stmt->execute([$tanggal_hari_ini]);
$stats_hari_ini = $stmt->fetch();

// Total guru aktif
$stmt = $pdo->query("SELECT COUNT(*) as total FROM guru WHERE status = 'aktif' AND role = 'guru'");
$total_guru = $stmt->fetch();

// Ajuan pending
$stmt = $pdo->prepare("SELECT COUNT(*) as pending FROM ajuan WHERE status = 'pending'");
$stmt->execute();
$ajuan_pending = $stmt->fetch();

// Data absensi 7 hari terakhir
$stmt = $pdo->prepare("
    SELECT 
        tanggal,
        COUNT(*) as total_absen
    FROM absensi 
    WHERE tanggal >= DATE_SUB(?, INTERVAL 7 DAY)
    GROUP BY tanggal
    ORDER BY tanggal ASC
");
$stmt->execute([$tanggal_hari_ini]);
$chart_data = $stmt->fetchAll();

$max_absen = 1;
if (!empty($chart_data)) {
    $max_absen = max(array_column($chart_data, 'total_absen'));
    $max_absen = $max_absen > 0 ? $max_absen : 1;
}

// Data absensi per jam (termasuk deteksi terlambat berdasarkan jam mulai)
$stmt = $pdo->prepare("
    SELECT 
        j.jam_ke,
        j.jam_mulai,
        j.jam_selesai,
        COUNT(a.id_absensi) as jumlah_absen,
        COUNT(CASE WHEN a.status = 'telat' OR (a.status = 'hadir' AND TIME(a.waktu_absen) > ADDTIME(j.jam_mulai, '00:05:00')) THEN 1 END) as jumlah_telat
    FROM jam_kerja j
    LEFT JOIN absensi a ON a.jam_ke = j.jam_ke AND a.tanggal = ?
    GROUP BY j.jam_ke, j.jam_mulai, j.jam_selesai
    ORDER BY j.jam_ke
");
$stmt->execute([$tanggal_hari_ini]);
$absensi_per_jam = $stmt->fetchAll();

// Hitung total telat dari perhitungan per jam
$total_telat_hari_ini = 0;
foreach ($absensi_per_jam as $jam) {
    $total_telat_hari_ini += $jam['jumlah_telat'];
}

// Update statistik telat dengan nilai yang benar
$stats_hari_ini['telat'] = $total_telat_hari_ini;
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">Dashboard</h1>
            <p class="page-subtitle">Selamat datang, <?= htmlspecialchars($_SESSION['nama']) ?>!</p>
        </div>
        
        <!-- Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 28px;">
            <div style="background: linear-gradient(135deg, #2563eb, #508ff4); border-radius: 20px; padding: 24px; color: white;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 18px; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 28px;">👥</div>
                </div>
                <div style="font-size: 36px; font-weight: 800;"><?= number_format($total_guru['total']) ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Total Guru Aktif</div>
                <div style="font-size: 12px; opacity: 0.7; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">✓ Terdaftar di sistem</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #059669, #3ae069); border-radius: 20px; padding: 24px; color: white;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 18px; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 28px;">✅</div>
                </div>
                <div style="font-size: 36px; font-weight: 800;"><?= $stats_hari_ini['hadir'] ?? 0 ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Hadir Hari Ini</div>
                <div style="font-size: 12px; opacity: 0.7; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">dari <?= $stats_hari_ini['total_absen'] ?? 0 ?> total</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #ea580c, #ea9370); border-radius: 20px; padding: 24px; color: white;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 18px; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 28px;">⏰</div>
                </div>
                <div style="font-size: 36px; font-weight: 800;"><?= $stats_hari_ini['telat'] ?? 0 ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Terlambat</div>
                <div style="font-size: 12px; opacity: 0.7; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">⚠️ Perlu perhatian</div>
            </div>
            
            <div style="background: linear-gradient(135deg, #dc2626, #e59494); border-radius: 20px; padding: 24px; color: white;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <div style="background: rgba(255,255,255,0.25); border-radius: 18px; width: 56px; height: 56px; display: flex; align-items: center; justify-content: center; font-size: 28px;">📋</div>
                </div>
                <div style="font-size: 36px; font-weight: 800;"><?= $ajuan_pending['pending'] ?></div>
                <div style="font-size: 14px; opacity: 0.9;">Ajuan Pending</div>
                <div style="font-size: 12px; opacity: 0.7; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2);">⏳ Menunggu approval</div>
            </div>
        </div>
        
        <!-- Chart and Jam -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 28px;">
            <div class="card">
                <div class="card-header">📊 Grafik Absensi 7 Hari Terakhir</div>
                <div class="card-body">
                    <div style="display: flex; align-items: flex-end; gap: 12px; min-height: 220px; overflow-x: auto;">
                        <?php 
                        $dates = [];
                        for($i = 6; $i >= 0; $i--) {
                            $date = date('Y-m-d', strtotime("-$i days"));
                            $dates[$date] = 0;
                        }
                        foreach($chart_data as $d) {
                            $dates[$d['tanggal']] = $d['total_absen'];
                        }
                        foreach($dates as $date => $total): 
                            $height = ($total / $max_absen) * 150;
                            $height = max($height, 4);
                        ?>
                        <div style="flex: 1; min-width: 50px; display: flex; flex-direction: column; align-items: center; gap: 8px;">
                            <div style="width: 100%; max-width: 40px; background: #dc2626; border-radius: 8px; height: <?= $height ?>px;"></div>
                            <div style="font-size: 11px; color: #64748b;"><?= date('d/m', strtotime($date)) ?></div>
                            <div style="font-size: 12px; font-weight: 600;"><?= $total ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="card">
                <div class="card-header">⏰ Absensi & Keterlambatan Per Jam (Hari Ini)</div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px;">
                        <?php foreach($absensi_per_jam as $jam): ?>
                        <div style="background: #f8fafc; border-radius: 14px; padding: 14px; text-align: center; border: 1px solid #e2e8f0;">
                            <div style="font-size: 13px; font-weight: 600; color: #dc2626;">Jam ke-<?= $jam['jam_ke'] ?></div>
                            <div style="font-size: 11px; color: #64748b;"><?= substr($jam['jam_mulai'], 0, 5) ?> - <?= substr($jam['jam_selesai'], 0, 5) ?></div>
                            <div style="display: flex; justify-content: space-around; margin-top: 8px;">
                                <div>
                                    <div style="font-size: 20px; font-weight: 700; color: #059669;"><?= $jam['jumlah_absen'] ?></div>
                                    <div style="font-size: 9px; color: #94a3b8;">Hadir</div>
                                </div>
                                <div>
                                    <div style="font-size: 20px; font-weight: 700; color: #ea580c;"><?= $jam['jumlah_telat'] ?></div>
                                    <div style="font-size: 9px; color: #94a3b8;">Terlambat</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .main-content .content-wrapper { padding: 16px; }
    div[style*="grid-template-columns: repeat(4, 1fr)"] { grid-template-columns: repeat(2, 1fr) !important; gap: 16px !important; }
    div[style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; gap: 16px !important; }
}
</style>

<?php require_once '../includes/footer.php'; ?>