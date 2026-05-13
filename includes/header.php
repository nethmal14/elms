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
<html lang="en" data-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site_name) ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="<?= SITE_ROOT ?>css/style.css">

    <!-- Apply saved theme immediately, before paint -->
    <script>
    (function() {
        try {
            var t = localStorage.getItem('elms-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    })();
    </script>
</head>
<body>

<div class="layout-container">

    <!-- Mobile Overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay" onclick="closeSidebar()"></div>

    <!-- ─── Sidebar ─── -->
    <aside id="sidebar" class="app-sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-brand-icon">E</div>
            <span class="sidebar-brand-name"><?= htmlspecialchars($site_name) ?></span>
        </div>

        <nav class="sidebar-nav">
            <div class="sidebar-section-label">Navigation</div>

            <a href="<?= SITE_ROOT ?>index.php" class="sidebar-nav-link <?= $currentPage==='index.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                </svg>
                <span>Home</span>
            </a>

            <a href="<?= SITE_ROOT ?>courses.php" class="sidebar-nav-link <?= $currentPage==='courses.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
                </svg>
                <span>Courses</span>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
            <a href="<?= SITE_ROOT ?>dashboard.php" class="sidebar-nav-link <?= $currentPage==='dashboard.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/>
                </svg>
                <span>Dashboard</span>
            </a>

            <a href="<?= SITE_ROOT ?>papers.php" class="sidebar-nav-link <?= $currentPage==='papers.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/>
                </svg>
                <span>Papers</span>
            </a>

            <a href="<?= SITE_ROOT ?>rankings.php" class="sidebar-nav-link <?= $currentPage==='rankings.php' ? 'active' : '' ?>">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                </svg>
                <span>Rankings</span>
            </a>
            <?php endif; ?>

            <?php endif; ?>
        </nav>

        <!-- Footer: User Info / Auth -->
        <div class="sidebar-footer">
            <?php if (isset($_SESSION['user_id'])): ?>
                <div class="sidebar-user">
                    <div class="sidebar-avatar">
                        <?= strtoupper(substr($_SESSION['username'], 0, 1)) ?>
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['username']) ?></div>
                        <div class="sidebar-user-role"><?= htmlspecialchars($_SESSION['role']) ?></div>
                    </div>
                    <a href="<?= SITE_ROOT ?>logout.php" class="sidebar-logout" title="Sign out">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                    </a>
                </div>
            <?php else: ?>
                <div class="sidebar-login-actions">
                    <a href="<?= SITE_ROOT ?>login.php" class="sidebar-login-btn outline">Sign In</a>
                    <a href="<?= SITE_ROOT ?>register.php" class="sidebar-login-btn filled">Join Free</a>
                </div>
            <?php endif; ?>
        </div>
    </aside>

    <!-- ─── Main Wrapper ─── -->
    <div class="main-wrapper">

        <!-- Top Bar -->
        <header class="app-topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" id="mobile-toggle" onclick="toggleSidebar()" aria-label="Menu">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"/>
                    </svg>
                </button>
                <span class="topbar-title">
                    <?php
                    $titles = [
                        'index.php'        => htmlspecialchars($site_name),
                        'dashboard.php'    => 'Dashboard',
                        'courses.php'      => 'Courses',
                        'papers.php'       => 'Papers',
                        'rankings.php'     => 'Rankings',
                        'notifications.php'=> 'Notifications',
                        'payment.php'      => 'Payment',
                        'subject.php'      => 'Classroom',
                    ];
                    echo $titles[$currentPage] ?? htmlspecialchars($site_name);
                    ?>
                </span>
            </div>

            <div class="topbar-right">

                <!-- Theme Toggle -->
                <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme" title="Toggle dark/light mode">
                    <!-- Moon icon (shown in dark mode) -->
                    <svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                    </svg>
                    <!-- Sun icon (shown in light mode) -->
                    <svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                    </svg>
                </button>

                <?php if (isset($_SESSION['user_id'])): ?>
                <!-- Notifications -->
                <a href="<?= SITE_ROOT ?>notifications.php" class="topbar-notif" title="Notifications">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                    </svg>
                    <?php if ($unreadCount > 0): ?>
                        <span class="topbar-notif-dot"></span>
                    <?php endif; ?>
                </a>

                <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="<?= SITE_ROOT ?>admin/index.php" class="topbar-admin-link">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                    </svg>
                    <span>Admin</span>
                </a>
                <?php endif; ?>
                <?php endif; ?>

            </div>
        </header>

        <!-- Content Scroll Area -->
        <div class="content-scroll">
            <div class="content-container">
