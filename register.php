<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/csrf.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $grade_id = $_POST['grade_id'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $school = $_POST['school'] ?? '';

    if (empty($username) || empty($password) || empty($grade_id) || empty($phone) || empty($school)) {
        $error = "All fields are required.";
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already exists.";
        } else {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            
            // Generate unique 5-digit student ID (PERF-1 fix)
            $student_id = null;
            for ($i = 0; $i < 5; $i++) {
                $new_id = str_pad(random_int(10000, 99999), 5, '0', STR_PAD_LEFT);
                $check_id = $pdo->prepare("SELECT id FROM users WHERE student_id = ?");
                $check_id->execute([$new_id]);
                if (!$check_id->fetch()) {
                    $student_id = $new_id;
                    break;
                }
            }

            if (!$student_id) {
                $error = "Failed to generate student ID. Please try again.";
            } else {
                // Handle Photo Upload (SEC-5, SEC-6)
                $photo_path = null;
                if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    $max_bytes = 5 * 1024 * 1024; // 5MB
                    if ($_FILES['photo']['size'] > $max_bytes) {
                        $error = "File too large. Maximum allowed size is 5MB.";
                    } else {
                        $allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                        $finfo = new finfo(FILEINFO_MIME_TYPE);
                        $mime  = $finfo->file($_FILES['photo']['tmp_name']);
                        if (!in_array($mime, $allowed_mimes)) {
                            $error = "Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.";
                        } else {
                            $ext = match($mime) {
                                'image/jpeg' => 'jpg',
                                'image/png'  => 'png',
                                'image/gif'  => 'gif',
                                'image/webp' => 'webp',
                            };
                            $upload_dir = TenantContext::getUploadDir('profiles/');
                            $filename   = bin2hex(random_bytes(8)) . '.' . $ext;
                            if (move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . '/' . $filename)) {
                                $photo_path = TenantContext::getUploadUrl('profiles/' . $filename);
                            } else {
                                $error = "Failed to save uploaded file.";
                            }
                        }
                    }
                }

                if (!$error) {
                    $insert = $pdo->prepare("INSERT INTO users (username, password, role, grade_id, student_id, photo, phone, school) VALUES (?, ?, 'student', ?, ?, ?, ?, ?)");
                    if ($insert->execute([$username, $hashed, $grade_id, $student_id, $photo_path, $phone, $school])) {
                        $_SESSION['user_id'] = $pdo->lastInsertId();
                        $_SESSION['username'] = $username;
                        $_SESSION['role'] = 'student';
                        $_SESSION['grade_id'] = $grade_id;
                        header("Location: index.php");
                        exit;
                    } else {
                        $error = "Registration failed.";
                    }
                }
            }
        }
    }
}

$stmt = $pdo->query("SELECT * FROM grades ORDER BY name ASC");
$grades = $stmt->fetchAll();

// Get settings for branding
$settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('site_name')");
$site_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
    <title>Register - <?= htmlspecialchars($site_settings['site_name'] ?? 'Elms') ?></title>
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
                <div class="auth-panel-logo-name"><?= htmlspecialchars($site_settings['site_name'] ?? 'Elms') ?></div>
            </a>
            <h2 class="auth-left-heading">Start Your<br><span>Learning Journey</span></h2>
            <p>Join thousands of students and start learning from the best instructors today.</p>
            <ul class="auth-feature-list">
                <li class="auth-feature-item"><div class="auth-feature-check"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div> World-class instructors</li>
                <li class="auth-feature-item"><div class="auth-feature-check"><svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg></div> Interactive materials</li>
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
              <span class="logo-text"><?= htmlspecialchars($site_settings['site_name'] ?? 'Elms') ?></span>
            </div>
            
            <h1>Create Account</h1>
            <p class="auth-subtitle">Register as a student to get started.</p>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <svg class="icon icon-md" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                      <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                
                <div class="grid grid-cols-2">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Pick a username">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                </div>

                <div class="form-group">
                    <label>Select Your Grade</label>
                    <select name="grade_id" required>
                        <option value="">-- Choose Grade --</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone" placeholder="e.g. 0771234567" required>
                </div>

                <div class="form-group">
                    <label>School Name</label>
                    <input type="text" name="school" placeholder="e.g. Royal College" required>
                </div>

                <div class="form-group mb-8">
                    <label>Profile Photo (Optional)</label>
                    <div class="upload-zone" id="upload-zone">
                        <div class="upload-zone-inner">
                            <svg class="icon icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="16,16 12,12 8,16"/><line x1="12" y1="12" x2="12" y2="21"/><path d="M20.39 18.39A5 5 0 0018 9h-1.26A8 8 0 103 16.3"/>
                            </svg>
                            <p style="font-weight:500;color:var(--text-primary);margin:0.75rem 0 0.25rem;">
                              Drop your photo here
                            </p>
                            <p style="font-size:0.82rem;margin:0;">or <span style="color:var(--blue-500);font-weight:500;">browse files</span></p>
                            <p style="font-size:0.75rem;color:var(--text-tertiary);margin-top:0.5rem;">JPG, PNG or GIF · max 5 MB</p>
                            <input type="file" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
                        </div>
                    </div>
                    <p id="upload-filename" style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.5rem;"></p>
                </div>

                <button type="submit" class="btn btn-primary btn-lg btn-block">Create Account</button>
            </form>

            <div class="text-center mt-8">
                <p class="text-secondary" style="font-size: 0.875rem;">
                    Already have an account? <a href="login.php" style="font-weight: 600;">Sign in</a>
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

<script>
var zone = document.getElementById('upload-zone');
var fileInput = zone ? zone.querySelector('input[type=file]') : null;
var fname = document.getElementById('upload-filename');
if (fileInput) {
  fileInput.addEventListener('change', function() {
    if (this.files[0] && fname) fname.textContent = '✓ ' + this.files[0].name;
  });
  zone.addEventListener('dragover', function(e) { e.preventDefault(); zone.classList.add('drag-over'); });
  zone.addEventListener('dragleave', function() { zone.classList.remove('drag-over'); });
  zone.addEventListener('drop', function(e) {
    e.preventDefault();
    zone.classList.remove('drag-over');
    if (e.dataTransfer.files[0]) {
      fileInput.files = e.dataTransfer.files;
      if (fname) fname.textContent = '✓ ' + e.dataTransfer.files[0].name;
    }
  });
}
</script>

</body>
</html>
