<?php
// Mulai session jika belum dimulai - TIDAK BOLEH ADA OUTPUT SEBELUM INI
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cek login untuk halaman yang membutuhkan autentikasi
$public_pages = ['login.php', 'reset_password.php', 'index.php'];
$current_file = basename($_SERVER['PHP_SELF']);

if (!in_array($current_file, $public_pages) && !isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Set page title default if not set
if (!isset($page_title)) {
    $page_title = 'GTK System';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes, viewport-fit=cover">
    <title><?= htmlspecialchars($page_title) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f0f2f5;
        }

        /* Main Content Layout */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
        }

        .content-wrapper {
            padding: 24px 32px;
        }

        /* Page Header */
        .page-header {
            margin-bottom: 28px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1a1f36;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 14px;
            color: #6c757d;
        }

        /* Card Style */
        .card {
            background: #ffffff;
            border-radius: 20px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card-header {
            padding: 18px 24px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 16px;
            color: #1e293b;
            background: #ffffff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .card-body {
            padding: 24px;
        }

        /* Form Elements */
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

        .form-control {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            color: #1a1f36;
        }

        .form-control:focus {
            outline: none;
            border-color: #c41e3a;
        }

        select.form-control {
            cursor: pointer;
        }

        /* Buttons */
        .btn {
            padding: 12px 24px;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: #c41e3a;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #a01830;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #1e293b;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .btn-danger {
            background: #dc2626;
            color: #ffffff;
        }

        .btn-danger:hover {
            background: #b91c1c;
        }

        .btn-success {
            background: #10b981;
            color: #ffffff;
        }

        .btn-success:hover {
            background: #059669;
        }

        .btn-warning {
            background: #f59e0b;
            color: #ffffff;
        }

        .btn-warning:hover {
            background: #d97706;
        }

        /* Alert Messages */
        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .alert-success {
            background: #d1fae5;
            border: 1px solid #a7f3d0;
            color: #065f46;
        }

        .alert-danger {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .alert-warning {
            background: #fed7aa;
            border: 1px solid #fdba74;
            color: #9a3412;
        }

        .alert-info {
            background: #dbeafe;
            border: 1px solid #bfdbfe;
            color: #1e40af;
        }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            text-align: left;
        }

        .data-table th {
            background: #f8fafc;
            font-weight: 600;
        }

        /* Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        /* Toast Notification */
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

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .content-wrapper {
                padding: 16px;
            }
            
            .page-title {
                font-size: 22px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .card-header {
                padding: 14px 18px;
                font-size: 14px;
            }
            
            .card-body {
                padding: 16px;
            }

            .btn {
                padding: 10px 16px;
                font-size: 13px;
            }

            .data-table th,
            .data-table td {
                padding: 8px 12px;
                font-size: 12px;
            }
            
            .toast-notification {
                top: 10px;
                right: 10px;
                left: 10px;
                min-width: auto;
            }
        }

        @media (max-width: 480px) {
            .content-wrapper {
                padding: 12px;
            }
        }
    </style>
</head>
<body>