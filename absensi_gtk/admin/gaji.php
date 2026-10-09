<?php
$page_title = 'Penggajian - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Filter range tanggal
$filter_tanggal_mulai = isset($_GET['tanggal_mulai']) && !empty($_GET['tanggal_mulai']) ? $_GET['tanggal_mulai'] : '';
$filter_tanggal_selesai = isset($_GET['tanggal_selesai']) && !empty($_GET['tanggal_selesai']) ? $_GET['tanggal_selesai'] : '';

// Ambil data jam kerja untuk batas telat
$stmt_jam = $pdo->query("SELECT * FROM jam_kerja");
$jam_kerja = [];
while ($row = $stmt_jam->fetch()) {
    $jam_kerja[$row['jam_ke']] = [
        'jam_mulai' => $row['jam_mulai'],
        'batas_telat' => date('H:i:s', strtotime($row['jam_mulai'] . ' +5 minutes'))
    ];
}

// Proses update gaji per guru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_gaji_pangkat'])) {
    $id_guru = $_POST['id_guru'];
    $gaji_pangkat_baru = str_replace('.', '', $_POST['gaji_pangkat']);
    $gaji_pangkat_baru = (float) $gaji_pangkat_baru;
    
    $stmt = $pdo->prepare("UPDATE guru SET gaji_pokok = ? WHERE id_guru = ?");
    $stmt->execute([$gaji_pangkat_baru, $id_guru]);
    
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Gaji pangkat berhasil diupdate!'];
    header("Location: gaji.php?" . http_build_query($_GET));
    exit();
}

// Ambil semua guru aktif
$stmt = $pdo->query("SELECT id_guru, nama, nip, jabatan, gaji_perjam, gaji_perhari, gaji_pokok FROM guru WHERE role = 'guru' AND status = 'aktif' ORDER BY nama");
$guru_list = $stmt->fetchAll();

$gaji_data = [];
$total_keseluruhan = 0;

foreach ($guru_list as $guru) {
    // Query ambil semua absensi guru
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
        $jam_mulai = $absen['jam_mulai'];
        $batas_telat = $jam_kerja[$absen['jam_ke']]['batas_telat'];
        
        // Hitung menit keterlambatan jika melebihi batas
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
    
    // Hitung gaji dari absensi
    if ($gaji_perjam > 0) {
        $gaji_dari_absensi = $total_jam * $gaji_perjam;
        $tipe_gaji = 'per jam';
        $jumlah_gaji = $gaji_perjam;
    } else {
        $gaji_dari_absensi = $total_hari * $gaji_perhari;
        $tipe_gaji = 'per hari';
        $jumlah_gaji = $gaji_perhari;
    }
    
    $gaji_lembur = $lembur['total_gaji_lembur'] ?? 0;
    $total_gaji = $gaji_pangkat + $gaji_dari_absensi + $gaji_lembur;
    $total_keseluruhan += $total_gaji;
    
    $gaji_data[] = [
        'id_guru' => $guru['id_guru'],
        'nama' => $guru['nama'],
        'nip' => $guru['nip'],
        'jabatan' => $guru['jabatan'],
        'total_jam' => $total_jam,
        'total_hari' => $total_hari,
        'total_menit_telat' => $total_menit_telat,
        'gaji_pangkat' => $gaji_pangkat,
        'gaji_dari_absensi' => $gaji_dari_absensi,
        'tipe_gaji' => $tipe_gaji,
        'jumlah_gaji' => $jumlah_gaji,
        'gaji_lembur' => $gaji_lembur,
        'total_gaji' => $total_gaji
    ];
}

// ========== EXPORT EXCEL ==========
if (isset($_GET['export_excel'])) {
    // Set header untuk download Excel
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="Laporan_Gaji.xls"');
    header('Cache-Control: max-age=0');
    
    $periode_text = (!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)) ? 
        date('d/m/Y', strtotime($filter_tanggal_mulai)) . ' - ' . date('d/m/Y', strtotime($filter_tanggal_selesai)) : 
        'Semua Data';
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Laporan Gaji Guru</title>';
    echo '</head>';
    echo '<body>';
    echo '<h2 style="text-align: center;">LAPORAN GAJI GURU</h2>';
    echo '<p style="text-align: center;">Periode: ' . $periode_text . '</p>';
    echo '<table border="1" cellpadding="5">';
    echo '<thead>';
    echo '<tr style="background: #c41e3a; color: white;">';
    echo '<th>No</th>';
    echo '<th>Nama Guru</th>';
    echo '<th>NIP</th>';
    echo '<th>Total Jam</th>';
    echo '<th>Total Hari</th>';
    echo '<th>Terlambat (menit)</th>';
    echo '<th>Gaji Pangkat</th>';
    echo '<th>Gaji Absensi</th>';
    echo '<th>Gaji Lembur</th>';
    echo '<th>Total Gaji</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    $no = 1;
    foreach ($gaji_data as $g) {
        echo '<tr>';
        echo '<td>' . $no++ . '</td>';
        echo '<td>' . htmlspecialchars($g['nama']) . '</td>';
        echo '<td>' . $g['nip'] . '</td>';
        echo '<td>' . $g['total_jam'] . ' jam</td>';
        echo '<td>' . $g['total_hari'] . ' hari</td>';
        echo '<td>' . $g['total_menit_telat'] . ' menit</td>';
        echo '<td>Rp ' . number_format($g['gaji_pangkat'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($g['gaji_dari_absensi'], 0, ',', '.') . '</td>';
        echo '<td>Rp ' . number_format($g['gaji_lembur'], 0, ',', '.') . '</td>';
        echo '<td><strong>Rp ' . number_format($g['total_gaji'], 0, ',', '.') . '</strong></td>';
        echo '</tr>';
    }
    
    echo '<tr style="background: #f1f5f9; font-weight: bold;">';
    echo '<td colspan="9">GRAND TOTAL</td>';
    echo '<td>Rp ' . number_format($total_keseluruhan, 0, ',', '.') . '</td>';
    echo '</tr>';
    echo '</tbody>';
    echo '</table>';
    echo '<p style="margin-top: 30px;">Dicetak: ' . date('d/m/Y H:i:s') . '</p>';
    echo '</body>';
    echo '</html>';
    exit();
}

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <div>
                <h1 class="page-title">💰 Penggajian Guru</h1>
                <p class="page-subtitle">Rekap gaji otomatis dari absensi dan lembur (realtime)</p>
            </div>
        </div>
        
        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="filter-info">
                <?php if(!empty($filter_tanggal_mulai) && !empty($filter_tanggal_selesai)): ?>
                    <span class="filter-badge">📅 Periode: <?= date('d/m/Y', strtotime($filter_tanggal_mulai)) ?> - <?= date('d/m/Y', strtotime($filter_tanggal_selesai)) ?></span>
                <?php else: ?>
                    <span class="filter-badge">📅 Semua Data</span>
                <?php endif; ?>
                <span class="filter-badge">👥 Total: <?= count($gaji_data) ?> guru</span>
            </div>
            
            <div class="filter-actions">
                <form method="GET" class="filter-form">
                    <input type="date" name="tanggal_mulai" class="filter-input" value="<?= $filter_tanggal_mulai ?>" placeholder="Dari">
                    <span class="filter-sep">-</span>
                    <input type="date" name="tanggal_selesai" class="filter-input" value="<?= $filter_tanggal_selesai ?>" placeholder="Sampai">
                    <button type="submit" class="filter-btn">Filter</button>
                    <a href="gaji.php" class="filter-reset">Reset</a>
                </form>
                
                <div class="action-buttons">
                    <a href="?export_excel=1&<?= http_build_query($_GET) ?>" class="btn-excel">📊 Export Excel</a>
                    <a href="download_gaji_pdf.php?<?= http_build_query($_GET) ?>" class="btn-pdf" target="_blank">📄 Export PDF</a>
                </div>
            </div>
        </div>
        
        <!-- Tabel Gaji -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Daftar Gaji Guru</div>
            </div>
            <div class="table-responsive">
                <?php if(count($gaji_data) > 0): ?>
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Nama Guru</th>
                            <th>NIP</th>
                            <th>Total Jam</th>
                            <th>Total Hari</th>
                            <th>Terlambat (menit)</th>
                            <th>Gaji Pangkat</th>
                            <th>Gaji Absensi</th>
                            <th>Gaji Lembur</th>
                            <th>Total Gaji</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach($gaji_data as $g): ?>
                        <tr>
                            <td data-label="No" class="text-center"><?= $no++ ?></td>
                            <td data-label="Nama Guru">
                                <strong><?= htmlspecialchars($g['nama']) ?></strong>
                                <div class="jabatan-text"><?= htmlspecialchars($g['jabatan']) ?></div>
                            </td>
                            <td data-label="NIP" class="text-center"><?= $g['nip'] ?></td>
                            <td data-label="Total Jam" class="text-center"><?= $g['total_jam'] ?> jam</td>
                            <td data-label="Total Hari" class="text-center"><?= $g['total_hari'] ?> hari</td>
                            <td data-label="Terlambat" class="text-center">
                                <span class="telat-badge <?= $g['total_menit_telat'] > 0 ? 'has-telat' : '' ?>">
                                    <?= $g['total_menit_telat'] ?> menit
                                </span>
                             </td>
                            <td data-label="Gaji Pangkat" class="text-right gaji-pangkat-cell">
                                <span class="gaji-pangkat-value">Rp <?= number_format($g['gaji_pangkat'], 0, ',', '.') ?></span>
                                <button onclick="openEditModal(<?= $g['id_guru'] ?>, '<?= addslashes($g['nama']) ?>', <?= $g['gaji_pangkat'] ?>)" class="btn-edit-gaji" title="Edit Gaji Pangkat">✏️</button>
                            </td>
                            <td data-label="Gaji Absensi" class="text-right">
                                Rp <?= number_format($g['gaji_dari_absensi'], 0, ',', '.') ?>
                                <div class="gaji-tipe">(<?= $g['tipe_gaji'] ?> <?= number_format($g['jumlah_gaji'], 0, ',', '.') ?>)</div>
                            </td>
                            <td data-label="Gaji Lembur" class="text-right">Rp <?= number_format($g['gaji_lembur'], 0, ',', '.') ?></td>
                            <td data-label="Total Gaji" class="text-right total-gaji">Rp <?= number_format($g['total_gaji'], 0, ',', '.') ?></td>
                            <td data-label="Aksi" class="text-center">
                                <a href="download_slip_pdf.php?id=<?= $g['id_guru'] ?>&<?= http_build_query($_GET) ?>" class="btn-slip" target="_blank">📄 Slip</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="grand-total">
                            <td colspan="9" class="text-right"><strong>GRAND TOTAL</strong></td>
                            <td class="text-right"><strong>Rp <?= number_format($total_keseluruhan, 0, ',', '.') ?></strong></td>
                            <td></td>
                        </tr>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-icon">📭</div>
                    <p>Belum ada data gaji</p>
                    <span class="empty-hint">Belum ada absensi untuk periode ini</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Ringkasan -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-icon">💰</div>
                <div class="summary-info">
                    <div class="summary-label">Total Gaji</div>
                    <div class="summary-value">Rp <?= number_format($total_keseluruhan, 0, ',', '.') ?></div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">👥</div>
                <div class="summary-info">
                    <div class="summary-label">Total Guru</div>
                    <div class="summary-value"><?= count($gaji_data) ?> orang</div>
                </div>
            </div>
            <div class="summary-card">
                <div class="summary-icon">⏰</div>
                <div class="summary-info">
                    <div class="summary-label">Total Terlambat</div>
                    <div class="summary-value">
                        <?php 
                        $total_menit_telat_all = 0;
                        foreach($gaji_data as $g) { $total_menit_telat_all += $g['total_menit_telat']; }
                        echo $total_menit_telat_all . ' menit';
                        ?>
                    </div>
                </div>
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

<!-- Modal Edit Gaji Pangkat -->
<div id="editGajiModal" class="modal-edit">
    <div class="modal-edit-content">
        <div class="modal-edit-header">
            <h3>✏️ Edit Gaji Pangkat</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <div class="modal-edit-body">
            <form method="POST" id="editGajiForm">
                <input type="hidden" name="id_guru" id="edit_id_guru">
                <div class="form-group">
                    <label class="form-label">Nama Guru</label>
                    <input type="text" id="edit_nama_guru" class="form-control" disabled style="background:#f1f5f9;">
                </div>
                <div class="form-group">
                    <label class="form-label">Gaji Pangkat (Rp)</label>
                    <input type="text" name="gaji_pangkat" id="edit_gaji_pangkat" class="form-control" required placeholder="Contoh: 2000000">
                </div>
                <div class="modal-edit-footer">
                    <button type="button" onclick="closeEditModal()" class="btn-cancel">Batal</button>
                    <button type="submit" name="update_gaji_pangkat" class="btn-save">Simpan</button>
                </div>
            </form>
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
        
        setTimeout(() => { toast.classList.remove('show'); }, 4000);
    }
    
    function closeToast() {
        document.getElementById('toastNotification').classList.remove('show');
    }
    
    function openEditModal(id, nama, gajiPangkat) {
        document.getElementById('edit_id_guru').value = id;
        document.getElementById('edit_nama_guru').value = nama;
        document.getElementById('edit_gaji_pangkat').value = new Intl.NumberFormat('id-ID').format(gajiPangkat);
        document.getElementById('editGajiModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editGajiModal').style.display = 'none';
    }
    
    document.getElementById('edit_gaji_pangkat').addEventListener('input', function(e) {
        let value = this.value.replace(/\./g, '');
        if (!isNaN(value) && value !== '') {
            this.value = new Intl.NumberFormat('id-ID').format(value);
        }
    });
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    document.getElementById('editGajiModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
</script>

<style>
    .filter-bar {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
        margin-bottom: 24px;
        padding-bottom: 16px;
        border-bottom: 1px solid #e2e8f0;
    }
    .filter-info { display: flex; gap: 12px; flex-wrap: wrap; }
    .filter-badge { background: #f1f5f9; padding: 6px 12px; border-radius: 20px; font-size: 12px; color: #475569; }
    .filter-actions { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
    .filter-form { display: flex; align-items: center; gap: 8px; background: white; padding: 4px 12px; border-radius: 10px; border: 1px solid #e2e8f0; }
    .filter-input { padding: 6px 10px; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 12px; width: 120px; }
    .filter-sep { color: #94a3b8; font-size: 12px; }
    .filter-btn { background: #c41e3a; color: white; border: none; padding: 6px 14px; border-radius: 6px; cursor: pointer; font-size: 12px; font-weight: 500; }
    .filter-reset { background: #e2e8f0; color: #1e293b; padding: 6px 14px; border-radius: 6px; text-decoration: none; }
    .action-buttons { display: flex; gap: 8px; }
    .btn-excel, .btn-pdf { padding: 6px 14px; border-radius: 6px; text-decoration: none; font-size: 12px; font-weight: 500; }
    .btn-excel { background: #10b981; color: white; }
    .btn-pdf { background: #dc2626; color: white; }
    
    .table-container { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; }
    .table-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: white; }
    .table-title { font-weight: 600; font-size: 16px; color: #1e293b; }
    .table-responsive { overflow-x: auto; }
    
    .modern-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modern-table th, .modern-table td { padding: 12px 16px; border: 1px solid #e2e8f0; text-align: left; vertical-align: middle; }
    .modern-table th { background: #f8fafc; font-weight: 600; font-size: 12px; white-space: nowrap; }
    .modern-table td.total-gaji { font-weight: 700; color: #1e293b; }
    .grand-total { background: #f1f5f9; font-weight: 600; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    
    .jabatan-text { font-size: 11px; color: #64748b; margin-top: 2px; }
    .gaji-tipe { font-size: 10px; color: #94a3b8; margin-top: 2px; }
    
    .telat-badge { display: inline-block; padding: 2px 8px; background: #e2e8f0; color: #64748b; border-radius: 12px; font-size: 11px; }
    .telat-badge.has-telat { background: #fed7aa; color: #9a3412; }
    
    .gaji-pangkat-cell { display: flex; align-items: center; justify-content: flex-end; gap: 8px; }
    .btn-edit-gaji { background: #f59e0b; color: white; border: none; width: 28px; height: 28px; border-radius: 6px; cursor: pointer; font-size: 12px; display: inline-flex; align-items: center; justify-content: center; }
    .btn-edit-gaji:hover { background: #d97706; }
    .btn-slip { background: #3b82f6; color: white; padding: 5px 12px; border-radius: 6px; text-decoration: none; font-size: 11px; display: inline-block; }
    
    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-icon { font-size: 48px; margin-bottom: 12px; }
    
    .summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px; }
    .summary-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; padding: 20px; display: flex; align-items: center; gap: 16px; }
    .summary-icon { font-size: 40px; }
    .summary-info { flex: 1; }
    .summary-label { font-size: 12px; color: #64748b; margin-bottom: 4px; }
    .summary-value { font-size: 20px; font-weight: 700; color: #1e293b; }
    
    .info-card { background: white; border-radius: 20px; border: 1px solid #e2e8f0; overflow: hidden; margin-top: 24px; }
    .info-header { padding: 14px 20px; background: #f8fafc; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
    .info-body { padding: 20px; }
    .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; }
    .info-item { text-align: center; padding: 12px; background: #f8fafc; border-radius: 12px; }
    .info-label { font-size: 11px; color: #64748b; margin-bottom: 4px; }
    .info-value { font-size: 16px; font-weight: 600; color: #1e293b; }
    .info-batas { font-size: 11px; color: #c41e3a; margin-top: 4px; }
    .info-note { margin-top: 16px; padding: 12px; background: #fef3c7; border-radius: 12px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 8px; }
    
    .modal-edit { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; }
    .modal-edit-content { background: white; border-radius: 20px; max-width: 450px; width: 90%; overflow: hidden; }
    .modal-edit-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; background: #fafcff; }
    .modal-edit-header h3 { margin: 0; font-size: 16px; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
    .modal-edit-body { padding: 20px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 13px; font-weight: 500; color: #374151; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 8px; font-size: 14px; }
    .modal-edit-footer { display: flex; justify-content: flex-end; gap: 12px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
    .btn-cancel, .btn-save { padding: 8px 20px; border-radius: 8px; cursor: pointer; font-size: 13px; font-weight: 500; border: none; }
    .btn-cancel { background: #e2e8f0; color: #1e293b; }
    .btn-save { background: #c41e3a; color: white; }
    
    .toast-notification { position: fixed; top: 20px; right: 20px; z-index: 9999; background: white; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px; border-left: 4px solid; display: none; }
    .toast-notification.show { display: block; }
    .toast-content { display: flex; align-items: center; gap: 12px; }
    .toast-close { background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8; margin-left: auto; }
    
    @media (max-width: 768px) {
        .filter-bar { flex-direction: column; align-items: flex-start; }
        .filter-actions { width: 100%; flex-direction: column; align-items: stretch; }
        .filter-form { flex-wrap: wrap; justify-content: center; }
        .filter-input { width: 100%; }
        .action-buttons { justify-content: center; }
        .action-buttons a { flex: 1; text-align: center; }
        .modern-table thead { display: none; }
        .modern-table tr { display: block; margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .modern-table td { display: flex; justify-content: space-between; align-items: center; padding: 10px 15px; border: none; border-bottom: 1px solid #e8ecf2; text-align: right; }
        .modern-table td:before { content: attr(data-label); font-weight: 600; color: #475569; text-align: left; width: 45%; }
        .summary-cards { grid-template-columns: 1fr; gap: 12px; }
        .toast-notification { top: 10px; right: 10px; left: 10px; min-width: auto; }
        .gaji-pangkat-cell { justify-content: space-between; }
        .info-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>

<?php require_once '../includes/footer.php'; ?>