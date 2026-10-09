<?php
$page_title = 'Ajuan - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];

// Proses tambah ajuan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ajukan'])) {
    $jenis = $_POST['jenis'];
    $alasan = $_POST['alasan'];
    $tanggal_mulai = $_POST['tanggal_mulai'];
    $tanggal_selesai = $_POST['tanggal_selesai'];
    
    $foto_bukti = '';
    if (isset($_FILES['foto_bukti']) && $_FILES['foto_bukti']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['foto_bukti']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $foto_bukti = 'bukti_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../assets/uploads/bukti_ajuan/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            move_uploaded_file($_FILES['foto_bukti']['tmp_name'], $upload_dir . $foto_bukti);
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO ajuan (id_guru, jenis, alasan, foto_bukti, tanggal_mulai, tanggal_selesai, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    if ($stmt->execute([$id_guru, $jenis, $alasan, $foto_bukti, $tanggal_mulai, $tanggal_selesai])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Ajuan berhasil dikirim! Menunggu persetujuan admin.'];
        header("Location: ajuan.php");
        exit();
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal mengirim ajuan!'];
        header("Location: ajuan.php");
        exit();
    }
}

// Ambil riwayat ajuan
$stmt = $pdo->prepare("
    SELECT * FROM ajuan 
    WHERE id_guru = ? 
    ORDER BY created_at DESC
");
$stmt->execute([$id_guru]);
$ajuan_list = $stmt->fetchAll();

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <!-- Header dengan Tombol di Pojok Kanan -->
        <div class="page-header-flex">
            <div>
                <h1 class="page-title">📋 Ajuan</h1>
                <p class="page-subtitle">Kelola ajuan lembur, izin, sakit, atau cuti</p>
            </div>
            <button onclick="openModal()" class="btn-create">
                <span>+</span> Buat Ajuan
            </button>
        </div>
        
        <!-- Tabel Riwayat Ajuan -->
        <div class="table-container">
            <div class="table-header">
                <div class="table-title">📋 Riwayat Ajuan</div>
                <div class="table-info">Total: <?= count($ajuan_list) ?> ajuan</div>
            </div>
            <div class="table-responsive">
                <table class="modern-table">
                    <thead>
                        <tr>
                            <th>Tanggal Ajuan</th>
                            <th>Jenis</th>
                            <th>Alasan</th>
                            <th>Periode</th>
                            <th>Durasi</th>
                            <th>Status</th>
                            <th>Catatan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($ajuan_list) > 0): ?>
                            <?php foreach($ajuan_list as $ajuan): 
                                $jenis_icon = [
                                    'lembur' => '💪',
                                    'izin' => '🏠',
                                    'sakit' => '🤒',
                                    'cuti' => '🏖️'
                                ];
                                $jenis_label = [
                                    'lembur' => 'Lembur',
                                    'izin' => 'Izin',
                                    'sakit' => 'Sakit',
                                    'cuti' => 'Cuti'
                                ];
                                $status_class = $ajuan['status'] == 'pending' ? 'status-pending' : ($ajuan['status'] == 'disetujui' ? 'status-success' : 'status-rejected');
                                $status_label = $ajuan['status'] == 'pending' ? 'Menunggu' : ($ajuan['status'] == 'disetujui' ? 'Disetujui' : 'Ditolak');
                                
                                $tgl_mulai = new DateTime($ajuan['tanggal_mulai']);
                                $tgl_selesai = new DateTime($ajuan['tanggal_selesai']);
                                $durasi = $tgl_mulai->diff($tgl_selesai)->days + 1;
                            ?>
                            <tr>
                                <td data-label="Tanggal Ajuan"><?= date('d/m/Y H:i', strtotime($ajuan['created_at'])) ?></td>
                                <td data-label="Jenis">
                                    <span class="jenis-badge"><?= $jenis_icon[$ajuan['jenis']] ?> <?= $jenis_label[$ajuan['jenis']] ?></span>
                                </td>
                                <td data-label="Alasan"><?= htmlspecialchars(substr($ajuan['alasan'], 0, 60)) ?><?= strlen($ajuan['alasan']) > 60 ? '...' : '' ?></td>
                                <td data-label="Periode"><?= date('d/m/Y', strtotime($ajuan['tanggal_mulai'])) ?> - <?= date('d/m/Y', strtotime($ajuan['tanggal_selesai'])) ?></td>
                                <td data-label="Durasi" class="text-center"><?= $durasi ?> hari</td>
                                <td data-label="Status">
                                    <span class="status-badge <?= $status_class ?>"><?= $status_label ?></span>
                                </td>
                                <td data-label="Catatan"><?= htmlspecialchars($ajuan['catatan_admin'] ?? '-') ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="empty-state">📭 Belum ada ajuan. Klik "Buat Ajuan" untuk mengajukan.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modal Buat Ajuan -->
        <div id="ajuanModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>✍️ Buat Ajuan Baru</h3>
                    <button class="modal-close" onclick="closeModal()">&times;</button>
                </div>
                <div class="modal-body">
                    <form method="POST" enctype="multipart/form-data" id="formAjuan">
                        <div class="form-group">
                            <label class="form-label">Jenis Ajuan *</label>
                            <select name="jenis" class="form-control" required>
                                <option value="">Pilih Jenis</option>
                                <option value="lembur">💪 Lembur</option>
                                <option value="izin">🏠 Izin</option>
                                <option value="sakit">🤒 Sakit</option>
                                <option value="cuti">🏖️ Cuti</option>
                            </select>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Tanggal Mulai *</label>
                                <input type="date" name="tanggal_mulai" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Tanggal Selesai *</label>
                                <input type="date" name="tanggal_selesai" class="form-control" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alasan *</label>
                            <textarea name="alasan" class="form-control" rows="4" required placeholder="Jelaskan alasan pengajuan..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Foto Bukti (opsional)</label>
                            <input type="file" name="foto_bukti" class="form-control" accept="image/*">
                            <small class="form-hint">Upload foto bukti jika diperlukan (format: JPG, PNG, GIF)</small>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" onclick="closeModal()" class="btn-secondary-modal">Batal</button>
                            <button type="submit" name="ajukan" class="btn-primary-modal">Kirim Ajuan</button>
                        </div>
                    </form>
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
    </div>
</div>

<script>
    const modal = document.getElementById('ajuanModal');
    
    function openModal() {
        modal.style.display = 'flex';
    }
    
    function closeModal() {
        modal.style.display = 'none';
    }
    
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
    
    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target === modal) {
            closeModal();
        }
    }
    
    // Validate date
    const tglMulai = document.querySelector('input[name="tanggal_mulai"]');
    const tglSelesai = document.querySelector('input[name="tanggal_selesai"]');
    
    tglMulai.addEventListener('change', function() {
        tglSelesai.min = this.value;
    });
</script>

<style>
    /* Page Header with Button */
    .page-header-flex {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 28px;
        flex-wrap: wrap;
        gap: 16px;
    }
    
    .btn-create {
        background: #c41e3a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 12px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-create:hover {
        background: #a01830;
    }
    
    .btn-create span {
        font-size: 18px;
        font-weight: bold;
    }
    
    /* Table Container */
    .table-container {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        margin-top: 0;
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
        vertical-align: top;
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
    
    .text-center {
        text-align: center;
    }
    
    /* Status Badge */
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .status-pending {
        background: #fef3c7;
        color: #92400e;
    }
    
    .status-success {
        background: #d1fae5;
        color: #065f46;
    }
    
    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }
    
    .jenis-badge {
        display: inline-block;
        padding: 4px 8px;
        background: #f1f5f9;
        border-radius: 8px;
        font-size: 12px;
    }
    
    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }
    
    /* Modal */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
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
    
    .modal-header h3 {
        margin: 0;
        font-size: 18px;
    }
    
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #94a3b8;
    }
    
    .modal-body {
        padding: 20px;
    }
    
    .modal-footer {
        display: flex;
        justify-content: flex-end;
        gap: 12px;
        margin-top: 20px;
        padding-top: 16px;
        border-top: 1px solid #e2e8f0;
    }
    
    .btn-primary-modal {
        background: #c41e3a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .btn-secondary-modal {
        background: #e2e8f0;
        color: #1e293b;
        border: none;
        padding: 10px 20px;
        border-radius: 10px;
        cursor: pointer;
        font-weight: 500;
    }
    
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }
    
    .form-group {
        margin-bottom: 16px;
    }
    
    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 500;
        color: #374151;
        margin-bottom: 6px;
    }
    
    .form-control {
        width: 100%;
        padding: 10px 12px;
        font-size: 14px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        font-family: inherit;
    }
    
    .form-control:focus {
        outline: none;
        border-color: #c41e3a;
    }
    
    textarea.form-control {
        resize: vertical;
    }
    
    .form-hint {
        font-size: 11px;
        color: #64748b;
        display: block;
        margin-top: 4px;
    }
    
    /* Toast */
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
    
    .toast-notification.show {
        display: block;
    }
    
    .toast-content {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    /* Mobile Responsive */
    @media (max-width: 768px) {
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        .page-header-flex {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .btn-create {
            width: 100%;
            justify-content: center;
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
        
        .form-row {
            grid-template-columns: 1fr;
            gap: 0;
        }
        
        .toast-notification {
            top: 10px;
            right: 10px;
            left: 10px;
            min-width: auto;
        }
        
        .modal-content {
            width: 95%;
            margin: 20px auto;
        }
    }
    
    @media (max-width: 480px) {
        .content-wrapper {
            padding: 12px;
        }
        
        .modern-table td {
            font-size: 12px;
            padding: 8px 12px;
        }
        
        .modern-table td:before {
            font-size: 11px;
            width: 45%;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>