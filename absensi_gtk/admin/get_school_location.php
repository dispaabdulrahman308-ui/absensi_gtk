<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan WHERE setting_key IN ('sekolah_lat', 'sekolah_lng')");
$data = [];
while ($row = $stmt->fetch()) {
    $data[$row['setting_key']] = $row['setting_value'];
}

echo json_encode([
    'lat' => $data['sekolah_lat'] ?? '',
    'lng' => $data['sekolah_lng'] ?? ''
]);
?>