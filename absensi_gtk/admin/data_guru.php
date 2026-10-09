<?php
$page_title = 'Data Guru - GTK System';
require_once '../config/database.php';

// Buat folder jika belum ada
$upload_dir = '../assets/uploads/foto_guru/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Proses Tambah Guru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['tambah'])) {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $jabatan = $_POST['jabatan'];
    $no_hp = $_POST['no_hp'];
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $status = $_POST['status'];
    
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $foto = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
        }
    }
    
    $stmt = $pdo->prepare("INSERT INTO guru (nama, nip, jabatan, no_hp, username, password, status, foto, role) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'guru')");
    
    if ($stmt->execute([$nama, $nip, $jabatan, $no_hp, $username, $password, $status, $foto])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Guru berhasil ditambahkan!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal menambahkan guru!'];
    }
    header("Location: data_guru.php");
    exit();
}

// Proses Edit Guru
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['edit'])) {
    $id_guru = $_POST['id_guru'];
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $jabatan = $_POST['jabatan'];
    $no_hp = $_POST['no_hp'];
    $username = $_POST['username'];
    $status = $_POST['status'];
    
    $sql = "UPDATE guru SET nama=?, nip=?, jabatan=?, no_hp=?, username=?, status=?";
    $params = [$nama, $nip, $jabatan, $no_hp, $username, $status];
    
    if (!empty($_POST['password'])) {
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $sql .= ", password=?";
        $params[] = $password;
    }
    
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $stmt = $pdo->prepare("SELECT foto FROM guru WHERE id_guru = ?");
            $stmt->execute([$id_guru]);
            $old_foto = $stmt->fetchColumn();
            if ($old_foto && file_exists($upload_dir . $old_foto)) {
                unlink($upload_dir . $old_foto);
            }
            
            $foto = time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto);
            $sql .= ", foto=?";
            $params[] = $foto;
        }
    }
    
    $sql .= " WHERE id_guru=?";
    $params[] = $id_guru;
    
    $stmt = $pdo->prepare($sql);
    if ($stmt->execute($params)) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Guru berhasil diupdate!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal mengupdate guru!'];
    }
    header("Location: data_guru.php");
    exit();
}

// Proses Hapus Guru
if (isset($_GET['hapus'])) {
    $id_guru = $_GET['hapus'];
    
    $stmt = $pdo->prepare("SELECT foto FROM guru WHERE id_guru = ?");
    $stmt->execute([$id_guru]);
    $foto = $stmt->fetchColumn();
    if ($foto && file_exists($upload_dir . $foto)) {
        unlink($upload_dir . $foto);
    }
    
    $stmt = $pdo->prepare("DELETE FROM guru WHERE id_guru = ?");
    if ($stmt->execute([$id_guru])) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Guru berhasil dihapus!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal menghapus guru!'];
    }
    header("Location: data_guru.php");
    exit();
}

// Proses Import CSV
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_csv'])) {
    if (isset($_FILES['file_csv']) && $_FILES['file_csv']['error'] == 0) {
        $filename = $_FILES['file_csv']['tmp_name'];
        $handle = fopen($filename, "r");
        
        // Skip header
        fgetcsv($handle, 0, ";");
        
        $success_count = 0;
        $error_count = 0;
        
        while (($data = fgetcsv($handle, 0, ";")) !== false) {
            if (count($data) >= 6) {
                $nama = trim($data[0]);
                $nip = trim($data[1]);
                $jabatan = trim($data[2]);
                $no_hp = trim($data[3]);
                $username = trim($data[4]);
                $password = password_hash(trim($data[5]), PASSWORD_DEFAULT);
                $status = isset($data[6]) ? trim($data[6]) : 'aktif';
                
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM guru WHERE nip = ? OR username = ?");
                $stmt->execute([$nip, $username]);
                if ($stmt->fetchColumn() == 0) {
                    $stmt = $pdo->prepare("INSERT INTO guru (nama, nip, jabatan, no_hp, username, password, status, role) VALUES (?, ?, ?, ?, ?, ?, ?, 'guru')");
                    if ($stmt->execute([$nama, $nip, $jabatan, $no_hp, $username, $password, $status])) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                } else {
                    $error_count++;
                }
            }
        }
        fclose($handle);
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => "Import selesai! $success_count berhasil, $error_count gagal."];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal upload file!'];
    }
    header("Location: data_guru.php");
    exit();
}

// Ambil data guru
$stmt = $pdo->query("SELECT * FROM guru WHERE role = 'guru' ORDER BY nama");
$guru_list = $stmt->fetchAll();

// Toast notification
$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">👥 Data Guru</h1>
            <p class="page-subtitle">Kelola data guru</p>
        </div>
        
        <!-- Tombol Aksi -->
        <div style="display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap;">
            <button onclick="openTambahModal()" class="btn btn-primary">+ Tambah Guru</button>
            <button onclick="openImportModal()" class="btn btn-success">📥 Import CSV</button>
            <a href="download_template.php" class="btn btn-secondary" style="background: #6c757d; color: white; text-decoration: none; padding: 10px 20px; border-radius: 8px; display: inline-flex; align-items: center; gap: 8px;">📎 Download Template</a>
        </div>
        
        <!-- Tabel Data Guru -->
        <div class="card">
            <div class="card-header">
                <span>📋 Daftar Guru</span>
                <span style="font-size: 12px; color: #64748b;">Total: <?= count($guru_list) ?> guru</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>Nama</th>
                                <th>NIP</th>
                                <th>Jabatan</th>
                                <th>No HP</th>
                                <th>Username</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($guru_list) > 0): ?>
                                <?php foreach($guru_list as $guru): ?>
                                <tr>
                                    <td>
                                        <?php if($guru['foto'] && file_exists($upload_dir . $guru['foto'])): ?>
                                            <img src="../assets/uploads/foto_guru/<?= $guru['foto'] ?>" width="35" height="35" style="border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 35px; height: 35px; background: #e2e8f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #64748b;">
                                                <?= strtoupper(substr($guru['nama'], 0, 1)) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?= htmlspecialchars($guru['nama']) ?></strong></td>
                                    <td><?= htmlspecialchars($guru['nip']) ?></td>
                                    <td><?= htmlspecialchars($guru['jabatan']) ?></td>
                                    <td><?= htmlspecialchars($guru['no_hp']) ?></td>
                                    <td><?= htmlspecialchars($guru['username']) ?></td>
                                    <td>
                                        <span style="display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 11px; background: <?= $guru['status'] == 'aktif' ? '#d1fae5' : '#fee2e2' ?>; color: <?= $guru['status'] == 'aktif' ? '#065f46' : '#991b1b' ?>;">
                                            <?= $guru['status'] == 'aktif' ? 'Aktif' : 'Nonaktif' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="editGuru(<?= $guru['id_guru'] ?>)" style="background: #f59e0b; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer; margin-right: 5px;">Edit</button>
                                        <button onclick="hapusGuru(<?= $guru['id_guru'] ?>)" style="background: #dc2626; color: white; border: none; padding: 5px 10px; border-radius: 6px; cursor: pointer;">Hapus</button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 40px;">📭 Belum ada data guru</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    <table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Tambah Guru -->
<div id="tambahModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; margin: 20px auto; padding: 20px;">
        <h3 style="margin-bottom: 16px; font-size: 18px;">Tambah Guru</h3>
        <form method="POST" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Nama *</label>
                    <input type="text" name="nama" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">NIP *</label>
                    <input type="text" name="nip" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Jabatan</label>
                    <input type="text" name="jabatan" class="form-control" style="padding: 8px 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">No HP</label>
                    <input type="text" name="no_hp" class="form-control" style="padding: 8px 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Username *</label>
                    <input type="text" name="username" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Password *</label>
                    <input type="password" name="password" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Foto</label>
                    <input type="file" name="foto" class="form-control" style="padding: 5px;" accept="image/*">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Status</label>
                    <select name="status" class="form-control" style="padding: 8px 10px;">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeTambahModal()" class="btn btn-secondary" style="padding: 8px 16px;">Batal</button>
                <button type="submit" name="tambah" class="btn btn-primary" style="padding: 8px 16px;">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Guru -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center; overflow-y: auto;">
    <div style="background: white; border-radius: 16px; max-width: 500px; width: 90%; margin: 20px auto; padding: 20px;">
        <h3 style="margin-bottom: 16px; font-size: 18px;">Edit Guru</h3>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id_guru" id="edit_id">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Nama *</label>
                    <input type="text" name="nama" id="edit_nama" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">NIP *</label>
                    <input type="text" name="nip" id="edit_nip" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Jabatan</label>
                    <input type="text" name="jabatan" id="edit_jabatan" class="form-control" style="padding: 8px 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">No HP</label>
                    <input type="text" name="no_hp" id="edit_no_hp" class="form-control" style="padding: 8px 10px;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Username *</label>
                    <input type="text" name="username" id="edit_username" class="form-control" style="padding: 8px 10px;" required>
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Password</label>
                    <input type="password" name="password" class="form-control" style="padding: 8px 10px;" placeholder="Kosongkan jika tidak diubah">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Foto</label>
                    <input type="file" name="foto" class="form-control" style="padding: 5px;" accept="image/*">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" style="font-size: 12px;">Status</label>
                    <select name="status" id="edit_status" class="form-control" style="padding: 8px 10px;">
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
                <button type="button" onclick="closeEditModal()" class="btn btn-secondary" style="padding: 8px 16px;">Batal</button>
                <button type="submit" name="edit" class="btn btn-primary" style="padding: 8px 16px;">Update</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Import CSV -->
<div id="importModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: white; border-radius: 16px; max-width: 400px; width: 90%; margin: 20px auto; padding: 24px;">
        <h3 style="margin-bottom: 16px; font-size: 18px;">📥 Import Data Guru (CSV)</h3>
        <form method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label">File CSV (.csv)</label>
                <input type="file" name="file_csv" class="form-control" accept=".csv" required>
            </div>
            <div style="margin-bottom: 16px; font-size: 12px; color: #64748b;">
                Format: Nama;NIP;Jabatan;No HP;Username;Password;Status
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button type="button" onclick="closeImportModal()" class="btn btn-secondary">Batal</button>
                <button type="submit" name="import_csv" class="btn btn-primary">Import</button>
            </div>
        </form>
    </div>
</div>

<!-- Toast Notification -->
<div id="toast" style="position: fixed; top: 20px; right: 20px; z-index: 1100; display: none;">
    <div id="toastContent" style="background: white; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 250px; border-left: 4px solid;">
        <div id="toastMessage" style="font-size: 14px;"></div>
    </div>
</div>

<script>
    function showToast(type, message) {
        const toast = document.getElementById('toast');
        const toastContent = document.getElementById('toastContent');
        const toastMessage = document.getElementById('toastMessage');
        
        let borderColor, icon;
        if (type === 'success') {
            borderColor = '#10b981';
            icon = '✓ ';
        } else {
            borderColor = '#dc2626';
            icon = '⚠️ ';
        }
        
        toastContent.style.borderLeftColor = borderColor;
        toastMessage.innerHTML = icon + message;
        toast.style.display = 'block';
        
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    function openTambahModal() {
        document.getElementById('tambahModal').style.display = 'flex';
    }
    
    function closeTambahModal() {
        document.getElementById('tambahModal').style.display = 'none';
    }
    
    function openImportModal() {
        document.getElementById('importModal').style.display = 'flex';
    }
    
    function closeImportModal() {
        document.getElementById('importModal').style.display = 'none';
    }
    
    function editGuru(id) {
        fetch('get_guru.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                document.getElementById('edit_id').value = data.id_guru;
                document.getElementById('edit_nama').value = data.nama;
                document.getElementById('edit_nip').value = data.nip;
                document.getElementById('edit_jabatan').value = data.jabatan || '';
                document.getElementById('edit_no_hp').value = data.no_hp || '';
                document.getElementById('edit_username').value = data.username;
                document.getElementById('edit_status').value = data.status;
                document.getElementById('editModal').style.display = 'flex';
            });
    }
    
    function closeEditModal() {
        document.getElementById('editModal').style.display = 'none';
    }
    
    function hapusGuru(id) {
        if (confirm('Yakin ingin menghapus guru ini?')) {
            window.location.href = '?hapus=' + id;
        }
    }
    
    document.getElementById('tambahModal').addEventListener('click', function(e) {
        if (e.target === this) closeTambahModal();
    });
    document.getElementById('editModal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('importModal').addEventListener('click', function(e) {
        if (e.target === this) closeImportModal();
    });
</script>

<style>
    .data-table th,
    .data-table td {
        padding: 12px 16px;
        border: 1px solid #e2e8f0;
        text-align: left;
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
    }
</style>

<?php require_once '../includes/footer.php'; ?>