<?php
$page_title = 'Riwayat Absensi - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Filter dengan range tanggal (default: 30 hari terakhir)
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : date('Y-m-d', strtotime('-30 days'));
$filter_tanggal_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : date('Y-m-d');
$filter_guru = isset($_GET['guru']) ? $_GET['guru'] : '';
$filter_jam = isset($_GET['jam']) ? $_GET['jam'] : '';

// Ambil data guru untuk filter
$stmt_guru = $pdo->query("SELECT id_guru, nama FROM guru WHERE role = 'guru' AND status = 'aktif' ORDER BY nama");
$guru_list = $stmt_guru->fetchAll();

// Ambil data jam kerja untuk filter dan deteksi telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja ORDER BY jam_ke");
$jam_list = $stmt_jam->fetchAll();

// Buat array batas waktu telat (contoh: telat jika melebihi 5 menit dari jam mulai)
$batas_telat = [];
foreach ($jam_list as $jam) {
    $jam_mulai = $jam['jam_mulai'];
    $batas = date('H:i:s', strtotime($jam_mulai . ' +5 minutes'));
    $batas_telat[$jam['jam_ke']] = $batas;
}

// Query absensi
$sql = "SELECT a.*, g.nama, g.nip, g.foto, j.jam_mulai, j.jam_selesai 
        FROM absensi a
        JOIN guru g ON a.id_guru = g.id_guru
        JOIN jam_kerja j ON a.jam_ke = j.jam_ke
        WHERE 1=1";
$params = [];

if ($filter_tanggal_mulai && $filter_tanggal_selesai) {
    $sql .= " AND a.tanggal BETWEEN ? AND ?";
    $params[] = $filter_tanggal_mulai;
    $params[] = $filter_tanggal_selesai;
}

if (!empty($filter_guru)) {
    $sql .= " AND a.id_guru = ?";
    $params[] = $filter_guru;
}

if (!empty($filter_jam)) {
    $sql .= " AND a.jam_ke = ?";
    $params[] = $filter_jam;
}

$sql .= " ORDER BY a.tanggal DESC, a.jam_ke ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$absensi_list = $stmt->fetchAll();

// Proses ulang data untuk menentukan telat berdasarkan jam mulai
$processed_absensi = [];
$total_hadir = 0;
$total_telat = 0;
$total_tepat = 0;
$total_guru_absen = [];

foreach ($absensi_list as $absen) {
    $jam_ke = $absen['jam_ke'];
    $jam_mulai = $absen['jam_mulai'];
    $waktu_absen = $absen['waktu_absen'];
    $waktu_absen_time = date('H:i:s', strtotime($waktu_absen));
    
    $batas = $batas_telat[$jam_ke] ?? date('H:i:s', strtotime($jam_mulai . ' +5 minutes'));
    $is_late = ($waktu_absen_time > $batas);
    
    if ($is_late && $absen['status'] == 'hadir') {
        $status_display = 'Terlambat';
        $status_class = 'telat';
        $total_telat++;
    } else if ($absen['status'] == 'hadir') {
        $status_display = 'Tepat Waktu';
        $status_class = 'tepat';
        $total_tepat++;
        $total_hadir++;
    } else if ($absen['status'] == 'telat') {
        $status_display = 'Terlambat';
        $status_class = 'telat';
        $total_telat++;
    } else {
        $status_display = 'Tidak Hadir';
        $status_class = 'alfa';
    }
    
    $processed_absensi[] = [
        'id_absensi' => $absen['id_absensi'],
        'id_guru' => $absen['id_guru'],
        'nama' => $absen['nama'],
        'nip' => $absen['nip'],
        'foto' => $absen['foto'],
        'tanggal' => $absen['tanggal'],
        'jam_ke' => $absen['jam_ke'],
        'jam_mulai' => $absen['jam_mulai'],
        'jam_selesai' => $absen['jam_selesai'],
        'waktu_absen' => $absen['waktu_absen'],
        'lokasi_gps' => $absen['lokasi_gps'],
        'foto_selfie' => $absen['foto_selfie'],
        'status' => $absen['status'],
        'status_display' => $status_display,
        'status_class' => $status_class,
        'is_late' => $is_late,
        'batas_waktu' => $batas
    ];
    
    if (!in_array($absen['id_guru'], $total_guru_absen)) {
        $total_guru_absen[] = $absen['id_guru'];
    }
}

// Hitung total absensi keseluruhan
$stmt_total = $pdo->query("SELECT COUNT(*) as total FROM absensi");
$total_semua = $stmt_total->fetch();

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">📝 Riwayat Absensi</h1>
            <p class="page-subtitle">Lihat dan filter riwayat absensi semua guru</p>
        </div>

        <!-- Filter Form -->
        <div class="card">
            <div class="card-header"><span>🔍 Filter Data</span></div>
            <div class="card-body">
                <form method="GET" action="">
                    <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                        <div style="flex: 1; min-width: 150px;">
                            <label class="form-label">Dari Tanggal</label>
                            <input type="date" name="tanggal_mulai" class="form-control" value="<?= $filter_tanggal_mulai ?>">
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label class="form-label">Sampai Tanggal</label>
                            <input type="date" name="tanggal_selesai" class="form-control" value="<?= $filter_tanggal_selesai ?>">
                        </div>
                        <div style="flex: 1; min-width: 150px;">
                            <label class="form-label">Guru</label>
                            <select name="guru" class="form-control">
                                <option value="">Semua Guru</option>
                                <?php foreach($guru_list as $guru): ?>
                                <option value="<?= $guru['id_guru'] ?>" <?= $filter_guru == $guru['id_guru'] ? 'selected' : '' ?>><?= htmlspecialchars($guru['nama']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="flex: 1; min-width: 120px;">
                            <label class="form-label">Jam ke-</label>
                            <select name="jam" class="form-control">
                                <option value="">Semua Jam</option>
                                <?php foreach($jam_list as $jam): ?>
                                <option value="<?= $jam['jam_ke'] ?>" <?= $filter_jam == $jam['jam_ke'] ? 'selected' : '' ?>>
                                    Jam ke-<?= $jam['jam_ke'] ?> (<?= substr($jam['jam_mulai'], 0, 5) ?> - <?= substr($jam['jam_selesai'], 0, 5) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="display: flex; gap: 10px;">
                            <button type="submit" class="btn btn-primary">Filter</button>
                            <a href="riwayat_absensi.php" class="btn btn-secondary">Reset</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Statistik Ringkasan -->
        <div class="stats-grid">
            <div class="stat-card stat-total"><div class="stat-icon">📊</div><div class="stat-value"><?= count($processed_absensi) ?></div><div class="stat-label">Total Absensi</div><div class="stat-footer">dari <?= $total_semua['total'] ?> keseluruhan</div></div>
            <div class="stat-card stat-guru"><div class="stat-icon">👥</div><div class="stat-value"><?= count($total_guru_absen) ?></div><div class="stat-label">Guru yang Absen</div></div>
            <div class="stat-card stat-tepat"><div class="stat-icon">✅</div><div class="stat-value"><?= $total_tepat ?></div><div class="stat-label">Tepat Waktu</div></div>
            <div class="stat-card stat-telat"><div class="stat-icon">⏰</div><div class="stat-value"><?= $total_telat ?></div><div class="stat-label">Terlambat</div></div>
            <div class="stat-card stat-alfa"><div class="stat-icon">❌</div><div class="stat-value"><?= count($processed_absensi) - $total_tepat - $total_telat ?></div><div class="stat-label">Tidak Hadir</div></div>
        </div>

        <!-- Tabel Riwayat Absensi dengan GPS -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Data Absensi</div>
                <div class="table-info">Periode: <?= date('d/m/Y', strtotime($filter_tanggal_mulai)) ?> - <?= date('d/m/Y', strtotime($filter_tanggal_selesai)) ?> | Total: <?= count($processed_absensi) ?> record</div>
            </div>
            <div class="table-responsive">
                <?php if(count($processed_absensi) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Guru</th>
                            <th>Tanggal</th>
                            <th>Jam</th>
                            <th>Batas Waktu</th>
                            <th>Waktu Absen</th>
                            <th>Lokasi GPS</th>
                            <th>Status</th>
                            <th>Keterlambatan</th>
                            <th>Foto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($processed_absensi as $absen): 
                            $menit_telat = 0;
                            if ($absen['is_late']) {
                                $waktu_absen_ts = strtotime($absen['waktu_absen']);
                                $batas_ts = strtotime($absen['jam_mulai'] . ' +5 minutes');
                                $menit_telat = ceil(($waktu_absen_ts - $batas_ts) / 60);
                                $menit_telat = max($menit_telat, 0);
                            }
                        ?>
                        <tr>
                            <td data-label="No" class="text-center"><?= $no++ ?></td>
                            <td data-label="Guru">
                                <div class="teacher-info">
                                    <?php if($absen['foto'] && file_exists('../assets/uploads/foto_guru/' . $absen['foto'])): ?>
                                        <img src="../assets/uploads/foto_guru/<?= $absen['foto'] ?>" width="32" height="32" class="teacher-avatar">
                                    <?php else: ?>
                                        <div class="teacher-avatar-placeholder"><?= strtoupper(substr($absen['nama'], 0, 1)) ?></div>
                                    <?php endif; ?>
                                    <div><div class="teacher-name"><?= htmlspecialchars($absen['nama']) ?></div><div class="teacher-nip"><?= $absen['nip'] ?></div></div>
                                </div>
                            </td>
                            <td data-label="Tanggal" class="text-center"><?= date('d/m/Y', strtotime($absen['tanggal'])) ?></td>
                            <td data-label="Jam"><strong>Jam ke-<?= $absen['jam_ke'] ?></strong><br><span class="jam-time"><?= substr($absen['jam_mulai'], 0, 5) ?> - <?= substr($absen['jam_selesai'], 0, 5) ?></span></td>
                            <td data-label="Batas Waktu" class="text-center"><?= substr($absen['batas_waktu'], 0, 5) ?> WIB</td>
                            <td data-label="Waktu Absen" class="text-center"><?= date('H:i:s', strtotime($absen['waktu_absen'])) ?> WIB</td>
                            <td data-label="Lokasi GPS" class="lokasi-text"><?= $absen['lokasi_gps'] ?: '-' ?></td>
                            <td data-label="Status" class="text-center"><span class="status-badge status-<?= $absen['status_class'] ?>"><?= $absen['status_display'] ?></span></td>
                            <td data-label="Keterlambatan" class="text-center"><?php if($absen['is_late']): ?><span class="late-badge"><?= $menit_telat ?> menit</span><?php else: ?><span class="ontime-badge">-</span><?php endif; ?></td>
                            <td data-label="Foto" class="text-center"><?php if($absen['foto_selfie'] && file_exists('../assets/uploads/selfie_absensi/' . $absen['foto_selfie'])): ?><button onclick="lihatFoto('<?= $absen['foto_selfie'] ?>')" class="btn-foto">📷 Lihat</button><?php else: ?><span class="no-photo">-</span><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state"><div class="empty-icon">📭</div><p>Tidak ada data absensi</p></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Informasi Batas Waktu -->
        <div class="info-card">
            <div class="info-header">📋 Informasi Batas Waktu Terlambat</div>
            <div class="info-body">
                <div class="info-grid">
                    <?php foreach($jam_list as $jam): $batas = date('H:i', strtotime($jam['jam_mulai'] . ' +5 minutes')); ?>
                    <div class="info-item"><div class="info-label">Jam ke-<?= $jam['jam_ke'] ?></div><div class="info-value"><?= substr($jam['jam_mulai'], 0, 5) ?> WIB</div><div class="info-batas">Batas telat: <strong><?= $batas ?> WIB</strong></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="info-note"><span>ℹ️</span> Keterlambatan dihitung jika absen melebihi <strong>5 menit</strong> dari jam mulai</div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div id="fotoModal" class="modal-foto"><div class="modal-foto-content"><h3>Foto Selfie Absensi</h3><img id="fotoSelfie" src=""><button onclick="closeFotoModal()" class="btn-modal-close">Tutup</button></div></div>

<script>
function lihatFoto(filename){document.getElementById('fotoSelfie').src='../assets/uploads/selfie_absensi/'+filename;document.getElementById('fotoModal').style.display='flex';}
function closeFotoModal(){document.getElementById('fotoModal').style.display='none';}
document.getElementById('fotoModal').addEventListener('click',function(e){if(e.target===this)closeFotoModal();});
</script>

<style>
.stats-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:16px;margin-bottom:24px;}
.stat-card{border-radius:16px;padding:16px;color:white;}
.stat-total{background:linear-gradient(135deg,#6366f1,#4f46e5);}
.stat-guru{background:linear-gradient(135deg,#06b6d4,#0891b2);}
.stat-tepat{background:linear-gradient(135deg,#10b981,#059669);}
.stat-telat{background:linear-gradient(135deg,#f59e0b,#d97706);}
.stat-alfa{background:linear-gradient(135deg,#ef4444,#dc2626);}
.stat-icon{font-size:28px;margin-bottom:8px;opacity:0.9;}
.stat-value{font-size:28px;font-weight:700;margin-bottom:4px;}
.stat-label{font-size:12px;opacity:0.9;}
.stat-footer{font-size:10px;opacity:0.7;margin-top:8px;padding-top:6px;border-top:1px solid rgba(255,255,255,0.2);}
.table-container{background:white;border-radius:20px;border:1px solid #e2e8f0;overflow:hidden;margin-bottom:24px;}
.table-header{padding:16px 20px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;background:white;}
.table-title{font-weight:600;font-size:16px;color:#1e293b;}
.table-info{font-size:12px;color:#64748b;background:#f1f5f9;padding:4px 12px;border-radius:20px;}
.table-responsive{overflow-x:auto;}
.modern-table{width:100%;border-collapse:collapse;font-size:13px;}
.modern-table th,.modern-table td{padding:12px 16px;border:1px solid #e2e8f0;text-align:left;vertical-align:middle;}
.modern-table th{background:#f8fafc;font-weight:600;font-size:12px;white-space:nowrap;}
.text-center{text-align:center;}
.teacher-info{display:flex;align-items:center;gap:10px;}
.teacher-avatar{width:32px;height:32px;border-radius:50%;object-fit:cover;}
.teacher-avatar-placeholder{width:32px;height:32px;background:#e2e8f0;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;color:#64748b;}
.teacher-name{font-weight:500;}
.teacher-nip{font-size:10px;color:#94a3b8;}
.jam-time{font-size:10px;color:#94a3b8;}
.lokasi-text{font-size:11px;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.status-badge{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:500;}
.status-tepat{background:#d1fae5;color:#065f46;}
.status-telat{background:#fed7aa;color:#9a3412;}
.status-alfa{background:#fee2e2;color:#991b1b;}
.late-badge{background:#fed7aa;color:#9a3412;padding:2px 8px;border-radius:12px;font-size:11px;}
.ontime-badge{color:#94a3b8;font-size:11px;}
.btn-foto{background:#8b5cf6;color:white;border:none;padding:5px 12px;border-radius:6px;cursor:pointer;font-size:11px;}
.no-photo{color:#94a3b8;font-size:12px;}
.empty-state{text-align:center;padding:60px 20px;color:#94a3b8;}
.empty-icon{font-size:48px;margin-bottom:12px;}
.info-card{background:white;border-radius:20px;border:1px solid #e2e8f0;overflow:hidden;}
.info-header{padding:14px 20px;background:#f8fafc;font-weight:600;border-bottom:1px solid #e2e8f0;}
.info-body{padding:20px;}
.info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:16px;}
.info-item{text-align:center;padding:12px;background:#f8fafc;border-radius:12px;}
.info-label{font-size:11px;color:#64748b;margin-bottom:4px;}
.info-value{font-size:16px;font-weight:600;color:#1e293b;}
.info-batas{font-size:11px;color:#c41e3a;margin-top:4px;}
.info-note{margin-top:16px;padding:12px;background:#fef3c7;border-radius:12px;font-size:12px;color:#92400e;display:flex;align-items:center;gap:8px;}
.modal-foto{display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.8);z-index:1100;align-items:center;justify-content:center;}
.modal-foto-content{background:white;border-radius:16px;max-width:400px;width:90%;padding:20px;text-align:center;}
.modal-foto-content img{width:100%;border-radius:12px;margin-bottom:16px;}
.btn-modal-close{background:#64748b;color:white;border:none;padding:10px;border-radius:8px;cursor:pointer;width:100%;}
@media (max-width:768px){
.stats-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
.modern-table th{display:none;}
.modern-table tr{display:block;margin-bottom:15px;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;}
.modern-table td{display:flex;justify-content:space-between;align-items:center;padding:10px 15px;border:none;border-bottom:1px solid #e8ecf2;text-align:right;}
.modern-table td:before{content:attr(data-label);font-weight:600;color:#475569;text-align:left;width:40%;font-size:11px;}
.lokasi-text{white-space:normal;word-break:break-all;}
.info-grid{grid-template-columns:repeat(2,1fr);}
}
@media (max-width:480px){.stats-grid{grid-template-columns:1fr;}.info-grid{grid-template-columns:1fr;}}
</style>

<?php require_once '../includes/footer.php'; ?>