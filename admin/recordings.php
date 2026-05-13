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

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Class Recordings</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Manage and archive previous class sessions for student review.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2" style="gap: 2.5rem;">

    <!-- Add Form -->
    <div class="card" style="align-self: start; border-radius: 20px; padding: 2.5rem; border: 1px solid var(--border);">
        <h3 style="margin-bottom: 0.5rem; font-size: 1.25rem;">Upload Recording</h3>
        <p style="color: var(--text-3); margin-bottom: 2.5rem; font-size: 0.95rem;">Link a YouTube session to its corresponding academic module.</p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_recording">
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Target Module</label>
                <div style="position: relative;">
                    <select name="subject_id" class="form-control" style="border-radius: 12px; height: 50px; padding-right: 2.5rem;" required>
                        <option value="">Select Target Module...</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' • ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Recording Title</label>
                <input type="text" name="title" class="form-control" style="border-radius: 12px; height: 50px;" placeholder="e.g. Chapter 01 - Historical Foundations" required>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">YouTube URL</label>
                <input type="url" name="youtube_url" class="form-control" style="border-radius: 12px; height: 50px;" placeholder="https://youtube.com/watch?v=..." required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group mb-6">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Custom Thumbnail</label>
                    <input type="file" name="image" class="form-control" style="border-radius: 12px; height: 50px; padding: 0.6rem;" accept="image/*">
                </div>
                <div class="form-group mb-6">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Class PDF Notes</label>
                    <input type="file" name="notes_pdf" class="form-control" style="border-radius: 12px; height: 50px; padding: 0.6rem;" accept=".pdf">
                </div>
            </div>
            
            <button class="btn btn-primary" style="width: 100%; height: 52px; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Initialize Archive</button>
        </form>
    </div>

    <!-- List -->
    <div>
        <div style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--blue-600);"></div>
            <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; color: var(--text);">Archive Repository</h3>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr; gap: 1rem;">
            <?php foreach ($recordings as $r): ?>
                <div class="card" style="padding: 1.25rem; border-radius: 16px; border: 1px solid var(--border); transition: all 0.2s;" onmouseover="this.style.borderColor='var(--blue-200)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.borderColor='var(--border)'; this.style.transform='none'">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div style="display: flex; align-items: center; gap: 1.25rem;">
                            <div style="width: 64px; height: 64px; border-radius: 12px; overflow: hidden; border: 2px solid var(--border); position: relative;">
                                <?php if (!empty($r['image'])): ?>
                                    <img src="../<?= htmlspecialchars($r['image']) ?>" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <img src="https://img.youtube.com/vi/<?= htmlspecialchars($r['youtube_id']) ?>/mqdefault.jpg" alt="YT Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php endif; ?>
                                <div style="position: absolute; inset: 0; background: rgba(0,0,0,0.2); display: flex; align-items: center; justify-content: center;">
                                    <div style="width: 24px; height: 24px; background: rgba(255,255,255,0.9); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <div style="border-left: 6px solid black; border-top: 4px solid transparent; border-bottom: 4px solid transparent; margin-left: 2px;"></div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <div style="color: var(--blue-600); font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;"><?= htmlspecialchars($r['subject_name']) ?></div>
                                <div style="font-weight: 800; font-size: 1.1rem; color: var(--text);"><?= htmlspecialchars($r['title']) ?></div>
                                <div style="color: var(--text-3); font-size: 0.8rem; font-weight: 600; margin-top: 0.25rem;">Video ID: <?= htmlspecialchars($r['youtube_id']) ?></div>
                            </div>
                        </div>
                        <form method="POST" onsubmit="return confirm('Permanently remove this recording?')" style="margin: 0;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <button type="submit" class="btn btn-ghost" style="color: var(--red-600); padding: 0.5rem; border-radius: 10px;">
                                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (empty($recordings)): ?>
                <div style="padding: 5rem 2rem; text-align: center; background: var(--surface-2); border-radius: 20px; border: 1px dashed var(--border);">
                    <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">🎞️</div>
                    <p style="color: var(--text-3); font-weight: 700; margin: 0;">The archive is currently empty.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
