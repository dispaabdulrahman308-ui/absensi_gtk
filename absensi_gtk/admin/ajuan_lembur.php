<?php
$page_title = 'Ajuan Lembur - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Proses update status ajuan dengan gaji lembur
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id_ajuan = $_POST['id_ajuan'];
    $status = $_POST['status'];
    $catatan = $_POST['catatan'] ?? '';
    $gaji_lembur = !empty($_POST['gaji_lembur']) ? str_replace('.', '', $_POST['gaji_lembur']) : 0;
    
    $stmt = $pdo->prepare("UPDATE ajuan SET status = ?, catatan_admin = ?, gaji_lembur = ? WHERE id_ajuan = ?");
    if ($stmt->execute([$status, $catatan, $gaji_lembur, $id_ajuan])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Status ajuan lembur berhasil diupdate!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal mengupdate status ajuan!'];
    }
    header("Location: ajuan_lembur.php");
    exit();
}

// Filter
$filter_status = $_GET['status'] ?? '';
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? '';

// Query ajuan lembur
$sql = "
    SELECT a.*, g.nama, g.nip, g.foto, g.no_hp, g.gaji_perjam
    FROM ajuan a
    JOIN guru g ON a.id_guru = g.id_guru
    WHERE a.jenis = 'lembur'
";
$params = [];

if ($filter_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}
if ($filter_tanggal_mulai) {
    $sql .= " AND a.tanggal_mulai >= ?";
    $params[] = $filter_tanggal_mulai;
}
if ($filter_tanggal_selesai) {
    $sql .= " AND a.tanggal_selesai <= ?";
    $params[] = $filter_tanggal_selesai;
}

$sql .= " ORDER BY a.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$ajuan_list = $stmt->fetchAll();

// Statistik
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis = 'lembur' AND status = 'pending'");
$pending_count = $stmt->fetch();
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis = 'lembur' AND status = 'disetujui'");
$approved_count = $stmt->fetch();
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis = 'lembur' AND status = 'ditolak'");
$rejected_count = $stmt->fetch();

// Total gaji lembur yang sudah disetujui
$stmt = $pdo->query("SELECT SUM(gaji_lembur) as total FROM ajuan WHERE jenis = 'lembur' AND status = 'disetujui'");
$total_gaji = $stmt->fetch();

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">💪 Ajuan Lembur</h1>
            <p class="page-subtitle">Kelola ajuan lembur dari guru dan tentukan gaji lembur</p>
        </div>
        
        <!-- Statistik - Mobile Friendly Grid -->
        <div class="stats-grid">
            <div class="stat-card stat-pending">
                <div class="stat-icon">⏳</div>
                <div class="stat-value"><?= $pending_count['total'] ?></div>
                <div class="stat-label">Menunggu</div>
            </div>
            <div class="stat-card stat-approved">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $approved_count['total'] ?></div>
                <div class="stat-label">Disetujui</div>
            </div>
            <div class="stat-card stat-rejected">
                <div class="stat-icon">❌</div>
                <div class="stat-value"><?= $rejected_count['total'] ?></div>
                <div class="stat-label">Ditolak</div>
            </div>
            <div class="stat-card stat-total">
                <div class="stat-icon">💰</div>
                <div class="stat-value">Rp <?= number_format($total_gaji['total'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Total Gaji</div>
            </div>
        </div>
        
        <!-- Filter - Mobile Friendly -->
        <div class="filter-card">
            <div class="filter-header">🔍 Filter Ajuan</div>
            <div class="filter-body">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-row">
                        <select name="status" class="filter-select">
                            <option value="">Semua Status</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="disetujui" <?= $filter_status == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                            <option value="ditolak" <?= $filter_status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                    <div class="filter-row">
                        <input type="date" name="tanggal_mulai" class="filter-input" value="<?= $filter_tanggal_mulai ?>" placeholder="Dari">
                    </div>
                    <div class="filter-row">
                        <input type="date" name="tanggal_selesai" class="filter-input" value="<?= $filter_tanggal_selesai ?>" placeholder="Sampai">
                    </div>
                    <div class="filter-actions">
                        <button type="submit" class="filter-btn">Filter</button>
                        <a href="ajuan_lembur.php" class="filter-reset">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabel Ajuan Lembur - Mobile Friendly (Card View di HP) -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Daftar Ajuan Lembur</div>
                <div class="table-info">Total: <?= count($ajuan_list) ?> ajuan</div>
            </div>
            <div class="table-responsive">
                <?php if(count($ajuan_list) > 0): ?>
                    <table class="modern-table">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Alasan</th>
                                <th>Tanggal</th>
                                <th>Durasi</th>
                                <th>Gaji</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($ajuan_list as $ajuan): 
                                $tgl_mulai = new DateTime($ajuan['tanggal_mulai']);
                                $tgl_selesai = new DateTime($ajuan['tanggal_selesai']);
                                $durasi = $tgl_mulai->diff($tgl_selesai)->days + 1;
                            ?>
                            <tr>
                                <td data-label="Guru">
                                    <div class="teacher-info">
                                        <?php if($ajuan['foto'] && file_exists('../assets/uploads/foto_guru/' . $ajuan['foto'])): ?>
                                            <img src="../assets/uploads/foto_guru/<?= $ajuan['foto'] ?>" class="teacher-avatar">
                                        <?php else: ?>
                                            <div class="teacher-avatar-placeholder">
                                                <?= strtoupper(substr($ajuan['nama'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="teacher-name"><?= htmlspecialchars($ajuan['nama']) ?></div>
                                            <div class="teacher-nip"><?= $ajuan['nip'] ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="Alasan" class="alasan-text"><?= htmlspecialchars(substr($ajuan['alasan'], 0, 50)) ?><?= strlen($ajuan['alasan']) > 50 ? '...' : '' ?></td>
                                <td data-label="Tanggal"><?= date('d/m/Y', strtotime($ajuan['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($ajuan['tanggal_selesai'])) ?></td>
                                <td data-label="Durasi" class="text-center"><?= $durasi ?> hari</td>
                                <td data-label="Gaji">
                                    <?php if($ajuan['gaji_lembur']): ?>
                                        <span class="gaji-amount">Rp <?= number_format($ajuan['gaji_lembur'], 0, ',', '.') ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?= $ajuan['status'] ?>">
                                        <?= $ajuan['status'] == 'pending' ? 'Menunggu' : ($ajuan['status'] == 'disetujui' ? 'Disetujui' : 'Ditolak') ?>
                                    </span>
                                </td>
                                <td data-label="Aksi" class="action-cell">
                                    <?php if($ajuan['status'] == 'pending'): ?>
                                        <button onclick="openModal(<?= $ajuan['id_ajuan'] ?>, '<?= addslashes($ajuan['nama']) ?>', '<?= addslashes($ajuan['alasan']) ?>', '<?= $ajuan['tanggal_mulai'] ?>', '<?= $ajuan['tanggal_selesai'] ?>', <?= $ajuan['gaji_perjam'] ?? 0 ?>)" class="btn-action">Proses</button>
                                    <?php else: ?>
                                        <button onclick="lihatDetail(<?= $ajuan['id_ajuan'] ?>)" class="btn-detail">Detail</button>
                                    <?php endif; ?>
                                    <?php if($ajuan['foto_bukti']): ?>
                                        <button onclick="lihatFoto('<?= $ajuan['foto_bukti'] ?>')" class="btn-foto">📷</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">📭</div>
                        <p>Belum ada ajuan lembur</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Proses Ajuan -->
<div id="prosesModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Proses Ajuan Lembur</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form method="POST">
            <input type="hidden" name="id_ajuan" id="proses_id">
            <div class="modal-body">
                <div class="info-box">
                    <div><strong>Guru:</strong> <span id="proses_nama"></span></div>
                    <div><strong>Alasan:</strong> <span id="proses_alasan"></span></div>
                    <div><strong>Tanggal:</strong> <span id="proses_tanggal"></span></div>
                    <div><strong>Durasi:</strong> <span id="proses_durasi"></span> hari</div>
                    <div class="gaji-info"><strong>💰 Gaji Per Jam:</strong> <span id="proses_gaji_perjam"></span></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" id="proses_status" class="form-control" onchange="toggleGajiInput()">
                        <option value="disetujui">✓ Disetujui</option>
                        <option value="ditolak">✗ Ditolak</option>
                    </select>
                </div>
                
                <div class="form-group" id="gajiGroup">
                    <label class="form-label">Gaji Lembur (Rp)</label>
                    <input type="text" name="gaji_lembur" id="gaji_lembur" class="form-control" placeholder="Contoh: 100000" oninput="formatRupiah(this)">
                    <small>Kosongkan jika tidak ada gaji lembur</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Catatan (opsional)</label>
                    <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan untuk guru..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" onclick="closeModal()" class="btn-cancel">Batal</button>
                <button type="submit" name="update_status" class="btn-save">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail -->
<div id="detailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Detail Ajuan Lembur</h3>
            <button class="modal-close" onclick="closeDetailModal()">&times;</button>
        </div>
        <div class="modal-body">
            <div id="detail_content"></div>
        </div>
        <div class="modal-footer">
            <button onclick="closeDetailModal()" class="btn-cancel">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Foto -->
<div id="fotoModal" class="modal-foto">
    <div class="modal-foto-content">
        <h3>Foto Bukti</h3>
        <img id="fotoBukti" src="">
        <button onclick="closeFotoModal()" class="btn-modal-close">Tutup</button>
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
    let currentGajiPerjam = 0;
    
    function formatRupiah(input) {
        let value = input.value.replace(/\./g, '');
        if (!isNaN(value) && value !== '') {
            input.value = new Intl.NumberFormat('id-ID').format(value);
        }
    }
    
    function toggleGajiInput() {
        const status = document.getElementById('proses_status').value;
        const gajiGroup = document.getElementById('gajiGroup');
        gajiGroup.style.display = status === 'ditolak' ? 'none' : 'block';
    }
    
    function openModal(id, nama, alasan, tgl_mulai, tgl_selesai, gaji_perjam) {
        currentGajiPerjam = gaji_perjam;
        
        document.getElementById('proses_id').value = id;
        document.getElementById('proses_nama').innerHTML = nama;
        document.getElementById('proses_alasan').innerHTML = alasan;
        document.getElementById('proses_tanggal').innerHTML = tgl_mulai + ' s/d ' + tgl_selesai;
        
        const mulai = new Date(tgl_mulai);
        const selesai = new Date(tgl_selesai);
        const durasi = Math.ceil((selesai - mulai) / (1000 * 60 * 60 * 24)) + 1;
        document.getElementById('proses_durasi').innerHTML = durasi;
        document.getElementById('proses_gaji_perjam').innerHTML = 'Rp ' + new Intl.NumberFormat('id-ID').format(gaji_perjam);
        
        if (gaji_perjam > 0) {
            const saranGaji = gaji_perjam * 8 * durasi;
            document.getElementById('gaji_lembur').value = new Intl.NumberFormat('id-ID').format(saranGaji);
        }
        
        document.getElementById('proses_status').value = 'disetujui';
        toggleGajiInput();
        document.getElementById('prosesModal').style.display = 'flex';
    }
    
    function closeModal() { document.getElementById('prosesModal').style.display = 'none'; }
    function closeDetailModal() { document.getElementById('detailModal').style.display = 'none'; }
    function closeFotoModal() { document.getElementById('fotoModal').style.display = 'none'; }
    
    function lihatDetail(id) {
        fetch('get_ajuan.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                let statusLabel = data.status == 'pending' ? 'Menunggu' : (data.status == 'disetujui' ? 'Disetujui' : 'Ditolak');
                let statusColor = data.status == 'pending' ? '#f59e0b' : (data.status == 'disetujui' ? '#10b981' : '#ef4444');
                let gajiLembur = data.gaji_lembur ? 'Rp ' + new Intl.NumberFormat('id-ID').format(data.gaji_lembur) : '-';
                
                document.getElementById('detail_content').innerHTML = `
                    <div class="detail-box">
                        <div><strong>Guru:</strong> ${data.nama}</div>
                        <div><strong>Alasan:</strong> ${data.alasan}</div>
                        <div><strong>Tanggal:</strong> ${data.tanggal_mulai} s/d ${data.tanggal_selesai}</div>
                        <div><strong>Durasi:</strong> ${data.durasi} hari</div>
                        <div><strong>Gaji Lembur:</strong> ${gajiLembur}</div>
                        <div><strong>Status:</strong> <span style="color: ${statusColor};">${statusLabel}</span></div>
                        ${data.catatan_admin ? `<div><strong>Catatan Admin:</strong> ${data.catatan_admin}</div>` : ''}
                        <div><strong>Tanggal Ajuan:</strong> ${data.created_at}</div>
                    </div>
                `;
                document.getElementById('detailModal').style.display = 'flex';
            });
    }
    
    function lihatFoto(filename) {
        document.getElementById('fotoBukti').src = '../assets/uploads/bukti_ajuan/' + filename;
        document.getElementById('fotoModal').style.display = 'flex';
    }
    
    function showToast(type, message) {
        const toast = document.getElementById('toastNotification');
        const toastIcon = document.getElementById('toastIcon');
        const toastMessage = document.getElementById('toastMessage');
        
        toast.style.borderLeftColor = type === 'success' ? '#10b981' : '#dc2626';
        toastIcon.innerHTML = type === 'success' ? '✓' : '⚠️';
        toastIcon.style.color = type === 'success' ? '#10b981' : '#dc2626';
        toastMessage.innerHTML = message;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 4000);
    }
    
    function closeToast() { document.getElementById('toastNotification').classList.remove('show'); }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    document.getElementById('prosesModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeModal(); });
    document.getElementById('detailModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeDetailModal(); });
    document.getElementById('fotoModal').addEventListener('click', e => { if (e.target === e.currentTarget) closeFotoModal(); });
</script>

<style>
    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .stat-card {
        border-radius: 16px;
        padding: 16px;
        color: white;
        text-align: center;
    }
    .stat-pending { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .stat-approved { background: linear-gradient(135deg, #10b981, #059669); }
    .stat-rejected { background: linear-gradient(135deg, #ef4444, #dc2626); }
    .stat-total { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
    .stat-icon { font-size: 28px; margin-bottom: 8px; }
    .stat-value { font-size: 28px; font-weight: 700; }
    .stat-label { font-size: 12px; opacity: 0.9; }
    
    /* Filter Card */
    .filter-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-bottom: 24px;
    }
    .filter-header {
        padding: 14px 20px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        background: #fafcff;
    }
    .filter-body { padding: 20px; }
    .filter-form {
        display: flex;
        flex-wrap: wrap;
        gap: 12px;
        align-items: flex-end;
    }
    .filter-row { flex: 1; min-width: 140px; }
    .filter-select, .filter-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 13px;
    }
    .filter-actions { display: flex; gap: 10px; }
    .filter-btn {
        background: #c41e3a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
    }
    .filter-reset {
        background: #e2e8f0;
        color: #1e293b;
        padding: 10px 20px;
        border-radius: 10px;
        text-decoration: none;
    }
    
    /* Table */
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
    }
    .table-title { font-weight: 600; font-size: 16px; color: #1e293b; }
    .table-info { font-size: 12px; color: #64748b; background: #f1f5f9; padding: 4px 12px; border-radius: 20px; }
    .table-responsive { overflow-x: auto; }
    
    .modern-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .modern-table th, .modern-table td {
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: middle;
    }
    .modern-table th { background: #f8fafc; font-weight: 600; white-space: nowrap; }
    .text-center { text-align: center; }
    
    .teacher-info { display: flex; align-items: center; gap: 10px; }
    .teacher-avatar { width: 32px; height: 32px; border-radius: 50%; object-fit: cover; }
    .teacher-avatar-placeholder {
        width: 32px; height: 32px;
        background: #e2e8f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #64748b;
    }
    .teacher-name { font-weight: 500; }
    .teacher-nip { font-size: 10px; color: #94a3b8; }
    .alasan-text { max-width: 200px; font-size: 12px; }
    .gaji-amount { font-weight: 600; color: #10b981; }
    .text-muted { color: #94a3b8; }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-disetujui { background: #d1fae5; color: #065f46; }
    .status-ditolak { background: #fee2e2; color: #991b1b; }
    
    .btn-action, .btn-detail, .btn-foto {
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 11px;
        margin: 2px;
    }
    .btn-action { background: #3b82f6; color: white; }
    .btn-detail { background: #64748b; color: white; }
    .btn-foto { background: #8b5cf6; color: white; }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .modal-content {
        background: white;
        border-radius: 20px;
        max-width: 500px;
        width: 90%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
    }
    .modal-header h3 { margin: 0; font-size: 18px; }
    .modal-close { background: none; border: none; font-size: 24px; cursor: pointer; color: #94a3b8; }
    .modal-body { padding: 20px; }
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        padding: 16px 20px;
        border-top: 1px solid #e2e8f0;
    }
    .info-box {
        background: #f8fafc;
        border-radius: 12px;
        padding: 16px;
        margin-bottom: 20px;
    }
    .info-box div { margin-bottom: 8px; }
    .info-box div:last-child { margin-bottom: 0; }
    .gaji-info { background: #e0f2fe; padding: 8px; border-radius: 8px; margin-top: 8px; }
    .detail-box div { margin-bottom: 10px; }
    
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 13px; font-weight: 500; margin-bottom: 6px; color: #374151; }
    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
    }
    textarea.form-control { resize: vertical; }
    small { font-size: 11px; color: #64748b; display: block; margin-top: 4px; }
    
    .btn-cancel, .btn-save { padding: 10px 20px; border-radius: 10px; cursor: pointer; font-size: 13px; border: none; }
    .btn-cancel { background: #e2e8f0; color: #1e293b; }
    .btn-save { background: #c41e3a; color: white; }
    
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
        max-width: 350px;
        width: 90%;
        padding: 20px;
        text-align: center;
    }
    .modal-foto-content img { width: 100%; border-radius: 12px; margin-bottom: 16px; }
    .btn-modal-close { background: #64748b; color: white; border: none; padding: 10px; border-radius: 8px; cursor: pointer; width: 100%; }
    
    .empty-state { text-align: center; padding: 60px 20px; color: #94a3b8; }
    .empty-icon { font-size: 48px; margin-bottom: 12px; }
    
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
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
        .filter-form { flex-direction: column; }
        .filter-row { width: 100%; }
        .filter-actions { width: 100%; }
        .filter-btn, .filter-reset { flex: 1; text-align: center; }
        .modern-table th { display: none; }
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
            font-size: 11px;
        }
        .modern-table td:last-child { border-bottom: none; }
        .action-cell { justify-content: flex-end; }
        .alasan-text { max-width: none; }
        .toast-notification { top: 10px; right: 10px; left: 10px; min-width: auto; }
    }
    
    @media (max-width: 480px) {
        .content-wrapper { padding: 12px; }
        .stats-grid { gap: 8px; }
        .stat-value { font-size: 22px; }
        .stat-icon { font-size: 22px; }
        .teacher-avatar, .teacher-avatar-placeholder { width: 28px; height: 28px; font-size: 12px; }
        .teacher-name { font-size: 12px; }
        .btn-action, .btn-detail, .btn-foto { padding: 3px 8px; font-size: 10px; }
    }
</style>

<?php require_once '../includes/footer.php'; ?>