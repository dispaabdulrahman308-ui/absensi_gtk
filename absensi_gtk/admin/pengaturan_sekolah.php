<?php
$page_title = 'Profil Sekolah - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

$success = '';
$error = '';

// Ambil data pengaturan
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$nama_sekolah = $settings['nama_sekolah'] ?? 'Sekolah GTK';
$npsn = $settings['npsn'] ?? '';
$alamat = $settings['alamat_sekolah'] ?? '';
$kota = $settings['kota'] ?? '';
$provinsi = $settings['provinsi'] ?? '';
$kode_pos = $settings['kode_pos'] ?? '';
$telepon = $settings['telepon_sekolah'] ?? '';
$email = $settings['email_sekolah'] ?? '';
$website = $settings['website'] ?? '';
$kepala_sekolah = $settings['kepala_sekolah'] ?? '';
$nip_kepsek = $settings['nip_kepsek'] ?? '';
$logo = $settings['logo_sekolah'] ?? '';

// Folder upload logo
$upload_dir = '../assets/uploads/logo/';
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Proses simpan
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $nama_sekolah = $_POST['nama_sekolah'];
    $npsn = $_POST['npsn'];
    $alamat = $_POST['alamat'];
    $kota = $_POST['kota'];
    $provinsi = $_POST['provinsi'];
    $kode_pos = $_POST['kode_pos'];
    $telepon = $_POST['telepon'];
    $email = $_POST['email'];
    $website = $_POST['website'];
    $kepala_sekolah = $_POST['kepala_sekolah'];
    $nip_kepsek = $_POST['nip_kepsek'];
    
    $data = [
        'nama_sekolah' => $nama_sekolah,
        'npsn' => $npsn,
        'alamat_sekolah' => $alamat,
        'kota' => $kota,
        'provinsi' => $provinsi,
        'kode_pos' => $kode_pos,
        'telepon_sekolah' => $telepon,
        'email_sekolah' => $email,
        'website' => $website,
        'kepala_sekolah' => $kepala_sekolah,
        'nip_kepsek' => $nip_kepsek
    ];
    
    foreach ($data as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    // Upload logo
    if (isset($_FILES['logo']) && $_FILES['logo']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            if ($logo && file_exists($upload_dir . $logo)) {
                unlink($upload_dir . $logo);
            }
            
            $logo_baru = 'logo_' . time() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], $upload_dir . $logo_baru);
            
            $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES ('logo_sekolah', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$logo_baru, $logo_baru]);
            $logo = $logo_baru;
        }
    }
    
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Profil sekolah berhasil disimpan!'];
    header("Location: pengaturan_sekolah.php");
    exit();
}

// Hapus logo
if (isset($_GET['hapus_logo'])) {
    if ($logo && file_exists($upload_dir . $logo)) {
        unlink($upload_dir . $logo);
    }
    $stmt = $pdo->prepare("UPDATE pengaturan SET setting_value = '' WHERE setting_key = 'logo_sekolah'");
    $stmt->execute();
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Logo berhasil dihapus!'];
    header("Location: pengaturan_sekolah.php");
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
            <h1 class="page-title">🏫 Profil Sekolah</h1>
            <p class="page-subtitle">Kelola informasi dan identitas sekolah</p>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                <!-- Kolom Kiri -->
                <div class="card">
                    <div class="card-header">
                        <span>📋 Informasi Dasar</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Nama Sekolah *</label>
                            <input type="text" name="nama_sekolah" class="form-control" value="<?= htmlspecialchars($nama_sekolah) ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">NPSN</label>
                            <input type="text" name="npsn" class="form-control" value="<?= htmlspecialchars($npsn) ?>" placeholder="Nomor Pokok Sekolah Nasional">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Alamat Sekolah</label>
                            <textarea name="alamat" class="form-control" rows="3"><?= htmlspecialchars($alamat) ?></textarea>
                        </div>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                            <div class="form-group">
                                <label class="form-label">Kota</label>
                                <input type="text" name="kota" class="form-control" value="<?= htmlspecialchars($kota) ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Provinsi</label>
                                <input type="text" name="provinsi" class="form-control" value="<?= htmlspecialchars($provinsi) ?>">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kode Pos</label>
                            <input type="text" name="kode_pos" class="form-control" value="<?= htmlspecialchars($kode_pos) ?>">
                        </div>
                    </div>
                </div>
                
                <!-- Kolom Kanan -->
                <div class="card">
                    <div class="card-header">
                        <span>📞 Kontak & Kepala Sekolah</span>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon</label>
                            <input type="text" name="telepon" class="form-control" value="<?= htmlspecialchars($telepon) ?>" placeholder="Contoh: (021) 1234567">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Email Sekolah</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($email) ?>" placeholder="info@sekolah.sch.id">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Website</label>
                            <input type="text" name="website" class="form-control" value="<?= htmlspecialchars($website) ?>" placeholder="www.sekolah.sch.id">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Kepala Sekolah</label>
                            <input type="text" name="kepala_sekolah" class="form-control" value="<?= htmlspecialchars($kepala_sekolah) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">NIP Kepala Sekolah</label>
                            <input type="text" name="nip_kepsek" class="form-control" value="<?= htmlspecialchars($nip_kepsek) ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Logo Sekolah -->
            <div class="card" style="margin-top: 24px;">
                <div class="card-header">
                    <span>🖼️ Logo Sekolah</span>
                </div>
                <div class="card-body">
                    <div style="display: flex; flex-wrap: wrap; gap: 24px; align-items: flex-start;">
                        <div style="text-align: center;">
                            <?php if($logo && file_exists($upload_dir . $logo)): ?>
                                <img src="../assets/uploads/logo/<?= $logo ?>" alt="Logo Sekolah" style="max-width: 150px; max-height: 150px; border-radius: 12px; border: 1px solid #e2e8f0; padding: 8px;">
                                <div style="margin-top: 10px;">
                                    <a href="?hapus_logo=1" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;" onclick="return confirm('Yakin ingin menghapus logo?')">🗑️ Hapus Logo</a>
                                </div>
                            <?php else: ?>
                                <div style="width: 150px; height: 150px; background: #f1f5f9; border-radius: 12px; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;">
                                    <span style="font-size: 48px;">🏫</span>
                                </div>
                                <div style="margin-top: 10px; font-size: 12px; color: #64748b;">Belum ada logo</div>
                            <?php endif; ?>
                        </div>
                        <div style="flex: 1;">
                            <div class="form-group">
                                <label class="form-label">Upload Logo Baru</label>
                                <input type="file" name="logo" class="form-control" accept="image/jpeg,image/png,image/gif">
                                <small style="font-size: 11px; color: #64748b;">Format: JPG, PNG, GIF. Maksimal 2MB</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tombol Simpan -->
            <div style="display: flex; justify-content: flex-end; margin-top: 24px;">
                <button type="submit" name="simpan" class="btn btn-primary" style="padding: 12px 32px;">💾 Simpan Profil Sekolah</button>
            </div>
        </form>
        
        <!-- Preview Data Sekolah -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <span>👁️ Preview Data Sekolah</span>
            </div>
            <div class="card-body">
                <div style="display: flex; flex-wrap: wrap; gap: 20px; align-items: center;">
                    <?php if($logo && file_exists($upload_dir . $logo)): ?>
                        <img src="../assets/uploads/logo/<?= $logo ?>" alt="Logo" style="width: 80px; height: 80px; border-radius: 12px; object-fit: cover;">
                    <?php else: ?>
                        <div style="width: 80px; height: 80px; background: #c41e3a; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <span style="font-size: 32px;">📚</span>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h3 style="font-size: 18px; margin-bottom: 4px;"><?= htmlspecialchars($nama_sekolah) ?></h3>
                        <p style="font-size: 13px; color: #64748b; margin: 0;">
                            <?= htmlspecialchars($alamat) ?><br>
                            <?php if($kota): ?>📌 <?= htmlspecialchars($kota) ?>, <?= htmlspecialchars($provinsi) ?> - <?= htmlspecialchars($kode_pos) ?><?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <hr style="margin: 20px 0; border-color: #e2e8f0;">
                
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <?php if($telepon): ?>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">📞 Telepon</div>
                        <div style="font-size: 13px;"><?= htmlspecialchars($telepon) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($email): ?>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">✉️ Email</div>
                        <div style="font-size: 13px;"><?= htmlspecialchars($email) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($website): ?>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">🌐 Website</div>
                        <div style="font-size: 13px;"><?= htmlspecialchars($website) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if($kepala_sekolah): ?>
                    <div>
                        <div style="font-size: 11px; color: #64748b;">👨‍🏫 Kepala Sekolah</div>
                        <div style="font-size: 13px;"><?= htmlspecialchars($kepala_sekolah) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Pojok Kanan Atas -->
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
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    // Format NPSN hanya angka
    const npsnInput = document.querySelector('input[name="npsn"]');
    if (npsnInput) {
        npsnInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // Format kode pos hanya angka
    const kodePosInput = document.querySelector('input[name="kode_pos"]');
    if (kodePosInput) {
        kodePosInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }
    
    // Format telepon
    const teleponInput = document.querySelector('input[name="telepon"]');
    if (teleponInput) {
        teleponInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9\(\)\-\+]/g, '');
        });
    }
</script>

<style>
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
    }
    
    @media (max-width: 768px) {
        .toast-notification {
            top: 10px;
            right: 10px;
            left: 10px;
            min-width: auto;
        }
        
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
        
        .form-group {
            margin-bottom: 14px;
        }
        
        .card-header {
            padding: 14px 18px;
        }
        
        .card-body {
            padding: 16px;
        }
        
        .page-title {
            font-size: 22px;
        }
        
        .btn-primary {
            width: 100%;
            justify-content: center;
        }
        
        div[style*="display: flex; justify-content: flex-end"] {
            justify-content: center !important;
        }
    }
    
    @media (max-width: 480px) {
        div[style*="grid-template-columns: 1fr 1fr; gap: 12px"] {
            grid-template-columns: 1fr !important;
            gap: 0 !important;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>