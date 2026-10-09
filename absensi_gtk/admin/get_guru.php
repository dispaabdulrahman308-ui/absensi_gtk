<?php
header('Content-Type: application/json');
require_once '../config/database.php';

if (isset($_GET['id'])) {
    $stmt = $pdo->prepare("SELECT id_guru, nama, nip, jabatan, no_hp, username, status FROM guru WHERE id_guru = ?");
    $stmt->execute([$_GET['id']]);
    $guru = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode($guru);
} else {
    echo json_encode([]);
}
?>