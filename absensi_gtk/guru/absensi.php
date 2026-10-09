<?php
// Set timezone ke Asia/Jakarta (WIB)
date_default_timezone_set('Asia/Jakarta');

$page_title = 'Absensi - GTK System';
require_once '../config/database.php';

// Cek login guru
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'guru') {
    header("Location: ../login.php");
    exit();
}

$id_guru = $_SESSION['user_id'];
$tanggal_hari_ini = date('Y-m-d');
$waktu_sekarang = date('H:i:s');

// Ambil data guru
$stmt = $pdo->prepare("SELECT * FROM guru WHERE id_guru = ?");
$stmt->execute([$id_guru]);
$guru = $stmt->fetch();

// Ambil pengaturan GPS
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$radius_gps = $settings['radius_gps'] ?? 100;
$enable_fake_detection = $settings['enable_fake_detection'] ?? 1;
$max_accuracy = $settings['max_accuracy'] ?? 50;
$sekolah_lat = $settings['sekolah_lat'] ?? '';
$sekolah_lng = $settings['sekolah_lng'] ?? '';
$api_wa_url = $settings['api_wa_url'] ?? '';
$api_wa_key = $settings['api_wa_key'] ?? '';
$wa_notification_enabled = $settings['wa_notification_enabled'] ?? 1;

// Ambil jam kerja
$stmt = $pdo->query("SELECT * FROM jam_kerja ORDER BY jam_ke");
$jam_kerja = $stmt->fetchAll();

// Cek jam absen saat ini
$jam_sekarang = null;
$bisa_absen = false;
$pesan_jam = '';
$jam_ke_aktif = null;
$jam_mulai_aktif = '';
$jam_selesai_aktif = '';

foreach ($jam_kerja as $jam) {
    if ($waktu_sekarang >= $jam['jam_mulai'] && $waktu_sekarang <= $jam['jam_selesai']) {
        $jam_sekarang = $jam;
        $jam_ke_aktif = $jam['jam_ke'];
        $jam_mulai_aktif = $jam['jam_mulai'];
        $jam_selesai_aktif = $jam['jam_selesai'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM absensi WHERE id_guru = ? AND tanggal = ? AND jam_ke = ?");
        $stmt->execute([$id_guru, $tanggal_hari_ini, $jam['jam_ke']]);
        $sudah_absen = $stmt->fetchColumn();
        
        if (!$sudah_absen) {
            $bisa_absen = true;
            $pesan_jam = "Jam ke-" . $jam['jam_ke'] . " (" . substr($jam['jam_mulai'], 0, 5) . " - " . substr($jam['jam_selesai'], 0, 5) . ")";
        } else {
            $pesan_jam = "Sudah absen Jam ke-" . $jam['jam_ke'];
        }
        break;
    }
}

if (!$jam_sekarang) {
    $pesan_jam = "Tidak ada jam pelajaran";
}

// Fungsi hitung jarak
function hitungJarak($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000;
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

// Proses absen
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['absen'])) {
    $foto_base64 = $_POST['foto_base64'];
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $accuracy = $_POST['accuracy'];
    $jam_ke = $_POST['jam_ke'];
    
    $error_validation = '';
    $jarak = 0;
    
    // Validasi radius
    if (empty($error_validation) && !empty($sekolah_lat) && !empty($sekolah_lng) && $radius_gps > 0) {
        $jarak = hitungJarak($lat, $lng, $sekolah_lat, $sekolah_lng);
        if ($jarak > $radius_gps) {
            $error_validation = "Di luar radius sekolah! Jarak: " . round($jarak) . "m (max {$radius_gps}m)";
        }
    }
    
    if (empty($error_validation)) {
        $foto_data = explode(',', $foto_base64);
        if (count($foto_data) > 1) {
            $foto_decoded = base64_decode($foto_data[1]);
            $foto_nama = 'selfie_' . date('Ymd_His') . '_' . uniqid() . '.jpg';
            $upload_dir = '../assets/uploads/selfie_absensi/';
            
            if (!file_exists($upload_dir)) mkdir($upload_dir, 0777, true);
            
            if (file_put_contents($upload_dir . $foto_nama, $foto_decoded)) {
                $stmt = $pdo->prepare("INSERT INTO absensi (id_guru, tanggal, jam_ke, waktu_absen, foto_selfie, lokasi_gps, status) VALUES (?, ?, ?, NOW(), ?, ?, 'hadir')");
                if ($stmt->execute([$id_guru, $tanggal_hari_ini, $jam_ke, $foto_nama, $lat . ',' . $lng])) {
                    $_SESSION['toast'] = ['type' => 'success', 'message' => "✓ Absen berhasil! Jam ke-$jam_ke", 'jarak' => round($jarak), 'nama' => $guru['nama']];
                    header("Location: absensi.php");
                    exit();
                }
            }
        }
    }
    
    $_SESSION['toast'] = ['type' => 'error', 'message' => $error_validation ?: 'Gagal absen!'];
    header("Location: absensi.php");
    exit();
}

// Kirim notifikasi WA
function sendWANotification($phone, $message) {
    global $api_wa_url, $api_wa_key;
    if (empty($api_wa_url) || empty($api_wa_key)) return false;
    
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (substr($phone, 0, 1) === "0") $phone = "+62" . substr($phone, 1);
    elseif (substr($phone, 0, 2) !== "62") $phone = "+62" . $phone;
    elseif (substr($phone, 0, 3) !== "+62") $phone = "+" . $phone;
    
    $data = ['phone' => $phone, 'message' => $message];
    $ch = curl_init($api_wa_url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'X-Secret-Key: ' . $api_wa_key]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Riwayat absensi hari ini
$stmt = $pdo->prepare("
    SELECT a.*, j.jam_mulai, j.jam_selesai 
    FROM absensi a
    JOIN jam_kerja j ON a.jam_ke = j.jam_ke
    WHERE a.id_guru = ? AND a.tanggal = ?
    ORDER BY a.jam_ke ASC
");
$stmt->execute([$id_guru, $tanggal_hari_ini]);
$riwayat_hari_ini = $stmt->fetchAll();

// Kirim WA jika ada toast sukses
$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
if ($toast && $toast['type'] == 'success' && $wa_notification_enabled && !empty($guru['no_hp'])) {
    $wa_message = "📚 *NOTIFIKASI ABSENSI* 📚\n\n";
    $wa_message .= "Nama: " . $guru['nama'] . "\n";
    $wa_message .= "Jam: " . $jam_ke_aktif . " (" . substr($jam_mulai_aktif, 0, 5) . " - " . substr($jam_selesai_aktif, 0, 5) . ")\n";
    $wa_message .= "Tanggal: " . date('d/m/Y') . "\n";
    $wa_message .= "Waktu: " . date('H:i:s') . "\n";
    $wa_message .= "Status: ✅ HADIR\n";
    // Hapus detail meter, cukup "Dalam radius sekolah"
    $wa_message .= "Lokasi: Dalam radius sekolah\n";
    $wa_message .= "\nTerima kasih telah melakukan absen 🙏";
    
    sendWANotification($guru['no_hp'], $wa_message);
}
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar_guru.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        
        <!-- Info Jam Sekarang -->
        <div class="info-card">
            <div class="info-icon">⏰</div>
            <div>
                <div class="info-title"><?= $bisa_absen ? "Absen " . $pesan_jam : $pesan_jam ?></div>
                <div class="info-time"><?= date('H:i') ?> WIB · <?= date('d/m/Y') ?></div>
            </div>
            <?php if($bisa_absen): ?>
            <div class="info-badge">PERLU ABSEN</div>
            <?php endif; ?>
        </div>
        
        <?php if($bisa_absen): ?>
        <!-- 2 Kolom Layout -->
        <div class="two-columns">
            
            <!-- KOLOM KIRI: Kamera + Validasi GPS -->
            <div class="col-left">
                <div class="absen-card">
                    <div class="card-header">📸 Absen Sekarang</div>
                    <div class="card-body">
                        <div id="gpsLoading" class="gps-loading">
                            <div class="loading-icon">📍</div>
                            <p>Mengambil Lokasi GPS...</p>
                        </div>
                        
                        <div id="absenForm" style="display: none;">
                            <form method="POST" id="formAbsen">
                                <input type="hidden" name="jam_ke" value="<?= $jam_ke_aktif ?>">
                                <input type="hidden" name="foto_base64" id="foto_base64">
                                <input type="hidden" name="lat" id="lat">
                                <input type="hidden" name="lng" id="lng">
                                <input type="hidden" name="accuracy" id="accuracy">
                                
                                <!-- Camera -->
                                <div class="camera-box">
                                    <div class="camera-preview">
                                        <video id="video" autoplay playsinline></video>
                                        <canvas id="canvas" style="display: none;"></canvas>
                                    </div>
                                    <button type="button" id="captureBtn" class="btn-capture" disabled>📸 Ambil Selfie</button>
                                </div>
                                
                                <!-- Preview Foto -->
                                <div id="previewContainer" class="preview-box" style="display: none;">
                                    <img id="preview" src="">
                                    <button type="button" id="retakeBtn" class="btn-secondary">📷 Ambil Ulang</button>
                                </div>
                                
                                <!-- Status GPS -->
                                <div class="gps-status">
                                    <div class="gps-row">
                                        <span id="gpsIcon">⏳</span>
                                        <span id="gpsText">Mendapatkan lokasi...</span>
                                    </div>
                                    <div id="gpsDetail" class="gps-detail"></div>
                                </div>
                                
                                <!-- Radius Info -->
                                <div class="radius-info">
                                    <div class="radius-label">📍 Radius Sekolah</div>
                                    <div class="radius-value"><?= $radius_gps ?> meter</div>
                                    <div id="radiusStatus" class="radius-status"></div>
                                </div>
                                
                                <div class="warning-note">
                                    ⚠️ Pastikan GPS perangkat Anda aktif dan izinkan akses lokasi.
                                </div>
                                
                                <button type="submit" name="absen" id="submitBtn" class="btn-submit" disabled>✔ Konfirmasi Absen</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- KOLOM KANAN: Riwayat Jam -->
            <div class="col-right">
                <div class="riwayat-card">
                    <div class="card-header">
                        <span>📋 Riwayat Absensi</span>
                        <span class="card-date"><?= date('d/m/Y') ?></span>
                    </div>
                    <div class="riwayat-list">
                        <?php if(count($riwayat_hari_ini) > 0): ?>
                            <?php 
                            $sudah_absen_jam = [];
                            foreach($riwayat_hari_ini as $absen) {
                                $sudah_absen_jam[] = $absen['jam_ke'];
                            }
                            ?>
                            <?php foreach($jam_kerja as $jam): 
                                $is_absen = in_array($jam['jam_ke'], $sudah_absen_jam);
                                $is_current = ($jam['jam_ke'] == $jam_ke_aktif);
                            ?>
                            <div class="riwayat-item <?= $is_current ? 'current' : '' ?> <?= $is_absen ? 'done' : '' ?>">
                                <div class="riwayat-info">
                                    <div class="riwayat-jam">Jam ke-<?= $jam['jam_ke'] ?></div>
                                    <div class="riwayat-waktu"><?= substr($jam['jam_mulai'], 0, 5) ?> - <?= substr($jam['jam_selesai'], 0, 5) ?></div>
                                </div>
                                <div class="riwayat-status">
                                    <?php if($is_absen): ?>
                                        <span class="status-success">✓ Hadir</span>
                                    <?php elseif($is_current): ?>
                                        <span class="status-current">⏳ Belum</span>
                                    <?php else: ?>
                                        <span class="status-pending">○ Menunggu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach($jam_kerja as $jam): 
                                $is_current = ($jam['jam_ke'] == $jam_ke_aktif);
                            ?>
                            <div class="riwayat-item <?= $is_current ? 'current' : '' ?>">
                                <div class="riwayat-info">
                                    <div class="riwayat-jam">Jam ke-<?= $jam['jam_ke'] ?></div>
                                    <div class="riwayat-waktu"><?= substr($jam['jam_mulai'], 0, 5) ?> - <?= substr($jam['jam_selesai'], 0, 5) ?></div>
                                </div>
                                <div class="riwayat-status">
                                    <?php if($is_current): ?>
                                        <span class="status-current">⏳ Belum</span>
                                    <?php else: ?>
                                        <span class="status-pending">○ Menunggu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert-info">
            ℹ️ <?= $pesan_jam ?>
        </div>
        
        <div class="riwayat-card">
            <div class="card-header">
                <span>📋 Riwayat Absensi Hari Ini</span>
                <span class="card-date"><?= date('d/m/Y') ?></span>
            </div>
            <div class="riwayat-list">
                <?php if(count($riwayat_hari_ini) > 0): ?>
                    <?php foreach($riwayat_hari_ini as $absen): ?>
                    <div class="riwayat-item done">
                        <div class="riwayat-info">
                            <div class="riwayat-jam">Jam ke-<?= $absen['jam_ke'] ?></div>
                            <div class="riwayat-waktu"><?= substr($absen['jam_mulai'], 0, 5) ?> - <?= substr($absen['jam_selesai'], 0, 5) ?></div>
                        </div>
                        <div class="riwayat-status">
                            <span class="status-success">✓ Hadir</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">📭 Belum ada absensi hari ini</div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
    </div>
</div>

<!-- Modal Foto -->
<div id="fotoModal" class="modal-foto">
    <div class="modal-foto-content">
        <img id="fotoSelfie" src="">
        <button onclick="closeFotoModal()" class="btn-modal-close">Tutup</button>
    </div>
</div>

<!-- Toast -->
<div id="toastNotification" class="toast-notification">
    <span id="toastMessage"></span>
</div>

<script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const captureBtn = document.getElementById('captureBtn');
    const retakeBtn = document.getElementById('retakeBtn');
    const previewContainer = document.getElementById('previewContainer');
    const preview = document.getElementById('preview');
    const fotoBase64 = document.getElementById('foto_base64');
    const submitBtn = document.getElementById('submitBtn');
    const absenForm = document.getElementById('absenForm');
    const gpsLoading = document.getElementById('gpsLoading');
    const radiusStatus = document.getElementById('radiusStatus');
    
    let stream = null;
    let fotoCaptured = false;
    let gpsValid = false;
    let currentJarak = 0;
    let radiusGps = <?= $radius_gps ?>;
    let sekolahLat = <?= $sekolah_lat ?: 'null' ?>;
    let sekolahLng = <?= $sekolah_lng ?: 'null' ?>;
    
    function showToast(message, isError = false) {
        const toast = document.getElementById('toastNotification');
        toast.innerHTML = message;
        toast.style.background = isError ? '#dc2626' : '#10b981';
        toast.style.display = 'block';
        setTimeout(() => { toast.style.display = 'none'; }, 4000);
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['message'] ?>', <?= $toast['type'] == 'error' ? 'true' : 'false' ?>);
    <?php endif; ?>
    
    function startCamera() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: true })
                .then(s => { 
                    stream = s; 
                    video.srcObject = stream; 
                    captureBtn.disabled = false;
                })
                .catch(err => { showToast('Gagal akses kamera: ' + err.message, true); });
        } else {
            showToast('Browser tidak support kamera', true);
        }
    }
    
    captureBtn.addEventListener('click', () => {
        if (!video.videoWidth) {
            showToast('Kamera belum siap', true);
            return;
        }
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0);
        const fotoData = canvas.toDataURL('image/jpeg', 0.8);
        fotoBase64.value = fotoData;
        preview.src = fotoData;
        previewContainer.style.display = 'block';
        captureBtn.style.display = 'none';
        fotoCaptured = true;
        if (stream) stream.getTracks().forEach(t => t.stop());
        checkReady();
    });
    
    retakeBtn.addEventListener('click', () => {
        previewContainer.style.display = 'none';
        captureBtn.style.display = 'block';
        fotoCaptured = false;
        startCamera();
        checkReady();
    });
    
    function hitungJarak(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    function validateGPS(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        const accuracy = position.coords.accuracy;
        
        document.getElementById('lat').value = lat;
        document.getElementById('lng').value = lng;
        document.getElementById('accuracy').value = accuracy;
        
        const gpsText = document.getElementById('gpsText');
        const gpsIcon = document.getElementById('gpsIcon');
        const gpsDetail = document.getElementById('gpsDetail');
        
        let isValid = true;
        let statusText = '';
        let statusColor = '';
        
        if (sekolahLat && sekolahLng && sekolahLat !== 'null' && sekolahLng !== 'null') {
            currentJarak = hitungJarak(lat, lng, sekolahLat, sekolahLng);
            if (currentJarak > radiusGps) {
                isValid = false;
                gpsText.innerHTML = '❌ Di luar radius sekolah';
                gpsIcon.innerHTML = '❌';
                gpsDetail.innerHTML = `Jarak: ${Math.round(currentJarak)}m dari sekolah (max ${radiusGps}m)`;
                statusText = `❌ Jarak ${Math.round(currentJarak)}m > ${radiusGps}m`;
                statusColor = '#dc2626';
            } else {
                gpsText.innerHTML = '✓ Lokasi valid (dalam radius)';
                gpsIcon.innerHTML = '✅';
                gpsDetail.innerHTML = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)} | Jarak: ${Math.round(currentJarak)}m | Akurasi: ${Math.round(accuracy)}m`;
                statusText = `✅ Dalam radius (${Math.round(currentJarak)}m)`;
                statusColor = '#10b981';
            }
        } else {
            gpsText.innerHTML = '✓ Lokasi valid';
            gpsIcon.innerHTML = '✅';
            gpsDetail.innerHTML = `Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)} | Akurasi: ${Math.round(accuracy)}m`;
            statusText = '✅ Lokasi valid';
            statusColor = '#10b981';
        }
        
        if (radiusStatus) {
            radiusStatus.innerHTML = statusText;
            radiusStatus.style.color = statusColor;
            radiusStatus.style.fontWeight = '500';
        }
        
        gpsValid = isValid;
        gpsLoading.style.display = 'none';
        absenForm.style.display = 'block';
        checkReady();
    }
    
    function getLocation() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(validateGPS, 
                function(error) {
                    let errorMsg = '';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMsg = 'Izin lokasi ditolak. Silakan izinkan akses GPS.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMsg = 'Lokasi tidak tersedia. Pastikan GPS menyala.';
                            break;
                        case error.TIMEOUT:
                            errorMsg = 'Timeout. Coba lagi.';
                            break;
                        default:
                            errorMsg = 'Error tidak diketahui';
                    }
                    document.getElementById('gpsText').innerHTML = '❌ ' + errorMsg;
                    gpsValid = false;
                    gpsLoading.style.display = 'none';
                    absenForm.style.display = 'block';
                    if(radiusStatus) radiusStatus.innerHTML = '❌ ' + errorMsg;
                    checkReady();
                    showToast(errorMsg, true);
                }, 
                { enableHighAccuracy: true, timeout: 15000 }
            );
        } else {
            document.getElementById('gpsText').innerHTML = '❌ GPS tidak support';
            gpsValid = false;
            gpsLoading.style.display = 'none';
            absenForm.style.display = 'block';
            checkReady();
        }
    }
    
    function checkReady() {
        submitBtn.disabled = !(fotoCaptured && gpsValid);
    }
    
    function lihatFoto(filename) {
        document.getElementById('fotoSelfie').src = '../assets/uploads/selfie_absensi/' + filename;
        document.getElementById('fotoModal').style.display = 'flex';
    }
    
    function closeFotoModal() {
        document.getElementById('fotoModal').style.display = 'none';
    }
    
    startCamera();
    getLocation();
    
    document.getElementById('fotoModal').addEventListener('click', e => { if(e.target === e.currentTarget) closeFotoModal(); });
</script>

<style>
    .two-columns {
        display: flex;
        gap: 24px;
        flex-wrap: wrap;
    }
    .col-left {
        flex: 1;
        min-width: 280px;
    }
    .col-right {
        flex: 1;
        min-width: 280px;
    }
    
    .info-card {
        background: #f1f5f9;
        border-radius: 16px;
        padding: 14px 18px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
    }
    .info-icon { font-size: 28px; }
    .info-title { font-weight: 600; font-size: 14px; color: #1e293b; }
    .info-time { font-size: 11px; color: #64748b; margin-top: 2px; }
    .info-badge {
        margin-left: auto;
        background: #c41e3a;
        color: white;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 500;
    }
    
    .absen-card, .riwayat-card {
        background: white;
        border-radius: 20px;
        border: 1px solid #e2e8f0;
        overflow: hidden;
        height: 100%;
    }
    .card-header {
        padding: 14px 18px;
        border-bottom: 1px solid #e2e8f0;
        font-weight: 600;
        font-size: 14px;
        background: #fafcff;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .card-date { font-size: 11px; color: #64748b; font-weight: normal; }
    .card-body { padding: 18px; }
    
    .camera-box { margin-bottom: 18px; }
    .camera-preview {
        background: #1a1f36;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 10px;
    }
    .camera-preview video {
        width: 100%;
        height: 220px;
        background: #000;
        object-fit: cover;
    }
    .btn-capture, .btn-secondary, .btn-submit {
        border: none;
        border-radius: 10px;
        padding: 10px 16px;
        font-size: 14px;
        font-weight: 500;
        cursor: pointer;
        width: 100%;
    }
    .btn-capture { background: #c41e3a; color: white; }
    .btn-capture:disabled { opacity: 0.5; cursor: not-allowed; }
    .btn-secondary { background: #e2e8f0; color: #1e293b; }
    .btn-submit { background: #c41e3a; color: white; }
    .btn-submit:disabled { opacity: 0.5; cursor: not-allowed; }
    
    .preview-box { text-align: center; margin-bottom: 18px; }
    .preview-box img { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; margin-bottom: 8px; border: 2px solid #c41e3a; }
    
    .gps-status {
        background: #f8fafc;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 12px;
    }
    .gps-row { display: flex; align-items: center; gap: 8px; font-size: 13px; }
    .gps-detail { font-size: 10px; color: #64748b; margin-top: 4px; }
    
    .radius-info {
        background: #e0f2fe;
        border-radius: 10px;
        padding: 10px 12px;
        margin-bottom: 18px;
        text-align: center;
    }
    .radius-label { font-size: 11px; color: #0369a1; margin-bottom: 4px; }
    .radius-value { font-size: 18px; font-weight: 700; color: #0284c7; }
    .radius-status { font-size: 12px; margin-top: 4px; }
    
    .gps-loading { text-align: center; padding: 30px; }
    .loading-icon { font-size: 40px; margin-bottom: 10px; }
    
    .alert-info {
        background: #dbeafe;
        border-radius: 14px;
        padding: 14px 18px;
        color: #1e40af;
        font-size: 13px;
        text-align: center;
        margin-bottom: 20px;
    }
    
    .riwayat-list { padding: 0; }
    .riwayat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px 18px;
        border-bottom: 1px solid #e8ecf2;
    }
    .riwayat-item.current { background: #fef3c7; }
    .riwayat-item.done { background: #f0fdf4; }
    .riwayat-jam { font-weight: 600; font-size: 14px; }
    .riwayat-waktu { font-size: 10px; color: #64748b; margin-top: 2px; }
    .status-success { background: #d1fae5; color: #065f46; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
    .status-current { background: #fef3c7; color: #92400e; padding: 4px 10px; border-radius: 20px; font-size: 11px; }
    .status-pending { color: #94a3b8; font-size: 11px; }
    
    .empty-state { text-align: center; padding: 30px; color: #94a3b8; font-size: 13px; }
    
    .warning-note {
        background: #fef3c7;
        border-radius: 10px;
        padding: 8px 12px;
        margin-bottom: 16px;
        font-size: 11px;
        color: #92400e;
    }
    
    .modal-foto {
        display: none;
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.85);
        z-index: 1100;
        align-items: center;
        justify-content: center;
    }
    .modal-foto-content {
        background: white;
        border-radius: 16px;
        max-width: 320px;
        width: 90%;
        padding: 16px;
        text-align: center;
    }
    .modal-foto-content img { width: 100%; border-radius: 12px; margin-bottom: 12px; }
    .btn-modal-close {
        background: #64748b;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
    }
    
    .toast-notification {
        position: fixed;
        top: 16px;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        background: #1e293b;
        border-radius: 10px;
        padding: 12px 20px;
        color: white;
        font-size: 13px;
        text-align: center;
        display: none;
        white-space: nowrap;
    }
    
    @media (max-width: 768px) {
        .content-wrapper { padding: 12px; }
        .two-columns { flex-direction: column; gap: 16px; }
        .camera-preview video { height: 200px; }
        .toast-notification { left: 16px; right: 16px; transform: none; white-space: normal; }
        .info-badge { margin-left: 0; }
        .riwayat-item { padding: 10px 14px; }
        .riwayat-jam { font-size: 13px; }
    }
</style>

<?php require_once '../includes/footer.php'; ?>