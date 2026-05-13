<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirect_url = $_POST['redirect'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['grade_id'] = $user['grade_id'];

            if ($user['role'] === 'admin') {
                header("Location: admin/index.php");
            } else {
                if ($redirect_url) {
                    $parsed = parse_url($redirect_url);
                    $safe   = (!isset($parsed['scheme']) && !isset($parsed['host']));
                    if ($safe && preg_match('/^[a-zA-Z0-9\/_\-\.?=&]+$/', $redirect_url)) {
                        header("Location: " . $redirect_url);
                    } else {
                        header("Location: dashboard.php");
                    }
                } else {
                    header("Location: dashboard.php");
                }
            }
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <script>
    (function() {
        try {
            var t = localStorage.getItem('elms-theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        } catch(e) {}
    })();
    </script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= htmlspecialchars($settings['site_name'] ?? 'Elms') ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700;800&family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <link rel="stylesheet" href="<?= SITE_ROOT ?>css/auth.css?v=<?= filemtime(__DIR__ . '/css/auth.css') ?>">

</head>
<body>

<div class="auth-page">
    <div class="auth-panel-left">
        <div class="auth-geo">
            <svg viewBox="0 0 100 100" fill="none" stroke="#fff" stroke-width="2"><circle cx="50" cy="50" r="40"/><path d="M10 50l40-40 40 40-40 40z"/></svg>
        </div>
        <div class="auth-panel-content">
            <a href="index.php" class="auth-panel-logo">
                <div class="auth-panel-logo-mark">E</div>
                <div class="auth-panel-logo-name"><?= htmlspecialchars($settings['site_name'] ?? 'Elms') ?></div>
            </a>
            <h2 class="auth-left-heading">Welcome to<br><span>Your Learning Hub</span></h2>
            <p>Access your courses, track your progress, and join our community of lifelong learners.</p>
            <ul class="auth-feature-list">
                <li class="auth-feature-item"><div class="auth-feature-check"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div> Seamless course access</li>
                <li class="auth-feature-item"><div class="auth-feature-check"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div> Real-time progress tracking</li>
            </ul>
        </div>
    </div>
    
    <div class="auth-panel-right">
        <!-- Theme Toggle -->
        <button class="auth-theme-toggle" id="themeToggle" onclick="toggleTheme()" aria-label="Toggle theme">
            <svg class="icon-moon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
            <svg class="icon-sun" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
        </button>
        <script>
        function toggleTheme() {
            var el = document.documentElement;
            var current = el.getAttribute('data-theme');
            var next = current === 'dark' ? 'light' : 'dark';
            el.setAttribute('data-theme', next);
            localStorage.setItem('elms-theme', next);
        }
        </script>

        <div class="auth-form-box">
            <div class="auth-logo mb-6" style="display: inline-flex;">
              <div class="logo-mark">E</div>
              <span class="logo-text"><?= htmlspecialchars($settings['site_name'] ?? 'Elms') ?></span>
            </div>
            
            <h1>Welcome back</h1>
            <p class="auth-subtitle">Please enter your details to sign in.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="icon icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">
                
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Enter your username" autofocus>
                </div>
                
                <div class="form-group mb-8">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Sign in</button>
            </form>

            <div class="text-center mt-8">
                <p class="text-secondary" style="font-size: 0.875rem;">
                    Don't have an account? <a href="register.php" style="font-weight: 600;">Sign up</a>
                </p>
            </div>
            
            <div class="text-center mt-6">
                <a href="index.php" class="btn btn-ghost btn-sm">
                    <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12,19 5,12 12,5"/>
                    </svg>
                    Back to Home
                </a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
