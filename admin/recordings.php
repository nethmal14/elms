<?php
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_recording') {
        $subject_id = $_POST['subject_id'];
        $title = $_POST['title'];
        $youtube_url = $_POST['youtube_url'];
        
        // Extract Video ID
        preg_match("/^(?:http(?:s)?:\/\/)?(?:www\.)?(?:m\.)?(?:youtu\.be\/|youtube\.com\/(?:(?:watch)?\?(?:.*&)?v(?:i)?=|(?:embed|v|vi|user)\/))([^\?&\"'>]+)/", $youtube_url, $matches);
        $youtube_id = $matches[1] ?? '';
        
        $image = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = TenantContext::getUploadDir('images/');
            $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['image']['name']));
            if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . '/' . $filename)) {
                $image = TenantContext::getUploadUrl('images/' . $filename);
            }
        }

        $notes_pdf = null;
        if (isset($_FILES['notes_pdf']) && $_FILES['notes_pdf']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = TenantContext::getUploadDir('notes/');
            $filename = 'note_' . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['notes_pdf']['name']));
            if (move_uploaded_file($_FILES['notes_pdf']['tmp_name'], $upload_dir . '/' . $filename)) {
                $notes_pdf = TenantContext::getUploadUrl('notes/' . $filename);
            }
        }
        
        if ($youtube_id) {
            try {
                $stmt = $pdo->prepare("INSERT INTO recordings (subject_id, title, youtube_id, image, notes_pdf) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$subject_id, $title, $youtube_id, $image, $notes_pdf]);
                $success = "Recording added successfully.";
            } catch (Exception $e) {
                $error = "Database Error: " . $e->getMessage();
            }
        } else {
            $error = "Invalid YouTube URL.";
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $pdo->prepare("DELETE FROM recordings WHERE id = ?")->execute([$id]);
            $success = "Recording deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting recording.";
        }
    }
}

// Fetch subjects
$subjects = $pdo->query("SELECT s.*, g.name as grade_name FROM subjects s JOIN grades g ON s.grade_id = g.id ORDER BY g.id, s.id")->fetchAll();
// Fetch recordings
$recordings = $pdo->query("SELECT r.*, s.name as subject_name FROM recordings r JOIN subjects s ON r.subject_id = s.id ORDER BY r.id DESC")->fetchAll();
?>

<div class="flex-between-center mb-10">
    <div>
        <h2 class="text-4xl mb-2">Class Recordings</h2>
        <p class="text-tertiary m-0 text-lg">Manage and archive previous class sessions for student review.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2 gap-10">
    <!-- Add Form -->
    <div class="card p-10 rounded-20 border-default self-start">
        <h3 class="text-xl mb-2">Upload Recording</h3>
        <p class="text-tertiary mb-10 text-sm">Link a YouTube session to its corresponding academic module.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_recording">
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">Target Module</label>
                <div class="relative">
                    <select name="subject_id" class="form-control rounded-12 h-12-5 pr-10" required>
                        <option value="">Select Target Module...</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' • ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">Recording Title</label>
                <input type="text" name="title" class="form-control rounded-12 h-12-5" placeholder="e.g. Chapter 01 - Historical Foundations" required>
            </div>
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">YouTube URL</label>
                <input type="url" name="youtube_url" class="form-control rounded-12 h-12-5" placeholder="https://youtube.com/watch?v=..." required>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group mb-6">
                    <label class="label-accent-muted-sm">Custom Thumbnail</label>
                    <input type="file" name="image" class="form-control rounded-12 h-12-5 p-2.5" accept="image/*">
                </div>
                <div class="form-group mb-6">
                    <label class="label-accent-muted-sm">Class PDF Notes</label>
                    <input type="file" name="notes_pdf" class="form-control rounded-12 h-12-5 p-2.5" accept=".pdf">
                </div>
            </div>
            
            <button class="btn btn-primary btn-init-archive">Initialize Archive</button>
        </form>
    </div>

    <!-- List -->
    <div>
        <div class="mb-6 flex-center-gap-3">
            <div class="dot-indicator"></div>
            <h3 class="card-section-title">Archive Repository</h3>
        </div>
               <div class="grid gap-4">
            <?php foreach ($recordings as $r): ?>
                <div class="card recording-item-card">
                    <div class="flex-between-center">
                        <div class="flex items-center gap-5">
                            <div class="recording-thumb">
                                <?php if (!empty($r['image'])): ?>
                                    <img src="../<?= htmlspecialchars($r['image']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($r['youtube_id']) ?>/mqdefault.jpg" alt="YT Thumbnail" class="w-full h-full object-cover">
                                <?php endif; ?>
                                <div class="recording-play-overlay">
                                    <div class="recording-play-icon">
                                        <div class="recording-play-triangle"></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div class="session-subject-tag"><?= htmlspecialchars($r['subject_name']) ?></div>
                                <div class="session-title-text text-lg-2"><?= htmlspecialchars($r['title']) ?></div>
                                <div class="session-time-text">Video ID: <?= htmlspecialchars($r['youtube_id']) ?></div>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Permanently remove this recording?')" class="m-0">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-ghost font-bold rounded-10 p-2 text-danger">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recordings)): ?>
                <div class="p-20 text-center rounded-20 bg-surface-muted border-dashed">
                    <div class="text-5xl mb-4 opacity-20">🎞️</div>
                    <p class="text-tertiary font-bold m-0">The archive is currently empty.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
