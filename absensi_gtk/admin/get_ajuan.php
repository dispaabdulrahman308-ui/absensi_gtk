<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("
        SELECT a.*, g.nama 
        FROM ajuan a 
        JOIN guru g ON a.id_guru = g.id_guru 
        WHERE a.id_ajuan = ?
    ");
    $stmt->execute([$_GET['id']]);
    $ajuan = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($ajuan) {
        $tgl_mulai = new DateTime($ajuan['tanggal_mulai']);
        $tgl_selesai = new DateTime($ajuan['tanggal_selesai']);
        $ajuan['durasi'] = $tgl_mulai->diff($tgl_selesai)->days + 1;
        $ajuan['created_at'] = date('d/m/Y H:i', strtotime($ajuan['created_at']));
        echo json_encode($ajuan);
    } else {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>