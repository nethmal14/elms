<?php
require_once __DIR__ . '/includes/header.php';

$pdo = getDB();

// Check if feature is enabled
$isEnabled = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'enable_papers'")->fetchColumn();
if ($isEnabled === '0') {
    header("Location: index.php");
    exit;
}

$success = '';
$error = '';

// Handle potential POST limit error
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $error = "The uploaded file is too large. The server limit is " . ini_get('post_max_size') . ".";
}

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST)) {
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'upload_paper') {
            $subject_id = $_POST['subject_id'];
            $title = $_POST['title'];
            $deadline = $_POST['deadline'];
            $paper_type = $_POST['paper_type'];
            
            $essay_pdf = null;
            $mcq_pdf = null;
            
            if ($paper_type === 'essay' || $paper_type === 'both') {
                if (!isset($_FILES['essay_pdf']) || $_FILES['essay_pdf']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("Essay PDF upload failed or missing. Please ensure the file is a valid PDF and under the size limit.");
                }
                $essay_pdf = 'essay_' . time() . '_' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['essay_pdf']['tmp_name'], TenantContext::getUploadDir('papers/') . '/' . $essay_pdf);
            }
            
            if ($paper_type === 'mcq' || $paper_type === 'both') {
                if (!isset($_FILES['mcq_pdf']) || $_FILES['mcq_pdf']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception("MCQ PDF upload failed or missing. Please ensure the file is a valid PDF and under the size limit.");
                }
                $mcq_pdf = 'mcq_' . time() . '_' . uniqid() . '.pdf';
                move_uploaded_file($_FILES['mcq_pdf']['tmp_name'], TenantContext::getUploadDir('papers/') . '/' . $mcq_pdf);
            }

            $mcq_config = null;
            if ($paper_type === 'mcq' || $paper_type === 'both') {
                $mcq_config = json_encode([
                    'num_questions' => (int)$_POST['num_questions'],
                    'num_options' => (int)$_POST['num_options'],
                    'answers' => $_POST['mcq_answers'] ?? []
                ]);
            }

            $stmt = $pdo->prepare("INSERT INTO papers (subject_id, title, paper_type, essay_pdf_path, mcq_pdf_path, mcq_config, deadline, pdf_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$subject_id, $title, $paper_type, $essay_pdf, $mcq_pdf, $mcq_config, $deadline, $essay_pdf ?: $mcq_pdf]);
            $success = "Paper uploaded successfully.";
        } elseif ($action === 'mark_submission') {
            $submission_id = $_POST['submission_id'];
            $marks = $_POST['marks'];
            
            $marked_filename = null;
            if (isset($_FILES['marked_pdf']) && $_FILES['marked_pdf']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['marked_pdf']['name'], PATHINFO_EXTENSION);
                if (strtolower($ext) !== 'pdf') throw new Exception("Only PDF files are allowed for marked papers.");
                
                $marked_filename = 'marked_' . time() . '_' . uniqid() . '.pdf';
                $upload_dir = TenantContext::getUploadDir('marked/');
                $upload_path = $upload_dir . '/' . $marked_filename;
                move_uploaded_file($_FILES['marked_pdf']['tmp_name'], $upload_path);
            }

            $stmt = $pdo->prepare("UPDATE paper_submissions SET essay_marks = ?, total_marks = (IFNULL(mcq_score, 0) + ?), marked_pdf_path = ?, status = 'marked', essay_status = 'marked' WHERE id = ?");
            $stmt->execute([$marks, $marks, $marked_filename, $submission_id]);
            
            // Notify student
            $submission = $pdo->prepare("SELECT user_id, paper_id FROM paper_submissions WHERE id = ?");
            $submission->execute([$submission_id]);
            $subData = $submission->fetch();
            
            $paper = $pdo->prepare("SELECT title FROM papers WHERE id = ?");
            $paper->execute([$subData['paper_id']]);
            $paperTitle = $paper->fetchColumn();
            
            $msg = "Your paper '$paperTitle' has been marked. Marks: $marks";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$subData['user_id'], $msg]);
            
            $success = "Submission marked successfully.";
        } elseif ($action === 'delete_paper') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("SELECT pdf_path FROM papers WHERE id = ?");
            $stmt->execute([$id]);
            $path = $stmt->fetchColumn();
            if ($path) {
                $file_path = TenantContext::getUploadDir('papers/') . '/' . $path;
                if (file_exists($file_path)) unlink($file_path);
            }
            $pdo->prepare("DELETE FROM papers WHERE id = ?")->execute([$id]);
            $success = "Paper deleted successfully.";
        } elseif ($action === 'edit_mcq_answers') {
            $id  = (int)$_POST['id'];
            $stmt = $pdo->prepare("SELECT mcq_config FROM papers WHERE id = ?");
            $stmt->execute([$id]);
            $existing = $stmt->fetchColumn();
            $cfg = $existing ? json_decode($existing, true) : [];
            // Update answers; also persist num_questions/num_options from hidden fields if provided
            $cfg['answers'] = $_POST['mcq_answers'] ?? [];
            if (!empty($_POST['num_questions_hidden'])) $cfg['num_questions'] = (int)$_POST['num_questions_hidden'];
            if (!empty($_POST['num_options_hidden']))   $cfg['num_options']   = (int)$_POST['num_options_hidden'];
            $pdo->prepare("UPDATE papers SET mcq_config = ? WHERE id = ?")
                ->execute([json_encode($cfg), $id]);
            $success = "MCQ answer key updated successfully.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Data
$grades = $pdo->query("SELECT * FROM grades ORDER BY id ASC")->fetchAll();
$subjects = $pdo->query("SELECT s.*, g.name as grade_name FROM subjects s JOIN grades g ON s.grade_id = g.id ORDER BY g.id, s.id")->fetchAll();

// Submission Filters
$sub_grade_id = $_GET['sub_grade_id'] ?? '';
$sub_subject_id = $_GET['sub_subject_id'] ?? '';

// Pending Submissions
$sub_query = "
    SELECT ps.*, u.username as student_name, p.title as paper_title, s.name as subject_name, g.name as grade_name
    FROM paper_submissions ps
    JOIN users u ON ps.user_id = u.id
    JOIN papers p ON ps.paper_id = p.id
    JOIN subjects s ON p.subject_id = s.id
    JOIN grades g ON s.grade_id = g.id
    WHERE ps.status = 'submitted'
";
$sub_params = [];

if ($sub_grade_id) {
    $sub_query .= " AND g.id = ?";
    $sub_params[] = $sub_grade_id;
}
if ($sub_subject_id) {
    $sub_query .= " AND s.id = ?";
    $sub_params[] = $sub_subject_id;
}

$sub_query .= " ORDER BY ps.submitted_at DESC";
$sub_stmt = $pdo->prepare($sub_query);
$sub_stmt->execute($sub_params);
$submissions = $sub_stmt->fetchAll();

// Marked Submissions
$marked_search = $_GET['marked_search'] ?? '';
$marked_query = "
    SELECT ps.*, u.username as student_name, u.student_id, p.title as paper_title, s.name as subject_name, g.name as grade_name
    FROM paper_submissions ps
    JOIN users u ON ps.user_id = u.id
    JOIN papers p ON ps.paper_id = p.id
    JOIN subjects s ON p.subject_id = s.id
    JOIN grades g ON s.grade_id = g.id
    WHERE ps.status = 'marked'
";
$marked_params = [];
if ($sub_grade_id) {
    $marked_query .= " AND g.id = ?";
    $marked_params[] = $sub_grade_id;
}
if ($sub_subject_id) {
    $marked_query .= " AND s.id = ?";
    $marked_params[] = $sub_subject_id;
}
if ($marked_search) {
    $marked_query .= " AND (u.username LIKE ? OR u.student_id LIKE ?)";
    $marked_params[] = "%$marked_search%";
    $marked_params[] = "%$marked_search%";
}
$marked_query .= " ORDER BY ps.submitted_at DESC LIMIT 50";
$marked_stmt = $pdo->prepare($marked_query);
$marked_stmt->execute($marked_params);
$marked_submissions = $marked_stmt->fetchAll();

// Group papers by grade and subject
$papers = $pdo->query("
    SELECT p.*, s.name as subject_name, g.name as grade_name 
    FROM papers p 
    JOIN subjects s ON p.subject_id = s.id 
    JOIN grades g ON s.grade_id = g.id 
    ORDER BY g.id, s.id, p.created_at DESC
")->fetchAll();

?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Academic Evaluations</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Upload exam papers, monitor submissions, and provide grades.</p>
    </div>
    <button class="btn btn-primary" onclick="document.getElementById('uploadModal').style.display='flex'" style="border-radius: 10px; height: 48px; padding: 0 1.5rem; font-weight: 700; background: var(--blue-900); border: none; display: flex; align-items: center; gap: 0.75rem;">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
        New Assessment
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1">
    <!-- Submissions for Marking -->
<div class="card mb-8" style="padding: 0; overflow: hidden; border-radius: 16px;">
    <div style="padding: 1.5rem 2rem; background: var(--surface-2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; color: var(--text);">Pending Reviews</h3>
        
        <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
            <div style="position: relative;">
                <select name="sub_grade_id" class="form-control" style="width: auto; height: 40px; padding: 0 2.5rem 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 600;" onchange="this.form.submit()">
                    <option value="">All Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $sub_grade_id == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <div style="position: relative;">
                <select name="sub_subject_id" class="form-control" style="width: auto; height: 40px; padding: 0 2.5rem 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 600;" onchange="this.form.submit()">
                    <option value="">All Subjects</option>
                    <?php foreach ($subjects as $s): ?>
                        <?php if (!$sub_grade_id || $s['grade_id'] == $sub_grade_id): ?>
                            <option value="<?= $s['id'] ?>" <?= $sub_subject_id == $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['grade_name']) ?>)</option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm" style="position: absolute; right: 0.75rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            <?php if ($sub_grade_id || $sub_subject_id): ?>
                <a href="papers.php" class="btn btn-ghost" style="height: 40px; display: flex; align-items: center; padding: 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

        <?php if (empty($submissions)): ?>
            <div style="text-align: center; padding: 5rem 2rem; color: var(--text-3);">
                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">📝</div>
                <p style="font-weight: 600;">No pending submissions found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Student</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Module</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Paper Title</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Submission</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($submissions as $sub): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 800; color: var(--text);"><?= htmlspecialchars($sub['student_name']) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="color: var(--blue-600); font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;"><?= htmlspecialchars($sub['grade_name']) ?></div>
                                    <div style="font-size: 0.9rem; font-weight: 700; color: var(--text);"><?= htmlspecialchars($sub['subject_name']) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text);"><?= htmlspecialchars($sub['paper_title']) ?></td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text);"><?= date('M j, Y', strtotime($sub['submitted_at'])) ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-3); font-weight: 600;"><?= date('g:i A', strtotime($sub['submitted_at'])) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end; align-items: center;">
                                        <?php if ($sub['mcq_score'] !== null): ?>
                                            <span class="badge badge-blue" style="font-size: 0.65rem; font-weight: 800; text-transform: uppercase;">MCQ: <?= $sub['mcq_score'] ?></span>
                                        <?php endif; ?>
                                        
                                        <?php if ($sub['submission_path']): ?>
                                            <a href="../<?= TenantContext::getUploadUrl('submissions/' . $sub['submission_path']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-weight: 700;">View File</a>
                                            <a href="mark_paper.php?id=<?= $sub['id'] ?>" class="btn btn-primary btn-sm" style="background: var(--blue-600); border: none; font-weight: 700; border-radius: 8px;">Mark Paper</a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Marked Submissions -->
<div class="card mb-8" style="padding: 0; overflow: hidden; border-radius: 16px;">
    <div style="padding: 1.5rem 2rem; background: var(--surface-2); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; color: var(--text);">Review Archive</h3>
        
        <form method="GET" style="display: flex; gap: 0.75rem; align-items: center;">
            <input type="hidden" name="sub_grade_id" value="<?= $sub_grade_id ?>">
            <input type="hidden" name="sub_subject_id" value="<?= $sub_subject_id ?>">
            
            <div style="position: relative;">
                <input type="text" name="marked_search" value="<?= htmlspecialchars($marked_search) ?>" class="form-control" placeholder="Search student or ID..." style="width: 240px; height: 40px; padding: 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 600;">
            </div>
            <button type="submit" class="btn btn-primary" style="height: 40px; padding: 0 1.25rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700;">Search</button>
            <?php if ($marked_search): ?>
                <a href="papers.php?sub_grade_id=<?= $sub_grade_id ?>&sub_subject_id=<?= $sub_subject_id ?>" class="btn btn-ghost" style="height: 40px; display: flex; align-items: center; padding: 0 1rem; font-size: 0.85rem; border-radius: 8px; font-weight: 700;">Reset</a>
            <?php endif; ?>
        </form>
    </div>
        <?php if (empty($marked_submissions)): ?>
            <div style="text-align: center; padding: 5rem 2rem; color: var(--text-3);">
                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">📜</div>
                <p style="font-weight: 600;">History is empty.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Student Identity</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Assessment</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Grading Details</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Review</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($marked_submissions as $m): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 800; color: var(--text);"><?= htmlspecialchars($m['student_name']) ?></div>
                                    <div style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.25rem;">
                                        <span style="font-family: 'JetBrains Mono', monospace; font-size: 0.7rem; color: var(--blue-600); font-weight: 800; background: var(--blue-50); padding: 0.1rem 0.4rem; border-radius: 4px;"><?= htmlspecialchars($m['student_id']) ?></span>
                                        <span style="font-size: 0.8rem; color: var(--text-3); font-weight: 700;">• <?= htmlspecialchars($m['grade_name']) ?></span>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($m['paper_title']) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-3); font-weight: 600;"><?= htmlspecialchars($m['subject_name']) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-size: 1.35rem; font-weight: 900; color: var(--blue-600); line-height: 1;"><?= $m['total_marks'] ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-3); font-weight: 800; margin-top: 0.4rem; text-transform: uppercase; letter-spacing: 0.02em;">MCQ: <?= $m['mcq_score'] ?? '-' ?> | Essay: <?= $m['essay_marks'] ?? '-' ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <a href="../<?= TenantContext::getUploadUrl('marked/' . $m['marked_pdf_path']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-weight: 700;">Review Script</a>
                                        <a href="mark_paper.php?id=<?= $m['id'] ?>" class="btn btn-ghost btn-sm" style="font-weight: 700; color: var(--blue-600);">Re-Mark</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Existing Papers List -->
    <!-- Existing Papers List -->
    <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
        <div style="padding: 1.5rem 2rem; background: var(--surface-2); border-bottom: 1px solid var(--border);">
            <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; color: var(--text);">Master Repository</h3>
        </div>
        
        <?php if (empty($papers)): ?>
            <div style="text-align: center; padding: 5rem 2rem; color: var(--text-3);">
                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">📂</div>
                <p style="font-weight: 600;">No papers have been uploaded to the system.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Academic Level</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Classification</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Paper Title</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Submission Window</th>
                            <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($papers as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="color: var(--blue-600); font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;"><?= htmlspecialchars($p['grade_name']) ?></div>
                                    <div style="font-size: 0.95rem; font-weight: 700; color: var(--text);"><?= htmlspecialchars($p['subject_name']) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <?php 
                                    $type_class = 'badge-neutral';
                                    if ($p['paper_type'] === 'mcq') $type_class = 'badge-blue';
                                    if ($p['paper_type'] === 'essay') $type_class = 'badge-green';
                                    if ($p['paper_type'] === 'both') $type_class = 'badge-purple';
                                    ?>
                                    <span class="badge <?= $type_class ?>" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 800;"><?= $p['paper_type'] ?></span>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 800; color: var(--text);"><?= htmlspecialchars($p['title']) ?></td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-size: 0.85rem; font-weight: 800; color: <?= strtotime($p['deadline']) < time() ? 'var(--red-600)' : 'var(--text)' ?>;">
                                        <?= date('M j, Y', strtotime($p['deadline'])) ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-3); font-weight: 600;"><?= date('g:i A', strtotime($p['deadline'])) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                        <?php if ($p['essay_pdf_path']): ?>
                                            <a href="../<?= TenantContext::getUploadUrl('papers/' . $p['essay_pdf_path']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-weight: 700;">View Essay</a>
                                        <?php endif; ?>
                                        <?php if ($p['mcq_pdf_path']): ?>
                                            <a href="../<?= TenantContext::getUploadUrl('papers/' . $p['mcq_pdf_path']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-weight: 700;">View MCQ</a>
                                        <?php endif; ?>
                                        <?php if (in_array($p['paper_type'] ?? '', ['mcq', 'both'])): ?>
                                            <button type="button" class="btn btn-ghost btn-sm" style="font-weight: 700; color: var(--blue-600);" onclick='openEditAnswers(<?= htmlspecialchars(json_encode($p)) ?>)'>Key</button>
                                        <?php endif; ?>
                                        <form method="POST" onsubmit="return confirm('Permanently delete this paper and all student responses?')" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_paper">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--red-600); font-weight: 700;">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
</div>

<!-- Edit MCQ Answers Modal -->
<div id="editAnswersModal" class="modal-overlay" style="display:none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 560px; padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <h3 style="margin: 0;">MCQ Answer Key</h3>
                <p id="editAnswersPaperTitle" style="color: var(--blue-600); font-weight: 800; font-size: 0.95rem; margin-top: 0.25rem;"></p>
            </div>
            <button onclick="document.getElementById('editAnswersModal').style.display='none'" class="btn btn-ghost btn-sm" style="padding: 0.5rem; border-radius: 50%;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="action" value="edit_mcq_answers">
            <input type="hidden" name="id" id="editAnswersPaperId">
            <div id="editAnswerKeyContainer" style="display:grid; grid-template-columns: repeat(5, 1fr); gap: 12px; max-height: 400px; overflow-y: auto; padding: 1.5rem; background: var(--surface-2); border-radius: 16px; border: 1px solid var(--border); margin-bottom: 2rem;"></div>
            <button type="submit" class="btn btn-primary" style="width: 100%; height: 48px; border-radius: 10px; font-weight: 800; background: var(--blue-900);">Finalize Answer Key</button>
        </form>
    </div>
</div>

<script>
function openEditAnswers(paper) {
    const rawCfg = paper.mcq_config;
    const cfg = rawCfg ? JSON.parse(rawCfg) : {};
    document.getElementById('editAnswersPaperId').value = paper.id;
    document.getElementById('editAnswersPaperTitle').innerText = paper.title;
    renderEditAnswerGrid(cfg);
    document.getElementById('editAnswersModal').style.display = 'flex';
}

function renderEditAnswerGrid(cfg) {
    const container = document.getElementById('editAnswerKeyContainer');
    const options   = ['1','2','3','4','5'];
    const saved     = cfg.answers      || {};
    const num       = cfg.num_questions || 0;
    const optsCount = cfg.num_options   || 5;

    if (num === 0) {
        // No config yet — show setup fields
        container.innerHTML = `
            <div style="grid-column:1/-1; padding:1rem 0;">
                <p style="color:var(--text-secondary);margin-bottom:1rem;">No MCQ config found for this paper. Enter the question count to set up the answer key.</p>
                <div style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1rem;">
                    <div class="form-group" style="flex:1;min-width:130px;">
                        <label>Number of Questions</label>
                        <input type="number" id="setupNumQ" min="1" max="200" value="40" style="width:100%;">
                    </div>
                    <div class="form-group" style="flex:1;min-width:130px;">
                        <label>Options per Question</label>
                        <input type="number" id="setupNumOpts" min="2" max="5" value="5" style="width:100%;">
                    </div>
                </div>
                <button type="button" class="btn btn-primary" style="width:100%;"
                    onclick="renderEditAnswerGrid({num_questions:+document.getElementById('setupNumQ').value, num_options:+document.getElementById('setupNumOpts').value, answers:{}})">
                    Build Answer Grid
                </button>
            </div>`;
        return;
    }

    let html = `<input type="hidden" name="num_questions_hidden" value="${num}">
                <input type="hidden" name="num_options_hidden"   value="${optsCount}">`;
    for (let i = 1; i <= num; i++) {
        html += `<div style="display:flex;flex-direction:column;gap:4px;">
            <span style="font-size:0.7rem;color:var(--text-secondary);">Q${i}</span>
            <select name="mcq_answers[${i}]" style="padding:2px;font-size:0.8rem;">
                <option value="">-</option>`;
        for (let j = 0; j < optsCount; j++) {
            const sel = (saved[i] !== undefined && saved[i] == options[j]) ? 'selected' : '';
            html += `<option value="${options[j]}" ${sel}>${options[j]}</option>`;
        }
        html += `</select></div>`;
    }
    container.innerHTML = html;
}
</script>
<div id="uploadModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 540px; padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <h3 style="margin: 0;">Create Evaluation</h3>
                <p style="color: var(--text-3); font-size: 0.95rem; margin-top: 0.25rem;">Deploy a new assessment paper to the repository.</p>
            </div>
            <button onclick="document.getElementById('uploadModal').style.display='none'" class="btn btn-ghost btn-sm" style="padding: 0.5rem; border-radius: 50%;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload_paper">
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Grade & Module</label>
                <div style="position: relative;">
                    <select name="subject_id" class="form-control" style="border-radius: 10px; height: 48px; padding-right: 2.5rem;" required>
                        <option value="">Select Target Module...</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name']) ?> • <?= htmlspecialchars($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Assessment Title</label>
                <input type="text" name="title" class="form-control" style="border-radius: 10px; height: 48px;" placeholder="e.g. Unit Test - Mechanics" required>
            </div>
            <div class="form-group mb-6">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Assessment Format</label>
                <div style="position: relative;">
                    <select name="paper_type" id="paperTypeSelect" class="form-control" style="border-radius: 10px; height: 48px; padding-right: 2.5rem;" onchange="togglePaperFields()" required>
                        <option value="essay">Essay Response Only</option>
                        <option value="mcq">Automated MCQ Only</option>
                        <option value="both">Hybrid (MCQ + Essay)</option>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div id="essayFields">
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Essay Question Paper (PDF)</label>
                    <input type="file" name="essay_pdf" id="essayPdfInput" class="form-control" style="border-radius: 10px; height: 48px; padding: 0.6rem;" accept=".pdf" required>
                </div>
            </div>
 
            <div id="mcqFields" style="display: none;">
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">MCQ Question Paper (PDF)</label>
                    <input type="file" name="mcq_pdf" id="mcqPdfInput" class="form-control" style="border-radius: 10px; height: 48px; padding: 0.6rem;" accept=".pdf">
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group mb-4">
                        <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Questions</label>
                        <input type="number" name="num_questions" id="numQuestions" class="form-control" style="border-radius: 10px; height: 48px;" min="1" max="100" value="40" onchange="generateAnswerKey()">
                    </div>
                    <div class="form-group mb-4">
                        <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Options</label>
                        <input type="number" name="num_options" id="numOptions" class="form-control" style="border-radius: 10px; height: 48px;" min="2" max="5" value="5" onchange="generateAnswerKey()">
                    </div>
                </div>
                <div class="form-group mb-6">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Initialize Answer Key</label>
                    <div id="answerKeyContainer" style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; max-height: 250px; overflow-y: auto; padding: 1.5rem; background: var(--surface-2); border-radius: 16px; border: 1px solid var(--border);">
                        <!-- JS generated -->
                    </div>
                </div>
            </div>
 
            <div class="form-group mb-8">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Submission Deadline</label>
                <input type="datetime-local" name="deadline" class="form-control" style="border-radius: 10px; height: 48px;" required>
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; height: 52px; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Initialize Assessment</button>
        </form>
    </div>
</div>v>

<script>
function togglePaperFields() {
    const type = document.getElementById('paperTypeSelect').value;
    document.getElementById('essayFields').style.display = (type === 'essay' || type === 'both') ? 'block' : 'none';
    document.getElementById('mcqFields').style.display = (type === 'mcq' || type === 'both') ? 'block' : 'none';
    
    document.getElementById('essayPdfInput').required = (type === 'essay' || type === 'both');
    document.getElementById('mcqPdfInput').required = (type === 'mcq' || type === 'both');
    
    if (type === 'mcq' || type === 'both') generateAnswerKey();
}

function generateAnswerKey() {
    const container = document.getElementById('answerKeyContainer');
    const num = parseInt(document.getElementById('numQuestions').value) || 0;
    const optsCount = parseInt(document.getElementById('numOptions').value) || 4;
    const options = ['1', '2', '3', '4', '5'];
    
    let html = '';
    for (let i = 1; i <= num; i++) {
        html += `<div style="display:flex; flex-direction:column; gap:4px;">
            <span style="font-size:0.7rem; color:var(--text-secondary);">Q${i}</span>
            <select name="mcq_answers[${i}]" style="padding:2px; font-size:0.8rem;">
                <option value="">-</option>`;
        for (let j = 0; j < optsCount; j++) {
            html += `<option value="${options[j]}">${options[j]}</option>`;
        }
        html += `</select></div>`;
    }
    container.innerHTML = html;
}
</script>

<!-- Marking Modal -->
<div id="markingModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 480px;">
        <button onclick="document.getElementById('markingModal').style.display='none'" class="close-modal" style="top: 1.5rem; right: 1.5rem;">&times;</button>
        <h3 class="mb-2">Mark Submission</h3>
        <p id="markingDetails" class="mb-6" style="color: var(--blue-600); font-weight: 700; font-size: 0.9rem;"></p>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="mark_submission">
            <input type="hidden" name="submission_id" id="markingSubmissionId">
            <div class="form-group mb-4">
                <label>Marks (0-100)</label>
                <input type="number" name="marks" class="form-control" style="border-radius: 10px;" min="0" max="100" required>
            </div>
            <div class="form-group mb-6">
                <label>Marked Paper (PDF) - Optional</label>
                <input type="file" name="marked_pdf" class="form-control" style="border-radius: 10px;" accept=".pdf">
            </div>
            <button type="submit" class="btn btn-primary btn-block" style="border-radius: 10px;">Submit Grade</button>
        </form>
    </div>
</div>

<script>
function openMarkingModal(id, student, paper) {
    document.getElementById('markingSubmissionId').value = id;
    document.getElementById('markingDetails').innerText = `Student: ${student} | Paper: ${paper}`;
    document.getElementById('markingModal').style.display = 'flex';
}

// Client-side file size validation
document.querySelectorAll('form[enctype="multipart/form-data"]').forEach(form => {
    form.addEventListener('submit', function(e) {
        const fileInputs = this.querySelectorAll('input[type="file"]');
        const maxSize = 40 * 1024 * 1024; // 40MB limit based on user error
        
        for (let input of fileInputs) {
            if (input.files && input.files[0] && input.files[0].size > maxSize) {
                e.preventDefault();
                alert(`File "${input.files[0].name}" is too large. Max size allowed is 40MB.`);
                return;
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
