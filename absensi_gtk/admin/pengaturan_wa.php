<?php
$page_title = 'Pengaturan WhatsApp - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Ambil pengaturan
$settings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$api_wa_url = $settings['api_wa_url'] ?? 'https://api.sidobe.com/wa/v1/send-message';
$api_wa_key = $settings['api_wa_key'] ?? '';
$wa_notification_enabled = $settings['wa_notification_enabled'] ?? '1';

// Proses simpan pengaturan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    $api_wa_url = trim($_POST['api_wa_url']);
    $api_wa_key = trim($_POST['api_wa_key']);
    $wa_notification_enabled = isset($_POST['wa_notification_enabled']) ? 1 : 0;
    
    $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute(['api_wa_url', $api_wa_url, $api_wa_url]);
    $stmt->execute(['api_wa_key', $api_wa_key, $api_wa_key]);
    $stmt->execute(['wa_notification_enabled', $wa_notification_enabled, $wa_notification_enabled]);
    
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Pengaturan WhatsApp berhasil disimpan!'];
    header("Location: pengaturan_wa.php");
    exit();
}

// Proses test WA
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'test') {
    $test_phone = trim($_POST['test_phone']);
    $test_message = trim($_POST['test_message']);
    $api_url = trim($_POST['api_wa_url']);
    $api_key = trim($_POST['api_wa_key']);
    
    if (empty($api_url) || empty($api_key)) {
        $_SESSION['test_result'] = [
            'success' => false,
            'message' => 'API URL dan Secret Key harus diisi terlebih dahulu!'
        ];
    } elseif (empty($test_phone)) {
        $_SESSION['test_result'] = [
            'success' => false,
            'message' => 'Nomor telepon tujuan harus diisi!'
        ];
    } else {
        // Format nomor telepon
        $test_phone = preg_replace('/[^0-9]/', '', $test_phone);
        if (substr($test_phone, 0, 1) === "0") {
            $test_phone = "+62" . substr($test_phone, 1);
        } elseif (substr($test_phone, 0, 2) !== "62") {
            $test_phone = "+62" . $test_phone;
        } elseif (substr($test_phone, 0, 3) !== "+62") {
            $test_phone = "+" . $test_phone;
        }
        
        $data = [
            'phone' => $test_phone,
            'message' => $test_message
        ];
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-Secret-Key: ' . $api_key
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            $_SESSION['test_result'] = [
                'success' => false,
                'message' => 'CURL Error: ' . $curl_error
            ];
        } elseif ($response !== false) {
            $res = json_decode($response, true);
            if ($res && isset($res['is_success']) && $res['is_success']) {
                $_SESSION['test_result'] = [
                    'success' => true,
                    'message' => 'Test WhatsApp berhasil dikirim ke ' . $test_phone . '!'
                ];
            } else {
                $error_info = isset($res['message']) ? $res['message'] : 'Unknown error';
                $_SESSION['test_result'] = [
                    'success' => false,
                    'message' => 'Gagal mengirim pesan: ' . $error_info,
                    'response' => $response
                ];
            }
        } else {
            $_SESSION['test_result'] = [
                'success' => false,
                'message' => 'Gagal mengirim pesan. HTTP Code: ' . $http_code,
                'response' => $response
            ];
        }
    }
    
    header("Location: pengaturan_wa.php");
    exit();
}

// Ambil hasil test dari session
$test_result = isset($_SESSION['test_result']) ? $_SESSION['test_result'] : null;
unset($_SESSION['test_result']);

// Ambil toast dari session
$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">💬 Pengaturan WhatsApp (SidoBe)</h1>
            <p class="page-subtitle">Konfigurasi API WhatsApp untuk notifikasi otomatis</p>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            <!-- Form Konfigurasi API -->
            <div class="card">
                <div class="card-header">
                    <span>🔧 Konfigurasi API WhatsApp</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="save">
                        
                        <div class="form-group">
                            <label class="form-label">Status Notifikasi</label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="wa_notification_enabled" value="1" <?= $wa_notification_enabled ? 'checked' : '' ?>>
                                Aktifkan Notifikasi WhatsApp
                            </label>
                            <small style="font-size: 11px; color: #64748b;">Kirim notifikasi ke guru saat absensi masuk/keluar</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">API URL</label>
                            <input type="text" name="api_wa_url" class="form-control" value="<?= htmlspecialchars($api_wa_url) ?>" required>
                            <small style="font-size: 11px; color: #64748b;">URL API SidoBe: <code>https://api.sidobe.com/wa/v1/send-message</code></small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Secret Key (X-Secret-Key)</label>
                            <input type="text" name="api_wa_key" class="form-control" value="<?= htmlspecialchars($api_wa_key) ?>" placeholder="Masukkan Secret Key dari SidoBe" required>
                            <small style="font-size: 11px; color: #64748b;">Masukkan Secret Key dari akun SidoBe Anda</small>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" style="width: 100%;">💾 Simpan Konfigurasi</button>
                    </form>
                </div>
            </div>
            
            <!-- Test WhatsApp -->
            <div class="card">
                <div class="card-header">
                    <span>📱 Test Kirim Pesan</span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="test">
                        <input type="hidden" name="api_wa_url" id="test_api_url" value="<?= htmlspecialchars($api_wa_url) ?>">
                        <input type="hidden" name="api_wa_key" id="test_api_key" value="<?= htmlspecialchars($api_wa_key) ?>">
                        
                        <div class="form-group">
                            <label class="form-label">Nomor Tujuan</label>
                            <input type="text" name="test_phone" class="form-control" placeholder="Contoh: 628123456789" required>
                            <small style="font-size: 11px; color: #64748b;">Format: 628xxxx (tanpa tanda + atau spasi)</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Pesan Test</label>
                            <textarea name="test_message" class="form-control" rows="4" required>📚 TEST NOTIFIKASI ABSENSI 📚

Halo, ini adalah pesan test dari Sistem Absensi GTK.

✅ Konfigurasi API WhatsApp berhasil!
📅 Tanggal: <?= date('d/m/Y H:i:s') ?>

Terima kasih 🙏</textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-success" style="width: 100%;">📤 Kirim Test WhatsApp</button>
                    </form>
                    
                    <?php if ($test_result): ?>
                    <div class="test-result" style="margin-top: 20px; padding: 15px; border-radius: 12px; background: <?= $test_result['success'] ? '#d1fae5' : '#fee2e2' ?>; color: <?= $test_result['success'] ? '#065f46' : '#991b1b' ?>;">
                        <div style="font-weight: 600; margin-bottom: 8px;">
                            <?= $test_result['success'] ? '✓ Berhasil!' : '⚠️ Gagal!' ?>
                        </div>
                        <p style="font-size: 13px;"><?= htmlspecialchars($test_result['message']) ?></p>
                        <?php if (isset($test_result['response'])): ?>
                        <details style="margin-top: 10px;">
                            <summary style="cursor: pointer; font-size: 12px;">Detail Response</summary>
                            <pre style="background: rgba(0,0,0,0.05); padding: 8px; border-radius: 8px; font-size: 11px; overflow-x: auto; margin-top: 8px;"><?= htmlspecialchars($test_result['response']) ?></pre>
                        </details>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Panduan Koneksi SidoBe -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <span>📖 Panduan Koneksi SidoBe</span>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
                    <div>
                        <h4 style="font-size: 14px; margin-bottom: 12px; color: #c41e3a;">🔗 Informasi API</h4>
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;">
                            <p><strong>Method:</strong> POST</p>
                            <p><strong>URL:</strong> <code>https://api.sidobe.com/wa/v1/send-message</code></p>
                            <p><strong>Header:</strong> <code>Content-Type: application/json</code></p>
                            <p><strong>Header:</strong> <code>X-Secret-Key: YOUR_SECRET_KEY</code></p>
                            <p><strong>Body:</strong> <code>{"phone": "+628123456789", "message": "Pesan"}</code></p>
                        </div>
                    </div>
                    
                    <div>
                        <h4 style="font-size: 14px; margin-bottom: 12px; color: #c41e3a;">📋 Langkah Mendapatkan Secret Key</h4>
                        <div style="background: #f8fafc; border-radius: 12px; padding: 16px;">
                            <ol style="margin-left: 20px; line-height: 1.8;">
                                <li>Daftar akun di <a href="https://sidobe.com" target="_blank">https://sidobe.com</a></li>
                                <li>Verifikasi email dan nomor WhatsApp</li>
                                <li>Login ke dashboard SidoBe</li>
                                <li>Masuk ke menu <strong>API Settings</strong> atau <strong>Developer</strong></li>
                                <li>Copy <strong>Secret Key</strong> yang tersedia</li>
                                <li>Tempelkan Secret Key pada form di atas</li>
                                <li>Klik <strong>Simpan Konfigurasi</strong> lalu <strong>Test Kirim Pesan</strong></li>
                            </ol>
                        </div>
                    </div>
                </div>
                
                <div style="margin-top: 20px; background: #fef3c7; border-radius: 12px; padding: 16px; border-left: 4px solid #f59e0b;">
                    <h4 style="font-size: 13px; margin-bottom: 8px;">💡 Tips</h4>
                    <ul style="margin-left: 20px; font-size: 13px; color: #475569;">
                        <li>Format nomor telepon: <code>628123456789</code> (otomatis diubah menjadi <code>+628123456789</code>)</li>
                        <li>Pastikan nomor tujuan sudah terdaftar di WhatsApp</li>
                        <li>Cek saldo/kredit jika menggunakan API berbayar</li>
                        <li>Notifikasi akan otomatis terkirim saat guru melakukan absensi</li>
                    </ul>
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
    
    // Update hidden inputs when form changes
    const apiUrlInput = document.querySelector('input[name="api_wa_url"]');
    const apiKeyInput = document.querySelector('input[name="api_wa_key"]');
    
    if (apiUrlInput) {
        apiUrlInput.addEventListener('change', function() {
            const testUrl = document.getElementById('test_api_url');
            if (testUrl) testUrl.value = this.value;
        });
    }
    
    if (apiKeyInput) {
        apiKeyInput.addEventListener('change', function() {
            const testKey = document.getElementById('test_api_key');
            if (testKey) testKey.value = this.value;
        });
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
</script>

<style>
    .test-result details summary {
        cursor: pointer;
        font-size: 12px;
    }
    
    .btn-success {
        background: #10b981;
        color: white;
    }
    
    .btn-success:hover {
        background: #059669;
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
    }
    
    @media (max-width: 768px) {
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .card {
            margin-bottom: 16px;
        }
        
        div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
        
        .toast-notification {
            top: 10px;
            right: 10px;
            left: 10px;
            min-width: auto;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>