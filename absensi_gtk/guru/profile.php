<?php
$page_title = 'Profil Saya - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];
$success = '';
$error = '';

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM guru WHERE id_guru = ?");
$stmt->execute([$id_guru]);
$guru = $stmt->fetch();

// Proses update profil
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profil'])) {
    $nama = $_POST['nama'];
    $nip = $_POST['nip'];
    $jabatan = $_POST['jabatan'];
    $no_hp = $_POST['no_hp'];
    
    // Upload foto
    $foto = $guru['foto'];
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $foto_baru = time() . '_' . uniqid() . '.' . $ext;
            $upload_dir = '../assets/uploads/foto_guru/';
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            // Hapus foto lama
            if ($guru['foto'] && file_exists($upload_dir . $guru['foto'])) {
                unlink($upload_dir . $guru['foto']);
            }
            
            move_uploaded_file($_FILES['foto']['tmp_name'], $upload_dir . $foto_baru);
            $foto = $foto_baru;
        }
    }
    
    $stmt = $pdo->prepare("UPDATE guru SET nama = ?, nip = ?, jabatan = ?, no_hp = ?, foto = ? WHERE id_guru = ?");
    if ($stmt->execute([$nama, $nip, $jabatan, $no_hp, $foto, $id_guru])) {
        $_SESSION['nama'] = $nama;
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Profil berhasil diupdate!'];
        header("Location: profile.php");
        exit();
    } else {
        $error = "Gagal mengupdate profil!";
    }
}

// Proses ubah password
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['ubah_password'])) {
    $password_lama = $_POST['password_lama'];
    $password_baru = $_POST['password_baru'];
    $konfirmasi_password = $_POST['konfirmasi_password'];
    
    if (password_verify($password_lama, $guru['password'])) {
        if ($password_baru == $konfirmasi_password) {
            $password_hash = password_hash($password_baru, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE guru SET password = ? WHERE id_guru = ?");
            if ($stmt->execute([$password_hash, $id_guru])) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => 'Password berhasil diubah!'];
                header("Location: profile.php");
                exit();
            } else {
                $error = "Gagal mengubah password!";
            }
        } else {
            $error = "Password baru tidak cocok!";
        }
    } else {
        $error = "Password lama salah!";
    }
}

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <div class="page-header">
            <h1 class="page-title">👤 Profil Saya</h1>
            <p class="page-subtitle">Kelola informasi profil dan ubah password</p>
        </div>
        
        <?php if($error): ?>
        <div class="alert-error">
            <span>⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
        <?php endif; ?>
        
        <div class="profile-grid">
            
            <!-- Kolom Kiri - Informasi Profil -->
            <div class="profile-card">
                <div class="card-header">
                    <span>📋 Informasi Profil</span>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="profile-avatar">
                            <?php if($guru['foto'] && file_exists('../assets/uploads/foto_guru/' . $guru['foto'])): ?>
                                <img src="../assets/uploads/foto_guru/<?= $guru['foto'] ?>" class="avatar-img">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?= strtoupper(substr($guru['nama'], 0, 2)) ?>
                                </div>
                            <?php endif; ?>
                            <div class="avatar-upload">
                                <label for="foto" class="avatar-label">Ganti Foto</label>
                                <input type="file" name="foto" id="foto" class="avatar-input" accept="image/*">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" value="<?= htmlspecialchars($guru['nama']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">NIP</label>
                            <input type="text" name="nip" class="form-control" value="<?= htmlspecialchars($guru['nip']) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Jabatan</label>
                            <input type="text" name="jabatan" class="form-control" value="<?= htmlspecialchars($guru['jabatan']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Nomor HP (WhatsApp)</label>
                            <input type="text" name="no_hp" class="form-control" value="<?= htmlspecialchars($guru['no_hp']) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($guru['username']) ?>" disabled>
                            <small class="form-hint">Username tidak dapat diubah</small>
                        </div>
                        
                        <button type="submit" name="update_profil" class="btn-save">💾 Simpan Perubahan</button>
                    </form>
                </div>
            </div>
            
            <!-- Kolom Kanan - Ubah Password -->
            <div class="password-card">
                <div class="card-header">
                    <span>🔐 Ubah Password</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-group">
                            <label class="form-label">Password Lama</label>
                            <input type="password" name="password_lama" class="form-control" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Password Baru</label>
                            <input type="password" name="password_baru" class="form-control" required>
                            <small class="form-hint">Minimal 6 karakter</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Password Baru</label>
                            <input type="password" name="konfirmasi_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="ubah_password" class="btn-change">🔄 Ganti Password</button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Info Gaji -->
        <div class="info-card">
            <div class="info-header">
                <span>ℹ️</span>
                <span>Informasi Gaji</span>
            </div>
            <div class="info-body">
                <div class="info-row">
                    <div class="info-label">Gaji Per Jam</div>
                    <div class="info-value"><?= $guru['gaji_perjam'] ? 'Rp ' . number_format($guru['gaji_perjam'], 0, ',', '.') : 'Belum diatur' ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gaji Per Hari</div>
                    <div class="info-value"><?= $guru['gaji_perhari'] ? 'Rp ' . number_format($guru['gaji_perhari'], 0, ',', '.') : 'Belum diatur' ?></div>
                </div>
                <div class="info-row">
                    <div class="info-label">Gaji Pokok</div>
                    <div class="info-value"><?= $guru['gaji_pokok'] ? 'Rp ' . number_format($guru['gaji_pokok'], 0, ',', '.') : 'Belum diatur' ?></div>
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
    
    // Preview foto sebelum upload
    document.getElementById('foto').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(event) {
                const avatarImg = document.querySelector('.avatar-img');
                const avatarPlaceholder = document.querySelector('.avatar-placeholder');
                if (avatarImg) {
                    avatarImg.src = event.target.result;
                } else if (avatarPlaceholder) {
                    const newImg = document.createElement('img');
                    newImg.src = event.target.result;
                    newImg.className = 'avatar-img';
                    avatarPlaceholder.parentNode.replaceChild(newImg, avatarPlaceholder);
                }
            }
            reader.readAsDataURL(file);
        }
    });
</script>

<style>
    .profile-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }
    
    .profile-card, .password-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .card-header {
        padding: 16px 20px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 14px;
        background: #fafcff;
    }
    
    .card-body {
        padding: 20px;
    }
    
    .profile-avatar {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 20px;
    }
    
    .avatar-img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #c41e3a;
        margin-bottom: 10px;
    }
    
    .avatar-placeholder {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: #c41e3a;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        font-weight: 600;
        margin-bottom: 10px;
    }
    
    .avatar-upload {
        text-align: center;
    }
    
    .avatar-label {
        display: inline-block;
        background: #e2e8f0;
        padding: 6px 16px;
        border-radius: 20px;
        font-size: 12px;
        cursor: pointer;
        color: #1e293b;
    }
    
    .avatar-input {
        display: none;
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
    
    .form-control:disabled {
        background: #f1f5f9;
        color: #64748b;
    }
    
    .form-hint {
        font-size: 11px;
        color: #64748b;
        display: block;
        margin-top: 4px;
    }
    
    .btn-save, .btn-change {
        width: 100%;
        padding: 10px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
    }
    
    .btn-save {
        background: #c41e3a;
        color: white;
    }
    
    .btn-save:hover {
        background: #a01830;
    }
    
    .btn-change {
        background: #3b82f6;
        color: white;
    }
    
    .btn-change:hover {
        background: #2563eb;
    }
    
    .alert-error {
        background: #fee2e2;
        border-radius: 12px;
        padding: 12px 16px;
        display: flex;
        align-items: center;
        gap: 10px;
        color: #991b1b;
        margin-bottom: 20px;
    }
    
    .info-card {
        background: #f8fafc;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
    }
    
    .info-header {
        padding: 14px 20px;
        background: #f1f5f9;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .info-body {
        padding: 16px 20px;
    }
    
    .info-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid #e2e8f0;
    }
    
    .info-row:last-child {
        border-bottom: none;
    }
    
    .info-label {
        font-size: 13px;
        color: #64748b;
    }
    
    .info-value {
        font-size: 14px;
        font-weight: 600;
        color: #1e293b;
    }
    
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
    
    .toast-close {
        background: none;
        border: none;
        font-size: 18px;
        cursor: pointer;
        color: #94a3b8;
        margin-left: auto;
    }
    
    @media (max-width: 768px) {
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        .profile-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }
        
        .toast-notification {
            top: 10px;
            right: 10px;
            left: 10px;
            min-width: auto;
        }
        
        .card-body {
            padding: 16px;
        }
    }
    
    @media (max-width: 480px) {
        .content-wrapper {
            padding: 12px;
        }
        
        .avatar-img, .avatar-placeholder {
            width: 80px;
            height: 80px;
            font-size: 28px;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>