<?php
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_class') {
        $subject_id = $_POST['subject_id'];
        $title = $_POST['title'];
        $start_time = $_POST['start_time']; // Format: YYYY-MM-DDTHH:MM
        $class_type = $_POST['class_type'] ?? 'online';
        $zoom_link = $_POST['zoom_link'];
        
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
        
        try {
            $stmt = $pdo->prepare("INSERT INTO classes (subject_id, title, start_time, class_type, zoom_link, image, notes_pdf) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$subject_id, $title, $start_time, $class_type, $zoom_link, $image, $notes_pdf]);
            
            // Notify enrolled students
            $sStmt = $pdo->prepare("SELECT user_id FROM payments WHERE subject_id = ? AND status = 'approved'");
            $sStmt->execute([$subject_id]);
            $students = $sStmt->fetchAll();
            
            $msg = "New upcoming class scheduled: " . $title . " on " . date('M j, g:i A', strtotime($start_time));
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            foreach ($students as $stu) {
                $nStmt->execute([$stu['user_id'], $msg]);
            }
            
            $success = "Class scheduled successfully and students notified.";
        } catch (Exception $e) {
            $error = "Error scheduling class: " . $e->getMessage();
        }
    } elseif ($action === 'edit_class') {
        $id = $_POST['id'];
        $subject_id = $_POST['subject_id'];
        $title = $_POST['title'];
        $start_time = $_POST['start_time'];
        $class_type = $_POST['class_type'] ?? 'online';
        $zoom_link = $_POST['zoom_link'];

        try {
            // Get current paths
            $stmt = $pdo->prepare("SELECT image, notes_pdf FROM classes WHERE id = ?");
            $stmt->execute([$id]);
            $current = $stmt->fetch();

            $image = $current['image'];
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = TenantContext::getUploadDir('images/');
                $filename = time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['image']['name']));
                if (move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . '/' . $filename)) {
                    $image = TenantContext::getUploadUrl('images/' . $filename);
                }
            }

            $notes_pdf = $current['notes_pdf'];
            if (isset($_FILES['notes_pdf']) && $_FILES['notes_pdf']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = TenantContext::getUploadDir('notes/');
                $filename = 'note_' . time() . '_' . preg_replace("/[^a-zA-Z0-9.]/", "", basename($_FILES['notes_pdf']['name']));
                if (move_uploaded_file($_FILES['notes_pdf']['tmp_name'], $upload_dir . '/' . $filename)) {
                    $notes_pdf = TenantContext::getUploadUrl('notes/' . $filename);
                }
            }

            $stmt = $pdo->prepare("UPDATE classes SET subject_id = ?, title = ?, start_time = ?, class_type = ?, zoom_link = ?, image = ?, notes_pdf = ? WHERE id = ?");
            $stmt->execute([$subject_id, $title, $start_time, $class_type, $zoom_link, $image, $notes_pdf, $id]);
            $success = "Class updated successfully.";
        } catch (Exception $e) {
            $error = "Error updating class: " . $e->getMessage();
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'];
        try {
            $pdo->prepare("DELETE FROM classes WHERE id = ?")->execute([$id]);
            $success = "Class deleted successfully.";
        } catch (Exception $e) {
            $error = "Error deleting class.";
        }
    }
}

// Fetch subjects for dropdown
$subjects = $pdo->query("SELECT s.*, g.name as grade_name FROM subjects s JOIN grades g ON s.grade_id = g.id ORDER BY g.id, s.id")->fetchAll();

// Fetch classes for fullcalendar events
$classes = $pdo->query("SELECT c.*, s.name as subject_name FROM classes c JOIN subjects s ON c.subject_id = s.id")->fetchAll();
$events = [];
foreach ($classes as $c) {
    $events[] = [
        'id' => $c['id'],
        'title' => $c['title'] . ' (' . $c['subject_name'] . ')',
        'start' => $c['start_time'],
        'url' => $c['zoom_link'],
        'extendedProps' => [
            'subject' => $c['subject_name']
        ]
    ];
}
?>

<!-- Include FullCalendar CSS & JS -->
<link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css' rel='stylesheet' />
<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js'></script>



<div class="flex-between-center mb-10">
    <div>
        <h2 class="text-4xl mb-2">Academic Schedule</h2>
        <p class="text-tertiary m-0 text-lg">Manage live sessions, physical classes, and module timelines.</p>
    </div>
    <button class="btn btn-primary btn-schedule flex-center-gap-3" onclick="openModal()">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Schedule Session
    </button>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div id='calendar'></div>

<!-- Add Class Modal -->
<div class="modal-overlay flex-center p-8" id="classModal" style="display: none;">
    <div class="modal modal-lg p-10">
        <div class="flex-between-start mb-8">
            <div>
                <h3 id="modalTitle" class="m-0">Schedule Session</h3>
                <p class="text-tertiary text-sm mt-1">Configure the details for the new class session.</p>
            </div>
            <button onclick="closeModal()" class="btn btn-ghost btn-sm btn-close-circle">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_class" id="modalAction">
            <input type="hidden" name="id" value="" id="modalClassId">
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">Academic Module</label>
                <div class="relative">
                    <select name="subject_id" class="form-control rounded-10 h-12 pr-10" required>
                        <option value="">Select Target Module...</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' • ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">Session Title</label>
                <input type="text" name="title" class="form-control rounded-10 h-12" placeholder="e.g. Weekly Revision - Unit 04" required>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group mb-4">
                    <label class="label-accent-muted-sm">Start Time</label>
                    <input type="datetime-local" name="start_time" id="start_time_input" class="form-control rounded-10 h-12" required>
                </div>
                <div class="form-group mb-4">
                    <label class="label-accent-muted-sm">Session Type</label>
                    <div class="relative">
                        <select name="class_type" class="form-control rounded-10 h-12 pr-10" required>
                            <option value="online">Online Only</option>
                            <option value="physical">Physical Only</option>
                            <option value="hybrid">Hybrid (Both)</option>
                        </select>
                        <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label class="label-accent-muted-sm">Zoom / Virtual Link</label>
                <input type="url" name="zoom_link" class="form-control rounded-10 h-12" placeholder="https://zoom.us/j/...">
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group mb-6">
                    <label class="label-accent-muted-sm">Cover Image</label>
                    <input type="file" name="image" class="form-control rounded-10 h-12 p-2.5" accept="image/*">
                </div>
                <div class="form-group mb-6">
                    <label class="label-accent-muted-sm">Course Notes (PDF)</label>
                    <input type="file" name="notes_pdf" class="form-control rounded-10 h-12 p-2.5" accept=".pdf">
                </div>
            </div>
            
            <button class="btn btn-primary btn-init-session" id="modalSubmitBtn">Initialize Session</button>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('calendar');
        var eventsData = <?= json_encode($events) ?>;

        var calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            height: 'auto',
            events: eventsData,
            eventClick: function(info) {
                // Prevent default URL opening if you want to handle it custom,
                // but since it's a zoom link, opening it might be fine.
                // We'll let it open in a new tab.
                info.jsEvent.preventDefault(); 
                if (info.event.url) {
                    window.open(info.event.url, "_blank");
                }
            },
            dateClick: function(info) {
                // Pre-fill the date when clicking on a day cell
                let clickedDate = info.dateStr;
                if(clickedDate.length === 10) { // If it's a month view (YYYY-MM-DD)
                    clickedDate += "T08:00"; // Default to 8 AM
                } else {
                    clickedDate = clickedDate.substring(0, 16); // Trim seconds/timezone
                }
                openModal(clickedDate);
            }
        });

        calendar.render();
    });

    function openModal(dateStr = '', classData = null) {
        const modal = document.getElementById('classModal');
        const actionInput = document.getElementById('modalAction');
        const titleInput = document.getElementById('modalTitle');
        const submitBtn = document.getElementById('modalSubmitBtn');
        const classIdInput = document.getElementById('modalClassId');
        
        modal.classList.add('active');
        
        if (classData) {
            // Edit mode
            titleInput.innerText = 'Edit Class';
            actionInput.value = 'edit_class';
            submitBtn.innerText = 'Save Changes';
            classIdInput.value = classData.id;
            
            // Fill fields
            document.querySelector('select[name="subject_id"]').value = classData.subject_id;
            document.querySelector('input[name="title"]').value = classData.title;
            document.querySelector('input[name="start_time"]').value = classData.start_time;
            document.querySelector('select[name="class_type"]').value = classData.class_type;
            document.querySelector('input[name="zoom_link"]').value = classData.zoom_link;
        } else {
            // Add mode
            titleInput.innerText = 'Schedule Class';
            actionInput.value = 'add_class';
            submitBtn.innerText = 'Schedule Class';
            classIdInput.value = '';
            
            // Reset fields
            document.querySelector('form', modal).reset();
            if(dateStr) {
                document.getElementById('start_time_input').value = dateStr;
            }
        }
    }

    function closeModal() {
        document.getElementById('classModal').classList.remove('active');
    }

    // Close modal on outside click
    window.onclick = function(event) {
        let modalOverlay = document.getElementById('classModal');
        if (event.target == modalOverlay) {
            closeModal();
        }
    }
</script>

<!-- List view for easy deletion -->
<div class="mt-16 mb-6 flex-center-gap-3">
    <div class="dot-indicator"></div>
    <h3 class="card-section-title">Session Management</h3>
</div>

<div class="card card-p0 overflow-hidden rounded-20 border-default">
    <div class="card-header-muted grid grid-cols-2-auto items-center">
        <span class="card-subsection-title">Session Overview</span>
        <span class="card-subsection-title text-right">Actions</span>
    </div>
    
    <div class="overflow-y-auto max-h-150">
        <?php foreach ($classes as $c): ?>
            <div class="session-item-row">
                <div class="flex items-center gap-6">
                    <?php if (!empty($c['image'])): ?>
                        <div class="session-thumb">
                            <img src="../<?= htmlspecialchars($c['image']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                        </div>
                    <?php else: ?>
                        <div class="session-thumb-empty">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="session-subject-tag"><?= htmlspecialchars($c['subject_name']) ?></div>
                        <div class="session-title-text"><?= htmlspecialchars($c['title']) ?></div>
                        <div class="session-time-text">
                             <?= date('F j, Y', strtotime($c['start_time'])) ?> • <span class="text-primary"><?= date('g:i A', strtotime($c['start_time'])) ?></span>
                        </div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="btn btn-ghost font-bold rounded-10 px-4 py-2" onclick='openModal("", <?= json_encode($c) ?>)'>
                        Edit Details
                    </button>
                    <form method="POST" onsubmit="return confirm('Permanently remove this scheduled session?')" class="inline-block">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-ghost font-bold rounded-10 px-4 py-2 text-danger">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($classes)): ?>
            <div class="p-20 text-center">
                <div class="text-5xl mb-4 opacity-20">📅</div>
                <p class="text-tertiary font-bold">No sessions scheduled on the timeline.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
