<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    
    $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES ('sekolah_lat', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$lat, $lat]);
    
    $stmt = $pdo->prepare("INSERT INTO pengaturan (setting_key, setting_value) VALUES ('sekolah_lng', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$lng, $lng]);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?>