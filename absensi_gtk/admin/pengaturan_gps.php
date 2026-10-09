<?php
$page_title = 'Pengaturan GPS - GTK System';
require_once '../config/database.php';

// Cek admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Proses update pengaturan GPS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['simpan'])) {
    $radius_gps = $_POST['radius_gps'];
    $enable_fake_detection = isset($_POST['enable_fake_detection']) ? 1 : 0;
    $max_accuracy = $_POST['max_accuracy'];
    $max_time_diff = $_POST['max_time_diff'];
    $sekolah_lat = $_POST['sekolah_lat'];
    $sekolah_lng = $_POST['sekolah_lng'];
    $sekolah_alamat = $_POST['sekolah_alamat'];
    
    $settings = [
        'radius_gps' => $radius_gps,
        'enable_fake_detection' => $enable_fake_detection,
        'max_accuracy' => $max_accuracy,
        'max_time_diff' => $max_time_diff,
        'sekolah_lat' => $sekolah_lat,
        'sekolah_lng' => $sekolah_lng,
        'sekolah_alamat' => $sekolah_alamat
    ];
    
    foreach ($settings as $key => $value) {
        $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$key, $value, $value]);
    }
    
    $_SESSION['toast'] = ['type' => 'success', 'message' => 'Pengaturan GPS berhasil disimpan!'];
    header("Location: pengaturan_gps.php");
    exit();
}

// Ambil data pengaturan
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$radius_gps = $settings['radius_gps'] ?? 100;
$enable_fake_detection = $settings['enable_fake_detection'] ?? 1;
$max_accuracy = $settings['max_accuracy'] ?? 50;
$max_time_diff = $settings['max_time_diff'] ?? 5;
$sekolah_lat = $settings['sekolah_lat'] ?? '-6.200000';
$sekolah_lng = $settings['sekolah_lng'] ?? '106.816666';
$sekolah_alamat = $settings['sekolah_alamat'] ?? '';

$toast = isset($_SESSION['toast']) ? $_SESSION['toast'] : null;
unset($_SESSION['toast']);

include '../includes/header.php';
include '../includes/sidebar.php';
?>

<div class="main-content">
    <div class="content-wrapper">
        <div class="page-header">
            <h1 class="page-title">📍 Pengaturan GPS & Lokasi</h1>
            <p class="page-subtitle">Atur radius validasi lokasi dan deteksi lokasi sekolah otomatis</p>
        </div>
        
        <?php if(!isset($_SERVER['HTTPS']) && $_SERVER['HTTP_HOST'] != 'localhost'): ?>
        <div class="alert-warning" style="background: #fef3c7; border-left: 4px solid #f59e0b; margin-bottom: 20px;">
            ⚠️ Gunakan HTTPS untuk akses GPS yang lebih baik. Di localhost, GPS tetap bisa berfungsi.
        </div>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;">
            
            <!-- Form Pengaturan GPS -->
            <div class="card">
                <div class="card-header">
                    <span>⚙️ Konfigurasi GPS</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="gpsForm">
                        <div class="form-group">
                            <label class="form-label">📍 Radius Validasi (meter)</label>
                            <input type="range" name="radius_gps" id="radiusRange" class="form-control" value="<?= $radius_gps ?>" min="10" max="500" step="10" oninput="updateRadiusValue(this.value)">
                            <div class="radius-value" style="text-align: center; margin-top: 5px;">
                                <span id="radiusDisplay" style="background: #c41e3a; color: white; padding: 5px 15px; border-radius: 20px; font-size: 12px;"><?= $radius_gps ?> meter</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">🎯 Deteksi Lokasi Sekolah Otomatis</label>
                            <button type="button" onclick="deteksiLokasiOtomatis()" class="btn btn-primary" style="width: 100%; margin-bottom: 10px;">
                                📍 Deteksi Lokasi Sekarang
                            </button>
                            <div id="deteksiStatus" style="display: none; background: #f0fdf4; border-radius: 8px; padding: 10px; margin-top: 10px;">
                                <span id="deteksiIcon">📍</span> <span id="deteksiText">Mendeteksi...</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">📌 Koordinat Sekolah</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                                <input type="text" name="sekolah_lat" id="sekolah_lat" class="form-control" value="<?= $sekolah_lat ?>" placeholder="Latitude">
                                <input type="text" name="sekolah_lng" id="sekolah_lng" class="form-control" value="<?= $sekolah_lng ?>" placeholder="Longitude">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">🏫 Alamat Sekolah</label>
                            <textarea name="sekolah_alamat" id="sekolah_alamat" class="form-control" rows="2"><?= htmlspecialchars($sekolah_alamat) ?></textarea>
                        </div>
                        
                        <div class="divider"></div>
                        
                        <div class="form-group">
                            <label class="form-label" style="display: flex; align-items: center; gap: 8px;">
                                <input type="checkbox" name="enable_fake_detection" value="1" <?= $enable_fake_detection ? 'checked' : '' ?>>
                                🛡️ Aktifkan Deteksi Fake GPS
                            </label>
                            <small>Mendeteksi jika guru menggunakan aplikasi fake GPS/mock location</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">📡 Akurasi Maksimal (meter)</label>
                            <input type="number" name="max_accuracy" class="form-control" value="<?= $max_accuracy ?>" min="10" max="500">
                            <small>Jika akurasi GPS melebihi batas ini, absensi akan ditolak</small>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">⏱️ Selisih Waktu Maksimal (detik)</label>
                            <input type="number" name="max_time_diff" class="form-control" value="<?= $max_time_diff ?>" min="1" max="60">
                            <small>Maksimal perbedaan waktu antara server dan perangkat</small>
                        </div>
                        
                        <button type="submit" name="simpan" class="btn btn-primary" style="width: 100%;">💾 Simpan Pengaturan</button>
                    </form>
                </div>
            </div>
            
            <!-- Peta Lokasi -->
            <div class="card">
                <div class="card-header">
                    <span>🗺️ Peta Lokasi Sekolah</span>
                </div>
                <div class="card-body">
                    <div id="map" style="height: 400px; border-radius: 12px; margin-bottom: 15px;"></div>
                    <div id="radiusValue" style="text-align: center; margin-top: 10px; font-size: 12px; color: #64748b;">
                        Radius: <?= $radius_gps ?> meter
                    </div>
                    <div style="margin-top: 10px; display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <button type="button" onclick="zoomIn()" class="btn btn-secondary" style="padding: 5px 15px;">➕ Zoom In</button>
                        <button type="button" onclick="zoomOut()" class="btn btn-secondary" style="padding: 5px 15px;">➖ Zoom Out</button>
                        <button type="button" onclick="resetMap()" class="btn btn-secondary" style="padding: 5px 15px;">⟳ Reset</button>
                        <button type="button" onclick="deteksiLokasiOtomatis()" class="btn btn-primary" style="padding: 5px 15px;">📍 Deteksi</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Informasi Keamanan -->
        <div class="card" style="margin-top: 24px;">
            <div class="card-header">
                <span>📋 Informasi Validasi GPS</span>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <div style="background: #f0fdf4; border-radius: 12px; padding: 12px;">
                        <div style="font-weight: 600; color: #15803d;">✅ Radius Validasi</div>
                        <div style="font-size: 24px; font-weight: 700;"><?= $radius_gps ?> m</div>
                        <div style="font-size: 11px;">Jarak dari titik sekolah</div>
                    </div>
                    <div style="background: #fef3c7; border-radius: 12px; padding: 12px;">
                        <div style="font-weight: 600; color: #92400e;">🛡️ Fake GPS Detection</div>
                        <div style="font-size: 14px; font-weight: 700;"><?= $enable_fake_detection ? 'AKTIF' : 'NONAKTIF' ?></div>
                        <div style="font-size: 11px;">Mendeteksi mock location</div>
                    </div>
                    <div style="background: #dbeafe; border-radius: 12px; padding: 12px;">
                        <div style="font-weight: 600; color: #1e40af;">📡 Akurasi Maksimal</div>
                        <div style="font-size: 24px; font-weight: 700;"><?= $max_accuracy ?> m</div>
                        <div style="font-size: 11px;">Toleransi akurasi GPS</div>
                    </div>
                    <div style="background: #fce7f3; border-radius: 12px; padding: 12px;">
                        <div style="font-weight: 600; color: #9d174d;">📍 Status</div>
                        <div style="font-size: 14px; font-weight: 700;" id="statusGPS"><?= $sekolah_lat != '-6.200000' ? 'Tersimpan' : 'Belum disimpan' ?></div>
                        <div style="font-size: 11px;">Klik deteksi untuk ambil lokasi</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification Pojok Kanan Atas -->
<div id="toastNotification" style="position: fixed; top: 20px; right: 20px; z-index: 9999; display: none;">
    <div style="background: white; border-radius: 12px; padding: 14px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px; border-left: 4px solid;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <span id="toastIcon"></span>
            <span id="toastMessage" style="font-size: 14px; flex: 1;"></span>
            <button onclick="closeToast()" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #94a3b8;">&times;</button>
        </div>
    </div>
</div>

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
    let map;
    let marker;
    let circle;
    let currentLat = <?= $sekolah_lat ?>;
    let currentLng = <?= $sekolah_lng ?>;
    let currentRadius = <?= $radius_gps ?>;
    
    function showToast(type, message) {
        const toast = document.getElementById('toastNotification');
        const toastContent = toast.querySelector('div');
        const toastIcon = document.getElementById('toastIcon');
        const toastMessage = document.getElementById('toastMessage');
        
        if (type === 'success') {
            toastContent.style.borderLeftColor = '#10b981';
            toastIcon.innerHTML = '✓';
            toastIcon.style.color = '#10b981';
            toastIcon.style.fontWeight = 'bold';
            toastIcon.style.fontSize = '18px';
        } else {
            toastContent.style.borderLeftColor = '#dc2626';
            toastIcon.innerHTML = '⚠️';
            toastIcon.style.color = '#dc2626';
            toastIcon.style.fontSize = '16px';
        }
        
        toastMessage.innerHTML = message;
        toast.style.display = 'block';
        
        setTimeout(function() {
            toast.style.display = 'none';
        }, 4000);
    }
    
    function closeToast() {
        document.getElementById('toastNotification').style.display = 'none';
    }
    
    <?php if($toast): ?>
    showToast('<?= $toast['type'] ?>', '<?= htmlspecialchars($toast['message']) ?>');
    <?php endif; ?>
    
    // Inisialisasi Peta
    function initMap() {
        map = L.map('map').setView([currentLat, currentLng], 16);
        
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OSM</a> & CartoDB',
            subdomains: 'abcd',
            maxZoom: 19
        }).addTo(map);
        
        marker = L.marker([currentLat, currentLng], {
            draggable: true,
            title: 'Lokasi Sekolah'
        }).addTo(map);
        
        circle = L.circle([currentLat, currentLng], {
            color: '#c41e3a',
            fillColor: '#c41e3a',
            fillOpacity: 0.2,
            radius: currentRadius
        }).addTo(map);
        
        marker.on('dragend', function(e) {
            var pos = marker.getLatLng();
            currentLat = pos.lat;
            currentLng = pos.lng;
            
            document.getElementById('sekolah_lat').value = currentLat.toFixed(6);
            document.getElementById('sekolah_lng').value = currentLng.toFixed(6);
            circle.setLatLng([currentLat, currentLng]);
            reverseGeocode(currentLat, currentLng);
        });
        
        marker.bindPopup('<b>🏫 Lokasi Sekolah</b><br>Radius: ' + currentRadius + ' meter').openPopup();
    }
    
    function reverseGeocode(lat, lng) {
        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`)
            .then(response => response.json())
            .then(data => {
                if (data.display_name) {
                    document.getElementById('sekolah_alamat').value = data.display_name;
                }
            })
            .catch(error => console.log('Error:', error));
    }
    
    function deteksiLokasiOtomatis() {
        const statusDiv = document.getElementById('deteksiStatus');
        const statusText = document.getElementById('deteksiText');
        const statusIcon = document.getElementById('deteksiIcon');
        
        statusDiv.style.display = 'block';
        statusIcon.innerHTML = '⏳';
        statusText.innerHTML = 'Mendeteksi lokasi... Mohon izinkan akses GPS';
        
        // Cek apakah browser mendukung geolocation
        if (!navigator.geolocation) {
            statusIcon.innerHTML = '❌';
            statusText.innerHTML = 'Browser tidak mendukung GPS. Gunakan Chrome, Firefox, atau Edge.';
            showToast('error', 'Browser tidak mendukung GPS');
            return;
        }
        
        // Opsi untuk deteksi GPS
        const options = {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        };
        
        navigator.geolocation.getCurrentPosition(
            function(position) {
                const lat = position.coords.latitude;
                const lng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                
                document.getElementById('sekolah_lat').value = lat.toFixed(6);
                document.getElementById('sekolah_lng').value = lng.toFixed(6);
                
                currentLat = lat;
                currentLng = lng;
                map.setView([lat, lng], 18);
                marker.setLatLng([lat, lng]);
                circle.setLatLng([lat, lng]);
                
                reverseGeocode(lat, lng);
                
                statusIcon.innerHTML = '✅';
                statusText.innerHTML = `Lokasi terdeteksi! Lat: ${lat.toFixed(6)}, Lng: ${lng.toFixed(6)}, Akurasi: ${Math.round(accuracy)} meter`;
                
                setTimeout(() => {
                    statusDiv.style.display = 'none';
                }, 5000);
                
                showToast('success', 'Lokasi berhasil dideteksi!');
            },
            function(error) {
                let msg = '';
                let suggestion = '';
                
                switch(error.code) {
                    case error.PERMISSION_DENIED: 
                        msg = 'Izin lokasi ditolak.'; 
                        suggestion = ' Silakan izinkan akses lokasi di browser Anda.';
                        break;
                    case error.POSITION_UNAVAILABLE: 
                        msg = 'Lokasi tidak tersedia.'; 
                        suggestion = ' Pastikan GPS perangkat Anda menyala.';
                        break;
                    case error.TIMEOUT: 
                        msg = 'Timeout.'; 
                        suggestion = ' Coba lagi atau pastikan koneksi internet stabil.';
                        break;
                    default: 
                        msg = 'Error tidak diketahui';
                        suggestion = ' Coba refresh halaman.';
                }
                
                statusIcon.innerHTML = '❌';
                statusText.innerHTML = msg + suggestion;
                showToast('error', msg + suggestion);
                console.error('Geolocation error:', error);
            },
            options
        );
    }
    
    function updateRadiusValue(val) {
        currentRadius = parseInt(val);
        document.getElementById('radiusDisplay').innerHTML = currentRadius + ' meter';
        document.getElementById('radiusValue').innerHTML = 'Radius: ' + currentRadius + ' meter';
        if (circle) {
            circle.setRadius(currentRadius);
            marker.bindPopup('<b>🏫 Lokasi Sekolah</b><br>Radius: ' + currentRadius + ' meter').openPopup();
        }
    }
    
    function zoomIn() { map.zoomIn(); }
    function zoomOut() { map.zoomOut(); }
    function resetMap() {
        let lat = parseFloat(document.getElementById('sekolah_lat').value);
        let lng = parseFloat(document.getElementById('sekolah_lng').value);
        map.setView([lat, lng], 16);
    }
    
    document.addEventListener('DOMContentLoaded', initMap);
</script>

<style>
    .divider {
        margin: 20px 0;
        border-top: 1px solid #e2e8f0;
    }
    
    input[type="range"] {
        -webkit-appearance: none;
        width: 100%;
        height: 6px;
        background: #e2e8f0;
        border-radius: 5px;
        outline: none;
    }
    
    input[type="range"]::-webkit-slider-thumb {
        -webkit-appearance: none;
        width: 18px;
        height: 18px;
        background: #c41e3a;
        border-radius: 50%;
        cursor: pointer;
    }
    
    .alert-warning {
        padding: 12px 16px;
        border-radius: 12px;
        font-size: 13px;
    }
    
    @media (max-width: 768px) {
        .main-content .content-wrapper {
            padding: 16px;
        }
        
        div[style*="grid-template-columns: 1fr 1fr"] {
            grid-template-columns: 1fr !important;
            gap: 16px !important;
        }
        
        #map {
            height: 300px !important;
        }
    }
</style>

<?php require_once '../includes/footer.php'; ?>