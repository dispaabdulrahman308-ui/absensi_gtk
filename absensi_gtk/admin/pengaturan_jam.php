<?php
$page_title = 'Pengaturan Jam Masuk - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Proses Tambah
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $jam_ke = $_POST['jam_ke'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM jam_kerja WHERE jam_ke = ?");
    $stmt->execute([$jam_ke]);
    $exists = $stmt->fetchColumn();
    
    if ($exists) {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Jam ke-$jam_ke sudah ada!"];
    } else {
        $stmt = $pdo->prepare("INSERT INTO jam_kerja (jam_ke, jam_mulai, jam_selesai) VALUES (?, ?, ?)");
        if ($stmt->execute([$jam_ke, $jam_mulai, $jam_selesai])) {
            $_SESSION['toast'] = ['type' => 'success', 'message' => "Jam ke-$jam_ke berhasil ditambahkan!"];
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "Gagal menambahkan jam kerja!"];
        }
    }
    header("Location: pengaturan_jam.php");
    exit();
}

// Proses Edit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id_jam = $_POST['id_jam'];
    $jam_ke = $_POST['jam_ke'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];
    
    $stmt = $pdo->prepare("UPDATE jam_kerja SET jam_ke = ?, jam_mulai = ?, jam_selesai = ? WHERE id_jam = ?");
    if ($stmt->execute([$jam_ke, $jam_mulai, $jam_selesai, $id_jam])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Jam ke-$jam_ke berhasil diupdate!"];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Gagal mengupdate jam kerja!"];
    }
    header("Location: pengaturan_jam.php");
    exit();
}

// Proses Hapus
if (isset($_GET['hapus'])) {
    $id_jam = $_GET['hapus'];
    $stmt = $pdo->prepare("DELETE FROM jam_kerja WHERE id_jam = ?");
    if ($stmt->execute([$id_jam])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Jam kerja berhasil dihapus!"];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Gagal menghapus jam kerja!"];
    }
    header("Location: pengaturan_jam.php");
    exit();
}

// Ambil data jam kerja
$stmt = $pdo->query("SELECT * FROM jam_kerja ORDER BY jam_ke");
$jam_kerja = $stmt->fetchAll();

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">⏰ Pengaturan Jam Masuk</h1>
            <p class="page-subtitle">Atur jam pelajaran (dapat ditambah/dikurangi sesuai kebutuhan sekolah)</p>
        </div>
        
        <!-- Form Tambah Jam -->
        <div class="card">
            <div class="card-header">➕ Tambah Jam Pelajaran Baru</div>
            <div class="card-body">
                <form method="POST" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1; min-width: 120px;">
                        <label class="form-label">Jam ke-</label>
                        <input type="number" name="jam_ke" class="form-control" placeholder="Contoh: 7" required min="1">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label class="form-label">Jam Mulai</label>
                        <input type="time" name="jam_mulai" class="form-control" required>
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <label class="form-label">Jam Selesai</label>
                        <input type="time" name="jam_selesai" class="form-control" required>
                    </div>
                    <div>
                        <button type="submit" name="tambah" class="btn btn-primary">+ Tambah</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar Jam Kerja -->
        <div class="card">
            <div class="card-header">
                <span>📋 Daftar Jam Pelajaran</span>
                <span style="font-size: 12px; color: #64748b;">Total: <?= count($jam_kerja) ?> jam</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Jam ke-</th>
                                <th>Jam Mulai</th>
                                <th>Jam Selesai</th>
                                <th>Durasi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($jam_kerja) > 0): ?>
                                <?php foreach($jam_kerja as $jam): 
                                    $mulai = strtotime($jam['jam_mulai']);
                                    $selesai = strtotime($jam['jam_selesai']);
                                    $durasi = ($selesai - $mulai) / 60;
                                ?>
                                <tr>
                                    <td data-label="Jam ke-"><strong>Jam ke-<?= $jam['jam_ke'] ?></strong></td>
                                    <td data-label="Jam Mulai"><?= date('H:i', strtotime($jam['jam_mulai'])) ?></td>
                                    <td data-label="Jam Selesai"><?= date('H:i', strtotime($jam['jam_selesai'])) ?></td>
                                    <td data-label="Durasi"><?= $durasi ?> menit</td>
                                    <td data-label="Aksi">
                                        <button onclick="openEditModal(<?= $jam['id_jam'] ?>, <?= $jam['jam_ke'] ?>, '<?= $jam['jam_mulai'] ?>', '<?= $jam['jam_selesai'] ?>')" class="btn btn-warning btn-sm">✏️ Edit</button>
                                        <a href="?hapus=<?= $jam['id_jam'] ?>" onclick="return confirm('Yakin ingin menghapus jam ini?')" class="btn btn-danger btn-sm">🗑️ Hapus</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center">📭 Belum ada data jam pelajaran</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Informasi -->
        <div class="card">
            <div class="card-header">ℹ️ Informasi</div>
            <div class="card-body">
                <ul class="info-list">
                    <li>Jam pelajaran akan digunakan untuk sistem absensi per jam</li>
                    <li>Guru akan melakukan absen setiap jam sesuai dengan jadwal yang ditentukan</li>
                    <li>Anda dapat menambah atau mengurangi jam sesuai kebijakan sekolah</li>
                    <li>Urutan jam akan diurutkan berdasarkan "Jam ke-" secara otomatis</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 20px; max-width: 500px; width: 90%; padding: 24px;">
        <h3 style="margin-bottom: 20px;">✏️ Edit Jam Pelajaran</h3>
        <form method="POST">
            <input type="hidden" name="id_jam" id="edit_id_jam">
            <div class="form-group">
                <label class="form-label">Jam ke-</label>
                <input type="number" name="jam_ke" id="edit_jam_ke" class="form-control" required min="1">
            </div>
            <div class="form-group">
                <label class="form-label">Jam Mulai</label>
                <input type="time" name="jam_mulai" id="edit_jam_mulai" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Jam Selesai</label>
                <input type="time" name="jam_selesai" id="edit_jam_selesai" class="form-control" required>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification -->
<div id="toastNotification" class="toast-notification">
    <div class="toast-content">
        <span id="toastIcon"></span>
        <span id="toastMessage" style="flex: 1;"></span>
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
            toastIcon.style.fontWeight = 'bold';
            toastIcon.style.fontSize = '18px';
        } else {
            toast.style.borderLeftColor = '#dc2626';
            toastIcon.innerHTML = '⚠️';
            toastIcon.style.color = '#dc2626';
            toastIcon.style.fontSize = '16px';
        }
        
        toastMessage.innerHTML = message;
        toast.classList.add('show');
        
        setTimeout(function() {
            toast.classList.remove('show');
        }, 4000);
    }
    
    function closeToast() {
        document.getElementById('toastNotification').classList.remove('show');
    }
    
    function openEditModal(id, jam_ke, jam_mulai, jam_selesai) {
        document.getElementById('edit_id_jam').value = id;
        document.getElementById('edit_jam_ke').value = jam_ke;
        document.getElementById('edit_jam_mulai').value = jam_mulai;
        document.getElementById('edit_jam_selesai').value = jam_selesai;
        document.getElementById('editModal').style.display = 'flex';
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
</script>

<style>
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    .info-list {
        margin-left: 20px;
        color: #475569;
        font-size: 13px;
        line-height: 1.8;
    }
    .text-center {
        text-align: center;
        padding: 40px;
        color: #94a3b8;
    }
    @media (max-width: 768px) {
        .data-table th { display: none; }
        .data-table td {
            display: block;
            text-align: right;
            padding: 10px 16px;
        }
        .data-table td:before {
            content: attr(data-label);
            float: left;
            font-weight: 600;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>