<?php
$page_title = 'Ajuan Izin/Sakit/Cuti - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Proses update status ajuan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id_ajuan = $_POST['id_ajuan'];
    $status = $_POST['status'];
    $catatan = $_POST['catatan'] ?? '';
    
    $stmt = $pdo->prepare("UPDATE ajuan SET status = ?, catatan_admin = ? WHERE id_ajuan = ?");
    if ($stmt->execute([$status, $catatan, $id_ajuan])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Status ajuan berhasil diubah!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal mengubah status ajuan!'];
    }
    header("Location: ajuan_izin.php");
    exit();
}

// Filter
$filter_status = $_GET['status'] ?? '';
$filter_jenis = $_GET['jenis'] ?? '';
$filter_tanggal_mulai = $_GET['tanggal_mulai'] ?? '';
$filter_tanggal_selesai = $_GET['tanggal_selesai'] ?? '';

// Query ajuan (sakit, izin, cuti)
$sql = "
    SELECT a.*, g.nama, g.nip, g.foto, g.no_hp
    FROM ajuan a
    JOIN guru g ON a.id_guru = g.id_guru
    WHERE a.jenis IN ('sakit', 'izin', 'cuti')
";
$params = [];

if ($filter_status) {
    $sql .= " AND a.status = ?";
    $params[] = $filter_status;
}
if ($filter_jenis) {
    $sql .= " AND a.jenis = ?";
    $params[] = $filter_jenis;
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
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis IN ('sakit','izin','cuti') AND status = 'pending'");
$pending_count = $stmt->fetch();
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis IN ('sakit','izin','cuti') AND status = 'disetujui'");
$approved_count = $stmt->fetch();
$stmt = $pdo->query("SELECT COUNT(*) as total FROM ajuan WHERE jenis IN ('sakit','izin','cuti') AND status = 'ditolak'");
$rejected_count = $stmt->fetch();

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">🏠 Ajuan Izin / Sakit / Cuti</h1>
            <p class="page-subtitle">Kelola ajuan izin, sakit, dan cuti dari guru</p>
        </div>
        
        <!-- Statistik -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
            <div style="background: linear-gradient(135deg, #f59e0b, #d97706); border-radius: 16px; padding: 16px; color: white;">
                <div style="font-size: 28px; font-weight: 700;"><?= $pending_count['total'] ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Menunggu Persetujuan</div>
            </div>
            <div style="background: linear-gradient(135deg, #10b981, #059669); border-radius: 16px; padding: 16px; color: white;">
                <div style="font-size: 28px; font-weight: 700;"><?= $approved_count['total'] ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Disetujui</div>
            </div>
            <div style="background: linear-gradient(135deg, #ef4444, #dc2626); border-radius: 16px; padding: 16px; color: white;">
                <div style="font-size: 28px; font-weight: 700;"><?= $rejected_count['total'] ?></div>
                <div style="font-size: 12px; opacity: 0.9;">Ditolak</div>
            </div>
        </div>
        
        <!-- Filter -->
        <div class="card">
            <div class="card-header">
                <span>🔍 Filter Ajuan</span>
            </div>
            <div class="card-body">
                <form method="GET" action="" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1; min-width: 120px;">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua</option>
                            <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Menunggu</option>
                            <option value="disetujui" <?= $filter_status == 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                            <option value="ditolak" <?= $filter_status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 120px;">
                        <label class="form-label">Jenis</label>
                        <select name="jenis" class="form-control">
                            <option value="">Semua</option>
                            <option value="sakit" <?= $filter_jenis == 'sakit' ? 'selected' : '' ?>>Sakit</option>
                            <option value="izin" <?= $filter_jenis == 'izin' ? 'selected' : '' ?>>Izin</option>
                            <option value="cuti" <?= $filter_jenis == 'cuti' ? 'selected' : '' ?>>Cuti</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 130px;">
                        <label class="form-label">Tanggal Mulai</label>
                        <input type="date" name="tanggal_mulai" class="form-control" value="<?= $filter_tanggal_mulai ?>">
                    </div>
                    <div style="flex: 1; min-width: 130px;">
                        <label class="form-label">Tanggal Selesai</label>
                        <input type="date" name="tanggal_selesai" class="form-control" value="<?= $filter_tanggal_selesai ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="ajuan_izin.php" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Tabel Ajuan -->
        <div class="card">
            <div class="card-header">
                <span>📋 Daftar Ajuan Izin / Sakit / Cuti</span>
                <span style="font-size: 12px; color: #64748b;">Total: <?= count($ajuan_list) ?> ajuan</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Guru</th>
                                <th>Jenis</th>
                                <th>Alasan</th>
                                <th>Tanggal</th>
                                <th>Durasi</th>
                                <th>Status</th>
                                <th>Tanggal Ajuan</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($ajuan_list) > 0): ?>
                                <?php foreach($ajuan_list as $ajuan): 
                                    $tgl_mulai = new DateTime($ajuan['tanggal_mulai']);
                                    $tgl_selesai = new DateTime($ajuan['tanggal_selesai']);
                                    $durasi = $tgl_mulai->diff($tgl_selesai)->days + 1;
                                    
                                    $jenis_icon = [
                                        'sakit' => '🤒',
                                        'izin' => '🏠',
                                        'cuti' => '🏖️'
                                    ];
                                    $jenis_label = [
                                        'sakit' => 'Sakit',
                                        'izin' => 'Izin',
                                        'cuti' => 'Cuti'
                                    ];
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <?php if($ajuan['foto'] && file_exists('../assets/uploads/foto_guru/' . $ajuan['foto'])): ?>
                                                <img src="../assets/uploads/foto_guru/<?= $ajuan['foto'] ?>" width="32" height="32" style="border-radius: 50%; object-fit: cover;">
                                            <?php else: ?>
                                                <div style="width: 32px; height: 32px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px;">
                                                    <?= strtoupper(substr($ajuan['nama'], 0, 1)) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div>
                                                <div style="font-weight: 500;"><?= htmlspecialchars($ajuan['nama']) ?></div>
                                                <div style="font-size: 10px; color: #64748b;"><?= $ajuan['nip'] ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="font-size: 18px;"><?= $jenis_icon[$ajuan['jenis']] ?></span>
                                        <span style="font-size: 12px;"><?= $jenis_label[$ajuan['jenis']] ?></span>
                                    </td>
                                    <td style="max-width: 200px;">
                                        <div style="font-size: 13px;"><?= htmlspecialchars(substr($ajuan['alasan'], 0, 50)) ?><?= strlen($ajuan['alasan']) > 50 ? '...' : '' ?></div>
                                        <?php if($ajuan['catatan_admin']): ?>
                                            <div style="font-size: 10px; color: #c41e3a; margin-top: 4px;">
                                                📝 Catatan: <?= htmlspecialchars(substr($ajuan['catatan_admin'], 0, 40)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= date('d/m/Y', strtotime($ajuan['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($ajuan['tanggal_selesai'])) ?>
                                    </td>
                                    <td><?= $durasi ?> hari</td>
                                    <td>
                                        <span class="status-badge status-<?= $ajuan['status'] ?>">
                                            <?= $ajuan['status'] == 'pending' ? 'Menunggu' : ($ajuan['status'] == 'disetujui' ? 'Disetujui' : 'Ditolak') ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($ajuan['created_at'])) ?></td>
                                    <td>
                                        <?php if($ajuan['status'] == 'pending'): ?>
                                            <button onclick="openModal(<?= $ajuan['id_ajuan'] ?>, '<?= $ajuan['jenis'] ?>', '<?= addslashes($ajuan['nama']) ?>', '<?= addslashes($ajuan['alasan']) ?>', '<?= $ajuan['tanggal_mulai'] ?>', '<?= $ajuan['tanggal_selesai'] ?>', '<?= $ajuan['foto_bukti'] ?>')" class="btn-action">Proses</button>
                                        <?php else: ?>
                                            <button onclick="lihatDetail(<?= $ajuan['id_ajuan'] ?>)" class="btn-detail">Detail</button>
                                        <?php endif; ?>
                                        <?php if($ajuan['foto_bukti']): ?>
                                            <button onclick="lihatFoto('<?= $ajuan['foto_bukti'] ?>')" class="btn-foto">📷</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">📭 Belum ada ajuan izin/sakit/cuti</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Proses Ajuan -->
<div id="prosesModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; margin: 20px auto; padding: 24px;">
        <h3 style="margin-bottom: 16px;">Proses Ajuan</h3>
        <form method="POST">
            <input type="hidden" name="id_ajuan" id="proses_id">
            
            <div style="background: #f8fafc; border-radius: 12px; padding: 16px; margin-bottom: 20px;">
                <div><strong>Guru:</strong> <span id="proses_nama"></span></div>
                <div style="margin-top: 8px;"><strong>Jenis:</strong> <span id="proses_jenis"></span></div>
                <div style="margin-top: 8px;"><strong>Alasan:</strong> <span id="proses_alasan"></span></div>
                <div style="margin-top: 8px;"><strong>Tanggal:</strong> <span id="proses_tanggal"></span></div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Status</label>
                <select name="status" id="proses_status" class="form-control" required>
                    <option value="disetujui">✓ Disetujui</option>
                    <option value="ditolak">✗ Ditolak</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Catatan (opsional)</label>
                <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan untuk guru..."></textarea>
            </div>
            
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeModal()" class="btn btn-secondary">Batal</button>
                <button type="submit" name="update_status" class="btn btn-primary">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Detail Ajuan -->
<div id="detailModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; margin: 20px auto; padding: 24px;">
        <h3 style="margin-bottom: 16px;">Detail Ajuan</h3>
        <div id="detail_content"></div>
        <div style="display: flex; justify-content: flex-end; margin-top: 20px;">
            <button onclick="closeDetailModal()" class="btn btn-secondary">Tutup</button>
        </div>
    </div>
</div>

<!-- Modal Foto Bukti -->
<div id="fotoModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 1100; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; max-width: 400px; width: 90%; padding: 20px;">
        <div style="text-align: center; margin-bottom: 16px;">
            <h3 style="font-size: 16px;">Foto Bukti</h3>
        </div>
        <img id="fotoBukti" src="" alt="Foto Bukti" style="width: 100%; border-radius: 12px;">
        <div style="text-align: center; margin-top: 16px;">
            <button onclick="closeFotoModal()" class="btn btn-secondary">Tutup</button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" style="position: fixed; top: 20px; right: 20px; z-index: 9999; display: none;">
    <div style="background: white; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px; border-left: 4px solid;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span id="toastIcon"></span>
            <span id="toastMessage" style="font-size: 14px; flex: 1;"></span>
            <button onclick="closeToast()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>
    </div>
</div>

<script>
    function openModal(id, jenis, nama, alasan, tgl_mulai, tgl_selesai, foto) {
        document.getElementById('proses_id').value = id;
        document.getElementById('proses_nama').innerHTML = nama;
        
        let jenisLabel = '';
        if (jenis == 'sakit') jenisLabel = '🤒 Sakit';
        else if (jenis == 'izin') jenisLabel = '🏠 Izin';
        else jenisLabel = '🏖️ Cuti';
        
        document.getElementById('proses_jenis').innerHTML = jenisLabel;
        document.getElementById('proses_alasan').innerHTML = alasan;
        document.getElementById('proses_tanggal').innerHTML = tgl_mulai + ' s/d ' + tgl_selesai;
        document.getElementById('prosesModal').style.display = 'flex';
    }
    
    function closeModal() {
        document.getElementById('prosesModal').style.display = 'none';
    }
    
    function lihatDetail(id) {
        fetch('get_ajuan.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                let jenisLabel = '';
                if (data.jenis == 'sakit') jenisLabel = '🤒 Sakit';
                else if (data.jenis == 'izin') jenisLabel = '🏠 Izin';
                else if (data.jenis == 'cuti') jenisLabel = '🏖️ Cuti';
                else jenisLabel = '💪 Lembur';
                
                let statusLabel = data.status == 'pending' ? 'Menunggu' : (data.status == 'disetujui' ? 'Disetujui' : 'Ditolak');
                let statusColor = data.status == 'pending' ? '#f59e0b' : (data.status == 'disetujui' ? '#10b981' : '#ef4444');
                
                document.getElementById('detail_content').innerHTML = `
                    <div style="background: #f8fafc; border-radius: 12px; padding: 16px;">
                        <div style="margin-bottom: 12px;"><strong>Guru:</strong> ${data.nama}</div>
                        <div style="margin-bottom: 12px;"><strong>Jenis:</strong> ${jenisLabel}</div>
                        <div style="margin-bottom: 12px;"><strong>Alasan:</strong> ${data.alasan}</div>
                        <div style="margin-bottom: 12px;"><strong>Tanggal:</strong> ${data.tanggal_mulai} s/d ${data.tanggal_selesai}</div>
                        <div style="margin-bottom: 12px;"><strong>Durasi:</strong> ${data.durasi} hari</div>
                        <div style="margin-bottom: 12px;"><strong>Status:</strong> <span style="color: ${statusColor}; font-weight: 600;">${statusLabel}</span></div>
                        ${data.catatan_admin ? `<div style="margin-bottom: 12px;"><strong>Catatan Admin:</strong> ${data.catatan_admin}</div>` : ''}
                        <div><strong>Tanggal Ajuan:</strong> ${data.created_at}</div>
                    </div>
                `;
                document.getElementById('detailModal').style.display = 'flex';
            });
    }
    
    function closeDetailModal() {
        document.getElementById('detailModal').style.display = 'none';
    }
    
    function lihatFoto(filename) {
        document.getElementById('fotoBukti').src = '../assets/uploads/bukti_ajuan/' + filename;
        document.getElementById('fotoModal').style.display = 'flex';
    }
    
    function closeFotoModal() {
        document.getElementById('fotoModal').style.display = 'none';
    }
    
    function showToast(type, message) {
        const toast = document.getElementById('toastNotification');
        const toastContent = toast.querySelector('div');
        const toastIcon = document.getElementById('toastIcon');
        const toastMessage = document.getElementById('toastMessage');
        
        if (type === 'success') {
            toastContent.style.borderLeftColor = '#10b981';
            toastIcon.innerHTML = '✓';
            toastIcon.style.color = '#10b981';
        } else {
            toastContent.style.borderLeftColor = '#dc2626';
            toastIcon.innerHTML = '⚠️';
            toastIcon.style.color = '#dc2626';
        }
        
        toastMessage.innerHTML = message;
        toast.style.display = 'block';
        
        setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }
    
    function closeToast() {
        document.getElementById('toastNotification').style.display = 'none';
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    document.getElementById('prosesModal').addEventListener('click', function(e) {
        if (e.target === this) closeModal();
    });
    document.getElementById('detailModal').addEventListener('click', function(e) {
        if (e.target === this) closeDetailModal();
    });
    document.getElementById('fotoModal').addEventListener('click', function(e) {
        if (e.target === this) closeFotoModal();
    });
</script>

<style>
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
    
    .btn-action {
        background: #3b82f6;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 5px;
    }
    .btn-detail {
        background: #64748b;
        color: white;
        border: none;
        padding: 5px 12px;
        border-radius: 6px;
        cursor: pointer;
        margin-right: 5px;
    }
    .btn-foto {
        background: #8b5cf6;
        color: white;
        border: none;
        padding: 5px 10px;
        border-radius: 6px;
        cursor: pointer;
    }
    
    .data-table th,
    .data-table td {
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        text-align: left;
        vertical-align: middle;
    }
    .data-table th {
        background: #f8fafc;
        font-weight: 600;
    }
    
    @media (max-width: 768px) {
        .data-table th,
        .data-table td {
            padding: 8px 12px;
            font-size: 12px;
        }
        .btn-action, .btn-detail, .btn-foto {
            padding: 3px 8px;
            font-size: 11px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>