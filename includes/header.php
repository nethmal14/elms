<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/security_headers.php';
$pdo = getDB();

// Get all settings
$settings = [];
$sStmt = $pdo->query("SELECT * FROM settings");
while ($row = $sStmt->fetch()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$site_name = $settings['site_name'] ?? 'Elms';
$currentPage = basename($_SERVER['PHP_SELF']);

// Get unread notification count if logged in
$unreadCount = 0;
if (isset($_SESSION['user_id'])) {
    $u_id = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$u_id]);
    $unreadCount = $stmt->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="<?= SITE_ROOT ?>css/style.css">
    <script>
    (function(){
      var saved = localStorage.getItem('elms-theme');
      if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    })();
    </script>
</head>
<body>

<div class="layout-container">
    
    <!-- Sidebar Overlay (mobile) -->
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- Sidebar -->
    <aside id="sidebar" class="app-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">E</div>
            <span class="sidebar-brand-name"><?= htmlspecialchars($site_name) ?></span>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Menu</div>
            
            <a href="index.php" class="sidebar-nav-link <?= $currentPage==='index.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span>Home</span>
            </a>
            
            <a href="courses.php" class="sidebar-nav-link <?= $currentPage==='courses.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.168 0.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332 0.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332 0.477-4.5 1.253"></path></svg>
                <span>Courses</span>
            </a>

            <?php if(isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="sidebar-nav-link <?= $currentPage==='dashboard.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                <span>Dashboard</span>
            </a>
            
            <a href="papers.php" class="sidebar-nav-link <?= $currentPage==='papers.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                <span>Papers</span>
            </a>
            
            <a href="rankings.php" class="sidebar-nav-link <?= $currentPage==='rankings.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <span>Rankings</span>
            </a>
            <?php endif; ?>
        </nav>

        <!-- Profile / Logout -->
        <div class="sidebar-footer">
            <?php if(isset($_SESSION['user_id'])): ?>
                <div class="sidebar-user">
                    <div class="sidebar-avatar"><?= strtoupper(substr($_SESSION['username'],0,1)) ?></div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="sidebar-user-role"><?= $_SESSION['role'] ?></div>
                    </div>
                    <a href="logout.php" class="sidebar-logout" title="Logout">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </a>
                </div>
            <?php else: ?>
                <div class="sidebar-login-actions">
                    <a href="login.php" class="sidebar-login-btn outline">Login</a>
                    <a href="register.php" class="sidebar-login-btn filled">Join Now</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-wrapper">
        
        <header class="app-topbar">
            <div class="topbar-left">
                <button id="mobile-toggle" class="mobile-toggle" onclick="toggleSidebar()">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path></svg>
                </button>
                <span class="topbar-title">
                    <?php
                    switch($currentPage) {
                        case 'index.php': echo htmlspecialchars($site_name); break;
                        case 'dashboard.php': echo 'Dashboard'; break;
                        case 'courses.php': echo 'Courses'; break;
                        case 'papers.php': echo 'Papers'; break;
                        case 'rankings.php': echo 'Rankings'; break;
                        case 'notifications.php': echo 'Notifications'; break;
                        default: echo htmlspecialchars($site_name);
                    }
                    ?>
                </span>
            </div>

            <div class="topbar-right">
                <?php if(isset($_SESSION['user_id'])): ?>
                <a href="notifications.php" class="topbar-notif">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    <?php if($unreadCount > 0): ?>
                        <span class="topbar-notif-dot"></span>
                    <?php endif; ?>
                </a>
                
                <?php if($_SESSION['role']==='admin'): ?>
                <a href="admin/index.php" class="topbar-admin-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    <span>Admin</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </header>

        <div class="content-scroll">
            <div class="content-container">