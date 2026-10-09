<?php
require_once 'config/database.php';

echo "<h2>Reset Password</h2>";

// Update password admin
$admin_password = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE guru SET password = ? WHERE username = 'admin'");
$stmt->execute([$admin_password]);
echo "Password admin direset ke: admin123<br>";

// Update password guru
$guru_password = password_hash('guru123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE guru SET password = ? WHERE username = 'guru'");
$stmt->execute([$guru_password]);
echo "Password guru direset ke: guru123<br>";

// Cek hasil
$stmt = $pdo->query("SELECT username, password FROM guru WHERE username IN ('admin', 'guru')");
while($row = $stmt->fetch()) {
    echo "<br>Username: " . $row['username'] . "<br>";
    echo "Password hash: " . $row['password'] . "<br>";
    echo "Verify 'admin123': " . (password_verify('admin123', $row['password']) ? 'YES' : 'NO') . "<br>";
}

echo "<br><a href='login.php'>Kembali ke Login</a>";
?>