<?php
require_once 'config/database.php';

if (isLoggedIn()) {
    if ($_SESSION['role'] == 'admin') {
        redirect('admin/dashboard.php');
    } else {
        redirect('guru/dashboard.php');
    }
}

$error = '';
$success_logout = isset($_GET['logout']) && $_GET['logout'] == 'success';

// Ambil data pengaturan dari database
$stmt = $pdo->query("SELECT setting_key, setting_value FROM pengaturan");
$settings = [];
while ($row = $stmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$nama_sekolah = $settings['nama_sekolah'] ?? 'GTK System';
$logo_sekolah = $settings['logo_sekolah'] ?? '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM guru WHERE username = ? AND status = 'aktif'");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id_guru'];
            $_SESSION['nama'] = $user['nama'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['foto'] = $user['foto'];
            
            if ($user['role'] == 'admin') {
                redirect('admin/dashboard.php');
            } else {
                redirect('guru/dashboard.php');
            }
        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title>Login - <?= htmlspecialchars($nama_sekolah) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            background: #1a1a2e;
            padding: 0;
        }

        /* Background Foto */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url('admin/bk.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            filter: brightness(0.7);
            z-index: 0;
        }

        /* Overlay */
        .overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(220, 38, 38, 0.3) 0%, rgba(255, 255, 255, 0.1) 100%);
            z-index: 1;
        }

        .login-container {
            width: 100%;
            max-width: 100%;
            padding: 20px;
            position: relative;
            z-index: 2;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.98);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 24px;
            padding: 40px 32px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        /* Ornamen merah */
        .red-accent {
            width: 60px;
            height: 4px;
            background: #dc2626;
            margin: 16px auto 0;
            border-radius: 2px;
        }

        .logo-area {
            text-align: center;
            margin-bottom: 32px;
        }

        .logo-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 12px;
            border-radius: 12px;
        }

        .logo-icon {
            font-size: 56px;
            margin-bottom: 12px;
            display: inline-block;
        }

        .logo-title {
            font-size: 26px;
            font-weight: 700;
            color: #1a1f36;
            letter-spacing: -0.5px;
        }

        .logo-title span {
            color: #dc2626;
        }

        .logo-subtitle {
            font-size: 13px;
            color: #6b7280;
            margin-top: 6px;
        }

        .welcome-text {
            font-size: 20px;
            font-weight: 600;
            color: #1a1f36;
            margin-bottom: 6px;
        }

        .instruction-text {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 28px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #374151;
            margin-bottom: 8px;
        }

        .input-wrapper {
            position: relative;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            font-size: 18px;
            color: #dc2626;
        }

        .form-control {
            width: 100%;
            padding: 12px 14px 12px 44px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            border: 1px solid #e8e0e0;
            border-radius: 12px;
            background: #ffffff;
            color: #1a1f36;
            transition: none;
        }

        .form-control:focus {
            outline: none;
            border-color: #dc2626;
        }

        .form-control::placeholder {
            color: #c9bdbd;
        }

        .options-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #4b5563;
            cursor: pointer;
        }

        .checkbox-label input {
            width: 16px;
            height: 16px;
            margin: 0;
            cursor: pointer;
            accent-color: #dc2626;
        }

        .forgot-link {
            font-size: 13px;
            color: #dc2626;
            text-decoration: none;
        }

        .forgot-link:hover {
            text-decoration: underline;
        }

        .btn-login {
            width: 100%;
            padding: 12px;
            background: #dc2626;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            color: #ffffff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-login:hover {
            background: #b91c1c;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #b91c1c;
        }

        .alert-success {
            background: #f0fdf4;
            border: 1px solid #dcfce7;
            color: #166534;
        }

        .footer {
            text-align: center;
            font-size: 12px;
            color: #6b7280;
            margin-top: 24px;
            padding-top: 16px;
            border-top: 1px solid #fee2e2;
        }

        .copyright {
            margin-top: 8px;
            font-size: 11px;
        }

        .copyright a {
            color: #dc2626;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .copyright a:hover {
            text-decoration: underline;
        }

        /* Responsive Mobile - PERBAIKAN */
        @media (max-width: 480px) {
            body {
                padding: 0;
                align-items: center;
            }
            
            .login-container {
                padding: 16px;
                min-height: 100vh;
                align-items: center;
            }
            
            .login-card {
                padding: 28px 20px;
                margin: 0;
                border-radius: 20px;
                width: 100%;
            }
            
            .logo-img, .logo-icon {
                width: 60px;
                height: 60px;
                font-size: 48px;
            }
            
            .logo-title {
                font-size: 22px;
            }
            
            .logo-subtitle {
                font-size: 12px;
            }
            
            .welcome-text {
                font-size: 18px;
            }
            
            .instruction-text {
                font-size: 13px;
                margin-bottom: 24px;
            }
            
            .form-group {
                margin-bottom: 16px;
            }
            
            .form-control {
                padding: 10px 12px 10px 40px;
                font-size: 13px;
            }
            
            .input-icon {
                font-size: 16px;
                left: 12px;
            }
            
            .btn-login {
                padding: 10px;
                font-size: 13px;
            }
            
            .options-row {
                margin-bottom: 24px;
            }
            
            .alert {
                padding: 10px 14px;
                font-size: 12px;
                margin-bottom: 18px;
            }
            
            .footer {
                margin-top: 20px;
                padding-top: 12px;
            }
        }

        @media (min-width: 481px) and (max-width: 768px) {
            .login-card {
                max-width: 420px;
                padding: 36px 28px;
            }
            
            .login-container {
                padding: 20px;
            }
        }
        
        /* Untuk HP dengan layar sangat kecil */
        @media (max-width: 360px) {
            .login-card {
                padding: 24px 16px;
            }
            
            .logo-title {
                font-size: 20px;
            }
            
            .welcome-text {
                font-size: 16px;
            }
            
            .instruction-text {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="overlay"></div>
    
    <div class="login-container">
        <div class="login-card">
            <div class="logo-area">
                <?php if($logo_sekolah && file_exists('assets/uploads/logo/' . $logo_sekolah)): ?>
                    <img src="assets/uploads/logo/<?= $logo_sekolah ?>" alt="Logo" class="logo-img">
                <?php else: ?>
                    <div class="logo-icon">📚</div>
                <?php endif; ?>
                <div class="logo-title">GTK <span>System</span></div>
                <div class="logo-subtitle"><?= htmlspecialchars($nama_sekolah) ?></div>
                <div class="red-accent"></div>
            </div>

            <div class="welcome-text">Selamat Datang</div>
            <div class="instruction-text">Silakan login dengan akun Anda</div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success_logout): ?>
                <div class="alert alert-success">
                    <span>✓</span>
                    <span>Anda berhasil logout</span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label class="form-label">Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" class="form-control" placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon">🔒</span>
                        <input type="password" name="password" class="form-control" placeholder="Masukkan password" required>
                    </div>
                </div>

                <div class="options-row">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Ingat saya
                    </label>
                    <a href="#" class="forgot-link">Lupa password?</a>
                </div>

                <button type="submit" class="btn-login">
                    <span>→</span> Masuk
                </button>
            </form>

            <div class="footer">
                <div>&copy; <?= date('Y') ?> <?= htmlspecialchars($nama_sekolah) ?></div>
                <div class="copyright">
                    <a href="https://shopee.co.id/viralytics_shop" target="_blank">
                        <span>🛒</span> viralytics_shop
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>