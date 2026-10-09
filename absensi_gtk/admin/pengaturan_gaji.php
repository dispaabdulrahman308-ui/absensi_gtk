<?php
$page_title = 'Pengaturan Gaji Guru - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Proses update gaji - DILAKUKAN SEBELUM OUTPUT APAPUN
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_gaji'])) {
        $id_guru = $_POST['id_guru'];
        $tipe_gaji = $_POST['tipe_gaji'];
        $jumlah = str_replace('.', '', $_POST['jumlah']);
        $jumlah = (float) $jumlah;
        
        if ($tipe_gaji == 'perjam') {
            $stmt = $pdo->prepare("UPDATE guru SET gaji_perjam = ?, gaji_perhari = NULL WHERE id_guru = ?");
            $stmt->execute([$jumlah, $id_guru]);
        } else {
            $stmt = $pdo->prepare("UPDATE guru SET gaji_perhari = ?, gaji_perjam = NULL WHERE id_guru = ?");
            $stmt->execute([$jumlah, $id_guru]);
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Gaji guru berhasil diupdate!'];
        header("Location: pengaturan_gaji.php");
        exit();
        
    } elseif (isset($_POST['update_massal'])) {
        $tipe_massal = $_POST['tipe_massal'];
        $jumlah_massal = str_replace('.', '', $_POST['jumlah_massal']);
        $jumlah_massal = (float) $jumlah_massal;
        
        if ($tipe_massal == 'perjam') {
            $stmt = $pdo->prepare("UPDATE guru SET gaji_perjam = ?, gaji_perhari = NULL WHERE role = 'guru'");
            $stmt->execute([$jumlah_massal]);
        } else {
            $stmt = $pdo->prepare("UPDATE guru SET gaji_perhari = ?, gaji_perjam = NULL WHERE role = 'guru'");
            $stmt->execute([$jumlah_massal]);
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => 'Gaji semua guru berhasil diupdate!'];
        header("Location: pengaturan_gaji.php");
        exit();
    }
}

// Ambil semua guru
$stmt = $pdo->query("SELECT id_guru, nama, nip, jabatan, gaji_perjam, gaji_perhari FROM guru WHERE role = 'guru' ORDER BY nama");
$guru_list = $stmt->fetchAll();

// Hitung ringkasan
$total_gaji_perjam = 0;
$total_gaji_perhari = 0;
$guru_perjam = 0;
$guru_perhari = 0;

foreach($guru_list as $guru) {
    if($guru['gaji_perjam']) {
        $total_gaji_perjam += $guru['gaji_perjam'];
        $guru_perjam++;
    }
    if($guru['gaji_perhari']) {
        $total_gaji_perhari += $guru['gaji_perhari'];
        $guru_perhari++;
    }
}

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">💰 Pengaturan Gaji Guru</h1>
            <p class="page-subtitle">Atur gaji per jam atau per hari untuk setiap guru</p>
        </div>
        
        <!-- Update Massal -->
        <div class="card">
            <div class="card-header">
                <span>⚡ Update Gaji Massal</span>
            </div>
            <div class="card-body">
                <form method="POST" style="display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1; min-width: 150px;">
                        <label class="form-label">Tipe Gaji</label>
                        <select name="tipe_massal" class="form-control" required>
                            <option value="perjam">Per Jam</option>
                            <option value="perhari">Per Hari</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 180px;">
                        <label class="form-label">Jumlah (Rp)</label>
                        <input type="text" name="jumlah_massal" class="form-control" placeholder="Contoh: 50000" required>
                    </div>
                    <div>
                        <button type="submit" name="update_massal" class="btn btn-primary" onclick="return confirm('Update gaji semua guru?')">Update Semua Guru</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Daftar Guru & Pengaturan Gaji -->
        <div class="card">
            <div class="card-header">
                <span>👥 Daftar Guru & Pengaturan Gaji</span>
                <span style="font-size: 12px; color: #64748b;">Total: <?= count($guru_list) ?> guru</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>No</th>
                                <th>Nama Guru</th>
                                <th>NIP</th>
                                <th>Gaji Saat Ini</th>
                                <th>Pengaturan Gaji</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(count($guru_list) > 0): ?>
                                <?php $no = 1; foreach($guru_list as $guru): 
                                    $gaji_perjam = $guru['gaji_perjam'] ? 'Rp ' . number_format($guru['gaji_perjam'], 0, ',', '.') . ' / jam' : '-';
                                    $gaji_perhari = $guru['gaji_perhari'] ? 'Rp ' . number_format($guru['gaji_perhari'], 0, ',', '.') . ' / hari' : '-';
                                    $gaji_current = $guru['gaji_perjam'] ? $gaji_perjam : ($guru['gaji_perhari'] ? $gaji_perhari : 'Belum diatur');
                                ?>
                                <tr>
                                    <td data-label="No"><?= $no++ ?></td>
                                    <td data-label="Nama Guru"><strong><?= htmlspecialchars($guru['nama']) ?></strong></td>
                                    <td data-label="NIP"><?= htmlspecialchars($guru['nip']) ?></td>
                                    <td data-label="Gaji Saat Ini">
                                        <span class="badge-info"><?= $gaji_current ?></span>
                                    </td>
                                    <td data-label="Pengaturan Gaji">
                                        <form method="POST" class="gaji-form">
                                            <input type="hidden" name="id_guru" value="<?= $guru['id_guru'] ?>">
                                            <select name="tipe_gaji" class="form-control form-control-sm">
                                                <option value="perjam" <?= $guru['gaji_perjam'] ? 'selected' : '' ?>>Per Jam</option>
                                                <option value="perhari" <?= $guru['gaji_perhari'] ? 'selected' : '' ?>>Per Hari</option>
                                            </select>
                                            <input type="text" name="jumlah" class="form-control form-control-sm" placeholder="Jumlah" value="<?= number_format($guru['gaji_perjam'] ?? $guru['gaji_perhari'] ?? 0, 0, ',', '.') ?>">
                                            <button type="submit" name="update_gaji" class="btn btn-primary btn-sm">Simpan</button>
                                        </form>
                                    </td>
                                    <td data-label="Aksi">
                                        <a href="data_guru.php?edit=<?= $guru['id_guru'] ?>" class="btn-link">Edit Profil</a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">📭 Belum ada data guru</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Ringkasan -->
        <div class="card">
            <div class="card-header">📊 Ringkasan Gaji</div>
            <div class="card-body">
                <div class="summary-grid">
                    <div class="summary-card green">
                        <div class="summary-title">Guru dengan Gaji Per Jam</div>
                        <div class="summary-value"><?= $guru_perjam ?> orang</div>
                        <div class="summary-desc">Rata-rata: Rp <?= $guru_perjam > 0 ? number_format($total_gaji_perjam / $guru_perjam, 0, ',', '.') : 0 ?> / jam</div>
                    </div>
                    <div class="summary-card orange">
                        <div class="summary-title">Guru dengan Gaji Per Hari</div>
                        <div class="summary-value"><?= $guru_perhari ?> orang</div>
                        <div class="summary-desc">Rata-rata: Rp <?= $guru_perhari > 0 ? number_format($total_gaji_perhari / $guru_perhari, 0, ',', '.') : 0 ?> / hari</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informasi -->
        <div class="card">
            <div class="card-header">ℹ️ Informasi</div>
            <div class="card-body">
                <ul class="info-list">
                    <li>Gaji per jam akan dihitung berdasarkan total jam absen guru dalam sebulan</li>
                    <li>Gaji per hari akan dihitung berdasarkan total hari masuk guru dalam sebulan</li>
                    <li>Rekap gaji akan muncul secara otomatis di menu Gaji</li>
                    <li>Slip gaji dapat diunduh dalam format PDF</li>
                </ul>
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
    
    // Format Rupiah
    document.querySelectorAll('input[name="jumlah"], input[name="jumlah_massal"]').forEach(input => {
        input.addEventListener('input', function(e) {
            let value = this.value.replace(/\./g, '');
            if (!isNaN(value) && value !== '') {
                this.value = new Intl.NumberFormat('id-ID').format(value);
            }
        });
    });
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
</script>

<style>
    .badge-info {
        background: #e0f2fe;
        color: #0369a1;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-block;
    }
    
    .gaji-form {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        align-items: center;
    }
    
    .form-control-sm {
        padding: 6px 10px;
        font-size: 12px;
        width: auto;
        min-width: 100px;
    }
    
    .btn-sm {
        padding: 6px 12px;
        font-size: 12px;
    }
    
    .btn-link {
        color: #c41e3a;
        text-decoration: none;
    }
    
    .btn-link:hover {
        text-decoration: underline;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 16px;
    }
    
    .summary-card {
        border-radius: 12px;
        padding: 16px;
    }
    
    .summary-card.green {
        background: #f0fdf4;
    }
    
    .summary-card.orange {
        background: #fef3c7;
    }
    
    .summary-title {
        font-size: 12px;
        margin-bottom: 4px;
    }
    
    .summary-value {
        font-size: 28px;
        font-weight: 700;
    }
    
    .summary-desc {
        font-size: 11px;
        margin-top: 8px;
    }
    
    .green .summary-title, .green .summary-desc { color: #166534; }
    .green .summary-value { color: #15803d; }
    .orange .summary-title, .orange .summary-desc { color: #92400e; }
    .orange .summary-value { color: #b45309; }
    
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
        .data-table th {
            display: none;
        }
        
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
        
        .gaji-form {
            justify-content: flex-end;
        }
        
        .form-control-sm {
            width: 100%;
        }
        
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>