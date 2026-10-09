<?php
// Set header untuk download file CSV
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="template_guru.csv"');

// Buat file pointer
$output = fopen('php://output', 'w');

// Set delimiter titik koma
fputcsv($output, ['Nama', 'NIP', 'Jabatan', 'No HP', 'Username', 'Password', 'Status'], ';');

// Data contoh
$samples = [
    ['Budi Santoso', '198001012010011001', 'Guru Matematika', '081234567890', 'budi', 'budi123', 'aktif'],
    ['Siti Aminah', '198002022010011002', 'Guru Bahasa Inggris', '081234567891', 'siti', 'siti123', 'aktif'],
    ['Ahmad Wijaya', '198003032010011003', 'Guru IPA', '081234567892', 'ahmad', 'ahmad123', 'aktif'],
    ['Dewi Purnama', '198004042010011004', 'Guru Bahasa Indonesia', '081234567893', 'dewi', 'dewi123', 'aktif'],
    ['Eko Prasetyo', '198005052010011005', 'Guru Olahraga', '081234567894', 'eko', 'eko123', 'aktif'],
];

foreach ($samples as $row) {
    fputcsv($output, $row, ';');
}

fclose($output);
exit();
?>