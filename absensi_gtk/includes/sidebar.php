<?php
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));
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

    /* Sub Menu */
    .nav-parent {
        position: relative;
    }

    .nav-parent .nav-item {
        cursor: pointer;
    }

    .sub-menu {
        margin-left: 34px;
        padding-left: 20px;
        border-left: 1px solid rgba(255,255,255,0.2);
        display: none;
    }

    .sub-menu.show {
        display: block;
    }

    .sub-menu a {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 16px;
        margin: 2px 0;
        color: rgba(255,255,255,0.75);
        text-decoration: none;
        border-radius: 8px;
        font-size: 13px;
    }

    .sub-menu a:hover {
        background: rgba(255,255,255,0.1);
        color: #ffffff;
    }

    .sub-menu a.active {
        background: rgba(255,255,255,0.2);
        color: #ffffff;
    }

    .sub-icon {
        font-size: 14px;
        width: 20px;
    }

    .nav-arrow {
        margin-left: auto;
        font-size: 12px;
        transition: transform 0.2s;
    }

    .nav-parent.open .nav-arrow {
        transform: rotate(90deg);
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

    /* Mobile Toggle */
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
        <?php if($_SESSION['role'] == 'admin'): ?>
            <!-- Dashboard -->
            <a href="dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            
            <!-- Data Guru -->
            <a href="data_guru.php" class="nav-item <?= $current_page == 'data_guru.php' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span class="nav-text">Data Guru</span>
            </a>
            
            <!-- Riwayat Absensi -->
            <a href="riwayat_absensi.php" class="nav-item <?= $current_page == 'riwayat_absensi.php' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Riwayat Absensi</span>
            </a>
            
            <!-- Ajuan dengan SUB MENU -->
            <div class="nav-parent <?= in_array($current_page, ['ajuan_lembur.php', 'ajuan_izin.php', 'ajuan_detail.php']) ? 'open' : '' ?>" id="ajuanParent">
                <div class="nav-item" onclick="toggleAjuanMenu()">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Ajuan</span>
                    <span class="nav-arrow">▶</span>
                </div>
                <div class="sub-menu <?= in_array($current_page, ['ajuan_lembur.php', 'ajuan_izin.php', 'ajuan_detail.php']) ? 'show' : '' ?>" id="ajuanSub">
                    <a href="ajuan_lembur.php" class="<?= $current_page == 'ajuan_lembur.php' ? 'active' : '' ?>">
                        <span class="sub-icon">💪</span> Lembur
                    </a>
                    <a href="ajuan_izin.php" class="<?= $current_page == 'ajuan_izin.php' ? 'active' : '' ?>">
                        <span class="sub-icon">🏠</span> Izin / Sakit / Cuti
                    </a>
                </div>
            </div>
            
            <!-- Gaji -->
            <a href="gaji.php" class="nav-item <?= $current_page == 'gaji.php' ? 'active' : '' ?>">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Gaji</span>
            </a>
            
            <!-- Laporan -->
            <a href="laporan.php" class="nav-item <?= $current_page == 'laporan.php' ? 'active' : '' ?>">
                <span class="nav-icon">📄</span>
                <span class="nav-text">Laporan</span>
            </a>
            
            <!-- Pengaturan dengan SUB MENU -->
            <div class="nav-parent <?= in_array($current_page, ['pengaturan_jam.php', 'pengaturan_gaji.php', 'pengaturan_gps.php', 'pengaturan_wa.php', 'pengaturan_sekolah.php', 'pengaturan_sistem.php']) ? 'open' : '' ?>" id="pengaturanParent">
                <div class="nav-item" onclick="toggleSubMenu()">
                    <span class="nav-icon">⚙️</span>
                    <span class="nav-text">Pengaturan</span>
                    <span class="nav-arrow">▶</span>
                </div>
                <div class="sub-menu <?= in_array($current_page, ['pengaturan_jam.php', 'pengaturan_gaji.php', 'pengaturan_gps.php', 'pengaturan_wa.php', 'pengaturan_sekolah.php', 'pengaturan_sistem.php']) ? 'show' : '' ?>" id="pengaturanSub">
                    <a href="pengaturan_jam.php" class="<?= $current_page == 'pengaturan_jam.php' ? 'active' : '' ?>">
                        <span class="sub-icon">⏰</span> Jam Masuk
                    </a>
                    <a href="pengaturan_gaji.php" class="<?= $current_page == 'pengaturan_gaji.php' ? 'active' : '' ?>">
                        <span class="sub-icon">💰</span> Gaji Guru
                    </a>
                    <a href="pengaturan_gps.php" class="<?= $current_page == 'pengaturan_gps.php' ? 'active' : '' ?>">
                        <span class="sub-icon">📍</span> Radius GPS
                    </a>
                    <a href="pengaturan_wa.php" class="<?= $current_page == 'pengaturan_wa.php' ? 'active' : '' ?>">
                        <span class="sub-icon">💬</span> WhatsApp Gateway
                    </a>
                    <a href="pengaturan_sekolah.php" class="<?= $current_page == 'pengaturan_sekolah.php' ? 'active' : '' ?>">
                        <span class="sub-icon">🏫</span> Profil Sekolah
                    </a>
                    <a href="pengaturan_sistem.php" class="<?= $current_page == 'pengaturan_sistem.php' ? 'active' : '' ?>">
                        <span class="sub-icon">🔧</span> Sistem
                    </a>
                </div>
            </div>
            
            <div class="nav-divider"></div>
            
            <a href="../logout.php" class="nav-logout">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
            
        <?php else: ?>
            <!-- Menu untuk Guru -->
            <a href="../guru/dashboard.php" class="nav-item <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span class="nav-text">Dashboard</span>
            </a>
            <a href="../guru/absensi.php" class="nav-item <?= $current_page == 'absensi.php' ? 'active' : '' ?>">
                <span class="nav-icon">📸</span>
                <span class="nav-text">Absensi</span>
            </a>
            
            <!-- Ajuan dengan SUB MENU untuk Guru -->
            <div class="nav-parent <?= in_array($current_page, ['ajuan_lembur.php', 'ajuan_izin.php']) ? 'open' : '' ?>" id="guruAjuanParent">
                <div class="nav-item" onclick="toggleGuruAjuanMenu()">
                    <span class="nav-icon">📋</span>
                    <span class="nav-text">Ajuan</span>
                    <span class="nav-arrow">▶</span>
                </div>
                <div class="sub-menu <?= in_array($current_page, ['ajuan_lembur.php', 'ajuan_izin.php']) ? 'show' : '' ?>" id="guruAjuanSub">
                    <a href="../guru/ajuan_lembur.php" class="<?= $current_page == 'ajuan_lembur.php' ? 'active' : '' ?>">
                        <span class="sub-icon">💪</span> Ajuan Lembur
                    </a>
                    <a href="../guru/ajuan_izin.php" class="<?= $current_page == 'ajuan_izin.php' ? 'active' : '' ?>">
                        <span class="sub-icon">🏠</span> Ajuan Izin/Sakit/Cuti
                    </a>
                </div>
            </div>
            
            <a href="../guru/gaji_saya.php" class="nav-item <?= $current_page == 'gaji_saya.php' ? 'active' : '' ?>">
                <span class="nav-icon">💰</span>
                <span class="nav-text">Gaji Saya</span>
            </a>
            <a href="../guru/riwayat.php" class="nav-item <?= $current_page == 'riwayat.php' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span class="nav-text">Riwayat</span>
            </a>
            
            <div class="nav-divider"></div>
            
            <a href="../logout.php" class="nav-logout">
                <span class="nav-icon">🚪</span>
                <span class="nav-text">Logout</span>
            </a>
        <?php endif; ?>
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
    
    function toggleSubMenu() {
        const subMenu = document.getElementById('pengaturanSub');
        const parent = document.getElementById('pengaturanParent');
        subMenu.classList.toggle('show');
        parent.classList.toggle('open');
    }
    
    function toggleAjuanMenu() {
        const subMenu = document.getElementById('ajuanSub');
        const parent = document.getElementById('ajuanParent');
        subMenu.classList.toggle('show');
        parent.classList.toggle('open');
    }
    
    function toggleGuruAjuanMenu() {
        const subMenu = document.getElementById('guruAjuanSub');
        const parent = document.getElementById('guruAjuanParent');
        subMenu.classList.toggle('show');
        parent.classList.toggle('open');
    }
    
    // Close sidebar when clicking outside on mobile
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
    
    // Handle resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('mobileOverlay');
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        }
    });
</script>