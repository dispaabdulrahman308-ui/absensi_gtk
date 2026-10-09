<?php
$page_title = 'Pengaturan Sistem - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil data admin untuk ubah password
$stmt = $pdo->prepare("SELECT * FROM guru WHERE id_guru = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Proses Ubah Password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    if (password_verify($password_lama, $admin['password'])) {
        if ($password_baru == $konfirmasi_password) {
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE guru SET password = ? WHERE id_guru = ?");
            if ($stmt->execute([$password_hash, $_SESSION['user_id']])) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Password berhasil diubah!'];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal mengubah password!'];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password baru tidak cocok!'];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Password lama salah!'];
    }
    header("Location: pengaturan_sistem.php");
    exit();
}

// Proses Backup Database
if (isset($_GET['backup'])) {
    $backup_file = '../backup/backup_' . date('Y-m-d_H-i-s') . '.sql';
    $backup_dir = '../backup/';
    
    if (!file_exists($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'absensi_gtk';
    
    $command = "mysqldump --user={$db_user} --password={$db_pass} --host={$db_host} {$db_name} > {$backup_file}";
    exec($command);
    
    if (file_exists($backup_file)) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Backup database berhasil!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal melakukan backup database!'];
    }
    header("Location: pengaturan_sistem.php");
    exit();
}

// Proses Reset Data
if (isset($_POST['reset_data'])) {
    $confirm = $_POST['confirm_reset'];
    if ($confirm == 'RESET') {
        try {
            $pdo->exec("TRUNCATE TABLE absensi");
            $pdo->exec("TRUNCATE TABLE ajuan");
            $pdo->exec("TRUNCATE TABLE gaji");
            $pdo->exec("TRUNCATE TABLE backup_log");
            $_SESSION['toast'] = ['type' => 'success', 'message' => 'Reset data berhasil! Semua data transaksi telah dihapus.'];
        } catch (PDOException $e) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => 'Gagal reset data: ' . $e->getMessage()];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'Konfirmasi reset tidak valid! Ketik "RESET" untuk mengkonfirmasi.'];
    }
    header("Location: pengaturan_sistem.php");
    exit();
}

// Ambil daftar file backup
$backup_dir = '../backup/';
$backup_files = [];
if (file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => date('d/m/Y H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
    rsort($backup_files);
}

// Hapus backup
if (isset($_GET['hapus_backup'])) {
    $file = $_GET['hapus_backup'];
    if (file_exists($backup_dir . $file)) {
        unlink($backup_dir . $file);
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'File backup berhasil dihapus!'];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => 'File backup tidak ditemukan!'];
    }
    header("Location: pengaturan_sistem.php");
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
            <h1 class="page-title">🔧 Pengaturan Sistem</h1>
            <p class="page-subtitle">Kelola keamanan, backup, dan reset data sistem</p>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 24px;">
            
            <!-- Ubah Password -->
            <div class="card">
                <div class="card-header">🔐 Ubah Password Admin</div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="password_lama" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" required>
                        </div>
                        <button type="submit" name="ubah_password" class="btn btn-primary" style="width: 100%;">Ganti Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Backup Database -->
            <div class="card">
                <div class="card-header">💾 Backup Database</div>
                <div class="card-body">
                    <p style="margin-bottom: 16px; font-size: 13px; color: #64748b;">Backup database akan menyimpan semua data ke file SQL. Disarankan melakukan backup secara berkala.</p>
                    <a href="?backup=1" class="btn btn-success" style="width: 100%; text-align: center; display: block;" onclick="return confirm('Yakin ingin melakukan backup database?')">📥 Backup Sekarang</a>
                </div>
            </div>
        </div>
        
        <!-- Daftar File Backup -->
        <div class="card">
            <div class="card-header">
                <span>📋 Daftar File Backup</span>
                <span style="font-size: 12px; color: #64748b;">Total: <?= count($backup_files) ?> file</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Nama File</th>
                                <th>Tanggal</th>
                                <th>Ukuran</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($backup_files) > 0): ?>
                                <?php foreach($backup_files as $file): ?>
                                <tr>
                                    <td data-label="Nama File"><?= htmlspecialchars($file['name']) ?></td>
                                    <td data-label="Tanggal"><?= $file['date'] ?></td>
                                    <td data-label="Ukuran"><?= number_format($file['size'] / 1024, 2) ?> KB</td>
                                    <td data-label="Aksi">
                                        <a href="../backup/<?= $file['name'] ?>" download class="btn btn-success btn-sm">⬇️ Download</a>
                                        <a href="?hapus_backup=<?= urlencode($file['name']) ?>" onclick="return confirm('Yakin ingin menghapus backup ini?')" class="btn btn-danger btn-sm">🗑️ Hapus</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center">📭 Belum ada file backup</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Reset Data -->
        <div class="card" style="border: 1px solid #fee2e2;">
            <div class="card-header" style="background: #fef2f2; color: #991b1b;">⚠️ Reset Data Sistem</div>
            <div class="card-body">
                <div class="alert alert-danger" style="margin-bottom: 16px;">
                    <strong>⚠️ Peringatan!</strong> Fitur ini akan menghapus SEMUA data transaksi (absensi, ajuan, gaji).
                    <strong>Data guru dan pengaturan sistem TIDAK akan terhapus.</strong>
                </div>
                <form method="POST" onsubmit="return confirm('PERINGATAN! Anda yakin ingin mereset semua data? Tindakan ini tidak dapat dibatalkan.')">
                    <div class="form-group">
                        <label class="form-label">Ketik <strong style="color: #dc2626;">RESET</strong> untuk konfirmasi</label>
                        <input type="text" name="confirm_reset" class="form-control" placeholder="RESET" required style="border-color: #dc2626;">
                    </div>
                    <button type="submit" name="reset_data" class="btn btn-danger" style="width: 100%;">🗑️ Reset Semua Data</button>
                </form>
            </div>
        </div>
        
        <!-- Informasi Sistem -->
        <div class="card">
            <div class="card-header">ℹ️ Informasi Sistem</div>
            <div class="card-body">
                <div class="info-grid">
                    <div>
                        <div class="info-label">Versi Aplikasi</div>
                        <div class="info-value">GTK System v1.0</div>
                    </div>
                    <div>
                        <div class="info-label">PHP Version</div>
                        <div class="info-value"><?= phpversion() ?></div>
                    </div>
                    <div>
                        <div class="info-label">Database</div>
                        <div class="info-value">MySQL</div>
                    </div>
                    <div>
                        <div class="info-label">Terakhir Backup</div>
                        <div class="info-value"><?= count($backup_files) > 0 ? $backup_files[0]['date'] : 'Belum pernah' ?></div>
                    </div>
                </div>
            </div>
        </div>
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
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
</script>

<style>
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 16px;
    }
    .info-label {
        font-size: 11px;
        color: #64748b;
        margin-bottom: 4px;
    }
    .info-value {
        font-size: 14px;
        font-weight: 500;
    }
    .btn-sm {
        padding: 5px 10px;
        font-size: 11px;
        margin: 2px;
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
        div[style*="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr))"] {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>