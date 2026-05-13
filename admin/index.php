<?php
require_once __DIR__ . '/../db.php';

// If not logged in or not admin, redirect to main login
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

$pdo = getDB();

// Mark notifications as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    header("Location: index.php");
    exit;
}

// Handle Unsend Announcement
if (isset($_GET['unsend_announcement'])) {
    $ann_id = $_GET['unsend_announcement'];
    $pdo->prepare("DELETE FROM notifications WHERE announcement_id = ?")->execute([$ann_id]);
    header("Location: index.php?success=Announcement+unsent+successfully");
    exit;
}

require_once __DIR__ . '/includes/header.php';

// Get stats
$stats = [];
$stats['students'] = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
$stats['pending_payments'] = $pdo->query("SELECT COUNT(*) FROM payments WHERE status = 'pending'")->fetchColumn();
$stats['total_classes'] = $pdo->query("SELECT COUNT(*) FROM classes WHERE start_time >= NOW()")->fetchColumn();

// Get notifications
$nStmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$nStmt->execute([$_SESSION['user_id']]);
$notifications = $nStmt->fetchAll();

// Handle Announcement Posting
$success = $_GET['success'] ?? "";
$error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_announcement'])) {
    $target_grade = $_POST['grade_id'] ?: null;
    $target_subject = $_POST['subject_id'] ?: null;
    $message = trim($_POST['message']);
    
    if ($message) {
        $ann_id = uniqid('ann_');
        $query = "SELECT id FROM users WHERE role = 'student'";
        $params = [];
        
        if ($target_grade) {
            $query .= " AND grade_id = ?";
            $params[] = $target_grade;
        }
        
        if ($target_subject) {
            $query .= " AND id IN (SELECT user_id FROM payments WHERE subject_id = ? AND status = 'approved')";
            $params[] = $target_subject;
        }
        
        $students = $pdo->prepare($query);
        $students->execute($params);
        $studentIds = $students->fetchAll(PDO::FETCH_COLUMN);
        
        if ($studentIds) {
            $ins = $pdo->prepare("INSERT INTO notifications (user_id, message, type, announcement_id) VALUES (?, ?, 'announcement', ?)");
            foreach ($studentIds as $sid) {
                $ins->execute([$sid, $message, $ann_id]);
            }
            $success = "Announcement sent to " . count($studentIds) . " students.";
        } else {
            $error = "No students found matching the selected criteria.";
        }
    } else {
        $error = "Message cannot be empty.";
    }
}

// Get Sent Announcements (Unique)
$sentAnnouncements = $pdo->query("SELECT message, announcement_id, created_at, COUNT(*) as recipient_count FROM notifications WHERE type = 'announcement' AND announcement_id IS NOT NULL GROUP BY announcement_id ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get Grades & Subjects for announcement form
$grades = $pdo->query("SELECT * FROM grades ORDER BY name")->fetchAll();
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name")->fetchAll();
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<div class="flex-between mb-8">
    <h2>Dashboard Overview</h2>
    <button onclick="startScanner()" class="btn btn-primary btn-scan">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h18a2 2 0 0 1 2 2z"/><path d="M7 7h10"/><path d="M7 12h10"/><path d="M7 17h10"/></svg>
        Scan Student ID
    </button>
</div>

<div class="grid grid-cols-3 mb-8">
    <div class="card stat-card">
        <div class="stat-card-accent stat-card-accent-blue"></div>
        <h3 class="stat-label">Total Students</h3>
        <div class="flex-between">
            <div class="stat-value"><?= number_format($stats['students']) ?></div>
            <div class="stat-icon stat-icon-blue">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
        </div>
    </div>
    
    <div class="card stat-card">
        <div class="stat-card-accent stat-card-accent-yellow"></div>
        <h3 class="stat-label">Pending Payments</h3>
        <div class="flex-between">
            <div class="stat-value"><?= number_format($stats['pending_payments']) ?></div>
            <div class="stat-icon stat-icon-yellow">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
            </div>
        </div>
        <?php if ($stats['pending_payments'] > 0): ?>
            <a href="payments.php" class="stat-link text-yellow-600">Review Now &rarr;</a>
        <?php endif; ?>
    </div>

    <div class="card stat-card">
        <div class="stat-card-accent stat-card-accent-green"></div>
        <h3 class="stat-label">Upcoming Classes</h3>
        <div class="flex-between">
            <div class="stat-value"><?= number_format($stats['total_classes']) ?></div>
            <div class="stat-icon stat-icon-green">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
        </div>
    </div>
</div>

<?php if ($success): ?>
    <div class="admin-alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="admin-alert-error"><?= $error ?></div>
<?php endif; ?>

<h3 class="mb-4">Quick Actions</h3>
<div class="grid grid-cols-4 mb-8">
    <button onclick="document.getElementById('announcementModal').style.display='flex'" class="quick-action">
        <div class="quick-action-icon bg-blue-600">
            <svg class="w-5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-2h2v2zm0-4h-2V7h2v6z"/></svg>
        </div>
        <span class="quick-action-label">Send Announcement</span>
    </button>
    
    <a href="calendar.php" class="quick-action">
        <div class="quick-action-icon bg-blue-900">
            <svg class="w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
        </div>
        <span class="quick-action-label">Schedule Class</span>
    </a>
</div>

<!-- Sent Announcements History -->
<div class="card glass-panel mb-8">
    <h3 class="mb-4">Recent Announcements</h3>
    <?php if (empty($sentAnnouncements)): ?>
        <p class="text-secondary">No announcements sent yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Message</th>
                        <th class="text-center">Recipients</th>
                        <th>Sent At</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($sentAnnouncements as $ann): ?>
                        <tr>
                            <td class="font-medium truncate-300">
                                <?= htmlspecialchars($ann['message']) ?>
                            </td>
                            <td class="text-center">
                                <span class="recipient-badge">
                                    <?= $ann['recipient_count'] ?> Students
                                </span>
                            </td>
                            <td class="text-tertiary">
                                <?= date('M j, g:i A', strtotime($ann['created_at'])) ?>
                            </td>
                            <td class="text-right">
                                <a href="?unsend_announcement=<?= $ann['announcement_id'] ?>" 
                                   onclick="return confirm('Are you sure you want to unsend this announcement? It will be removed for ALL students.')"
                                   class="action-pill action-pill-danger">
                                   Unsend
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Announcement Modal -->
<div id="announcementModal" class="admin-modal">
    <div class="admin-modal-card">
        <div class="flex-between mb-8">
            <h3 class="m-0">Send Announcement</h3>
            <button onclick="document.getElementById('announcementModal').style.display='none'" class="btn-close">
                <svg class="w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form method="POST">
            <input type="hidden" name="send_announcement" value="1">
            <div class="form-group mb-4">
                <label>Target Grade (Optional)</label>
                <select name="grade_id" class="form-control">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-4">
                <label>Target Subject (Optional)</label>
                <select name="subject_id" class="form-control">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-muted">Only students enrolled in the selected subject will receive this.</small>
            </div>
            <div class="form-group mb-6">
                <label>Message</label>
                <textarea name="message" class="form-control" rows="4" placeholder="Type your announcement here..." required></textarea>
            </div>
            <button class="btn btn-primary btn-block">Broadcast Announcement</button>
        </form>
    </div>
</div>

<div class="card glass-panel">
    <div class="flex-between mb-6">
        <h3 class="m-0">Recent Notifications</h3>
        <?php if (count($notifications) > 0): ?>
            <a href="index.php?mark_read=1" class="btn btn-secondary btn-sm">Mark All as Read</a>
        <?php endif; ?>
    </div>
    
    <?php if (empty($notifications)): ?>
        <p class="text-tertiary">You have no new notifications.</p>
    <?php else: ?>
        <ul class="flex flex-col gap-0 list-none p-0">
            <?php foreach ($notifications as $notif): ?>
                <li class="notif-item">
                    <div class="notif-icon-wrapper">
                        <svg class="w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </div>
                    <div>
                        <div class="font-medium mb-1"><?= htmlspecialchars($notif['message']) ?></div>
                        <div class="text-tertiary text-sm">
                            <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<!-- Student Scanner Modal -->
<div id="scannerModal" class="scanner-modal">
    <div class="scanner-container">
        <div class="card scanner-card">
            <div class="flex-between mb-8">
                <h3 class="m-0 text-white">Scan Student QR</h3>
                <button onclick="stopScanner()" class="btn-close btn-close-scanner">
                    <svg class="w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <div id="reader" class="scanner-reader"></div>
            <p class="text-center text-muted m-6">Position the student's ID QR code within the frame.</p>
            <button onclick="stopScanner()" class="btn-scanner-cancel">
                CANCEL SCANNING
            </button>
        </div>
    </div>
</div>

<div id="studentDetailModal" class="command-center-overlay">
    <div class="detail-modal-container">
        <div class="card detail-modal-content">
            <button onclick="document.getElementById('studentDetailModal').style.display='none'" class="btn-close-scanner absolute-top-right-6">
                <svg class="w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
            
            <div id="studentDetailContent">
                <!-- Loaded via AJAX -->
                <div class="text-center p-16">
                    <div class="spinner"></div>
                    <p class="mt-4 text-tertiary">Fetching student records...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let html5QrCode;

    async function startScanner() {
        document.getElementById('scannerModal').style.display = 'flex';
        html5QrCode = new Html5Qrcode("reader", { 
            experimentalFeatures: { useBarCodeDetectorIfSupported: true },
            rememberLastUsedCamera: true
        });

        const config = { 
            fps: 30, 
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            videoConstraints: {
                facingMode: "environment",
                width: { min: 640, ideal: 1280, max: 1920 },
                height: { min: 480, ideal: 720, max: 1080 },
                frameRate: { ideal: 30, max: 60 }
            }
        };

        try {
            await html5QrCode.start({ facingMode: "environment" }, config, onScanSuccess);
        } catch (err) {
            alert("Unable to access camera. Please ensure permissions are granted.");
            stopScanner();
        }
    }

    function stopScanner() {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                document.getElementById('scannerModal').style.display = 'none';
            }).catch(() => {
                document.getElementById('scannerModal').style.display = 'none';
            });
        } else {
            document.getElementById('scannerModal').style.display = 'none';
        }
    }

    function onScanSuccess(decodedText, decodedResult) {
        stopScanner();
        showStudentDetails(decodedText);
    }

    function showStudentDetails(studentId) {
        document.getElementById('studentDetailModal').style.display = 'flex';
        document.getElementById('studentDetailContent').innerHTML = '<div class="text-center p-16"><p>Fetching student records...</p></div>';
        
        fetch('api/get_student_details.php?student_id=' + studentId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('studentDetailContent').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('studentDetailContent').innerHTML = '<p class="text-danger text-center">Error loading student details.</p>';
            });
    }

    // Close modal on escape key
    document.addEventListener('keydown', function(event) {
        if (event.key === "Escape") {
            document.getElementById('studentDetailModal').style.display = 'none';
        }
    });

    // Close modal on outside click
    window.onclick = function(event) {
        let modal = document.getElementById('studentDetailModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
