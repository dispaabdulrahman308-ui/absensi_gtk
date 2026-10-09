<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar {
        width: 280px;
        background: #c41e3a;
        position: fixed;
        left: 0;
        top: 0;
        height: 100vh;
        overflow-y: auto;
        z-index: 100;
    }

    .sidebar::-webkit-scrollbar {
        width: 5px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255,255,255,0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255,255,255,0.3);
        border-radius: 5px;
    }

    .sidebar-header {
        padding: 24px;
        border-bottom: 1px solid rgba(255,255,255,0.15);
        background: #c41e3a;
    }

    .sidebar-logo {
        font-size: 20px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 8px;
    }

    .sidebar-user {
        font-size: 13px;
        color: rgba(255,255,255,0.85);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .sidebar-user-avatar {
        width: 32px;
        height: 32px;
        background: rgba(255,255,255,0.2);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        color: #ffffff;
    }

    .sidebar-nav {
        padding: 16px 0;
    }

    .nav-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        margin: 4px 12px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        border-radius: 10px;
    }

    .nav-item:hover {
        background: rgba(255,255,255,0.15);
        color: #ffffff;
    }

    .nav-item.active {
        background: rgba(255,255,255,0.25);
        color: #ffffff;
    }

    .nav-icon {
        width: 22px;
        font-size: 18px;
        text-align: center;
    }

    .nav-text {
        font-size: 14px;
        font-weight: 500;
    }

    .nav-divider {
        height: 1px;
        background: rgba(255,255,255,0.15);
        margin: 16px 20px;
    }

    .nav-logout {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 20px;
        margin: 4px 12px;
        color: rgba(255,255,255,0.85);
        text-decoration: none;
        border-radius: 10px;
    }

    .nav-logout:hover {
        background: rgba(255,255,255,0.15);
        color: #ffffff;
    }

    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 15px;
        left: 15px;
        z-index: 101;
        background: #c41e3a;
        border: none;
        border-radius: 10px;
        padding: 10px 12px;
        cursor: pointer;
        color: white;
        font-size: 18px;
    }

    .mobile-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.5);
        z-index: 99;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.2s;
            width: 280px;
        }
        
        .sidebar.open {
            transform: translateX(0);
        }
        
        .mobile-menu-toggle {
            display: block;
        }
        
        .mobile-overlay.active {
            display: block;
        }
    }
</style>

<button class="mobile-menu-toggle" onclick="toggleSidebar()">☰</button>
<div class="mobile-overlay" id="mobileOverlay" onclick="closeSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">GTK System</div>
        <div class="sidebar-user">
            <div class="sidebar-user-avatar">
                <?= strtoupper(substr($_SESSION['nama'] ?? 'U', 0, 1)) ?>
            </div>
            <span><?= htmlspecialchars($_SESSION['nama'] ?? 'User') ?></span>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
            <span class="nav-icon">📊</span>
            <span class="nav-text">Dashboard</span>
        </a>
        <a href="absensi.php" class="nav-item <?= $current_page == 'absensi.php' ? 'active' : '' ?>">
            <span class="nav-icon">📸</span>
            <span class="nav-text">Absensi</span>
        </a>
        <a href="ajuan.php" class="nav-item <?= $current_page == 'ajuan.php' ? 'active' : '' ?>">
            <span class="nav-icon">📋</span>
            <span class="nav-text">Ajuan</span>
        </a>
        <a href="gaji_saya.php" class="nav-item <?= $current_page == 'gaji_saya.php' ? 'active' : '' ?>">
            <span class="nav-icon">💰</span>
            <span class="nav-text">Gaji Saya</span>
        </a>
        <a href="riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
            <span class="nav-icon">📝</span>
            <span class="nav-text">Riwayat Absensi</span>
        </a>
        
        <!-- Menu Profile -->
        <a href="profile.php" class="nav-item <?= $current_page == 'profile.php' ? 'active' : '' ?>">
            <span class="nav-icon">👤</span>
            <span class="nav-text">Profil Saya</span>
        </a>
        
        <div class="nav-divider"></div>
        
        <a href="../logout.php" class="nav-logout">
            <span class="nav-icon">🚪</span>
            <span class="nav-text">Logout</span>
        </a>
    </nav>
</div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        sidebar.classList.toggle('open');
        overlay.classList.toggle('active');
    }
    
    function closeSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobileOverlay');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
    }
    
    document.addEventListener('click', function(event) {
        const sidebar = document.getElementById('sidebar');
        const toggle = document.querySelector('.mobile-menu-toggle');
        const overlay = document.getElementById('mobileOverlay');
        
        if (window.innerWidth <= 768) {
            if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
    });
    
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    });
</script>