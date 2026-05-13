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

<style>
    #calendar {
        background: var(--surface);
        padding: 2.5rem;
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: var(--shadow-sm);
    }
    .fc { font-family: inherit; }
    .fc-theme-standard td, .fc-theme-standard th { border-color: var(--border); }
    .fc-event {
        cursor: pointer;
        background-color: var(--blue-600) !important;
        border: none !important;
        border-radius: 8px;
        padding: 5px 10px;
        font-weight: 700;
        font-size: 0.8rem;
        box-shadow: 0 2px 4px rgba(37, 99, 235, 0.2);
        transition: transform 0.2s;
    }
    .fc-event:hover {
        background-color: var(--blue-700) !important;
        transform: translateY(-1px);
    }
    .fc-toolbar-title {
        font-weight: 800 !important;
        color: var(--text);
        letter-spacing: -0.03em;
        font-size: 1.5rem !important;
    }
    .fc-button-primary {
        background-color: var(--surface-2) !important;
        color: var(--text) !important;
        border: 1px solid var(--border) !important;
        text-transform: uppercase;
        font-weight: 800 !important;
        font-size: 0.7rem !important;
        padding: 0.6rem 1.2rem !important;
        border-radius: 10px !important;
        letter-spacing: 0.05em;
        transition: all 0.2s;
    }
    .fc-button-primary:hover {
        background-color: var(--border) !important;
        border-color: var(--text-3) !important;
    }
    .fc-button-active {
        background-color: var(--blue-600) !important;
        color: white !important;
        border-color: var(--blue-600) !important;
    }
</style>

    /* Modal Styles */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.4);
        backdrop-filter: blur(8px);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 2rem;
    }
    .modal-overlay.active {
        display: flex;
    }
    .modal {
        background: var(--surface);
        width: 100%;
        max-width: 480px;
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: var(--shadow-lg);
        padding: 2.5rem;
        position: relative;
    }
</style>
    .close-modal {
        position: absolute;
        top: 1rem; right: 1rem;
        background: none; border: none;
        font-size: 1.5rem; font-weight: bold;
        cursor: pointer; color: var(--text-secondary);
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Academic Schedule</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Manage live sessions, physical classes, and module timelines.</p>
    </div>
    <button class="btn btn-primary" onclick="openModal()" style="border-radius: 12px; height: 48px; padding: 0 1.5rem; font-weight: 800; background: var(--blue-900); border: none; display: flex; align-items: center; gap: 0.75rem;">
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
<div class="modal-overlay" id="classModal" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 520px; padding: 2.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
            <div>
                <h3 id="modalTitle" style="margin: 0;">Schedule Session</h3>
                <p style="color: var(--text-3); font-size: 0.95rem; margin-top: 0.25rem;">Configure the details for the new class session.</p>
            </div>
            <button onclick="closeModal()" class="btn btn-ghost btn-sm" style="padding: 0.5rem; border-radius: 50%;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add_class" id="modalAction">
            <input type="hidden" name="id" value="" id="modalClassId">
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Academic Module</label>
                <div style="position: relative;">
                    <select name="subject_id" class="form-control" style="border-radius: 10px; height: 48px; padding-right: 2.5rem;" required>
                        <option value="">Select Target Module...</option>
                        <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['grade_name'] . ' • ' . $s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Session Title</label>
                <input type="text" name="title" class="form-control" style="border-radius: 10px; height: 48px;" placeholder="e.g. Weekly Revision - Unit 04" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Start Time</label>
                    <input type="datetime-local" name="start_time" id="start_time_input" class="form-control" style="border-radius: 10px; height: 48px;" required>
                </div>
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Session Type</label>
                    <div style="position: relative;">
                        <select name="class_type" class="form-control" style="border-radius: 10px; height: 48px; padding-right: 2.5rem;" required>
                            <option value="online">Online Only</option>
                            <option value="physical">Physical Only</option>
                            <option value="hybrid">Hybrid (Both)</option>
                        </select>
                        <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                    </div>
                </div>
            </div>
            
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Zoom / Virtual Link</label>
                <input type="url" name="zoom_link" class="form-control" style="border-radius: 10px; height: 48px;" placeholder="https://zoom.us/j/...">
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group mb-6">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Cover Image</label>
                    <input type="file" name="image" class="form-control" style="border-radius: 10px; height: 48px; padding: 0.6rem;" accept="image/*">
                </div>
                <div class="form-group mb-6">
                    <label style="font-weight: 700; color: var(--text-3); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">Course Notes (PDF)</label>
                    <input type="file" name="notes_pdf" class="form-control" style="border-radius: 10px; height: 48px; padding: 0.6rem;" accept=".pdf">
                </div>
            </div>
            
            <button class="btn btn-primary" id="modalSubmitBtn" style="width: 100%; height: 52px; border-radius: 12px; font-weight: 800; background: var(--blue-900); border: none;">Initialize Session</button>
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
<div style="margin-top: 4rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
    <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--blue-600);"></div>
    <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800; color: var(--text);">Session Management</h3>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 20px; border: 1px solid var(--border);">
    <div style="background: var(--surface-2); padding: 1rem 1.5rem; border-bottom: 1px solid var(--border); display: grid; grid-template-columns: 1fr auto; align-items: center;">
        <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.05em;">Session Overview</span>
        <span style="font-size: 0.75rem; font-weight: 800; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.05em; text-align: right;">Actions</span>
    </div>
    
    <div style="max-height: 600px; overflow-y: auto;">
        <?php foreach ($classes as $c): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); transition: background 0.2s;" onmouseover="this.style.background='var(--surface-2)'" onmouseout="this.style.background='transparent'">
                <div style="display: flex; align-items: center; gap: 1.5rem;">
                    <?php if (!empty($c['image'])): ?>
                        <div style="width: 56px; height: 56px; border-radius: 12px; overflow: hidden; border: 2px solid var(--border);">
                            <img src="../<?= htmlspecialchars($c['image']) ?>" alt="Thumbnail" style="width: 100%; height: 100%; object-fit: cover;">
                        </div>
                    <?php else: ?>
                        <div style="width: 56px; height: 56px; background: var(--blue-50); border: 2px solid var(--blue-100); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: var(--blue-600);">
                            <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div style="color: var(--blue-600); font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem;"><?= htmlspecialchars($c['subject_name']) ?></div>
                        <div style="font-weight: 800; font-size: 1.15rem; color: var(--text);"><?= htmlspecialchars($c['title']) ?></div>
                        <div style="color: var(--text-3); font-size: 0.9rem; font-weight: 600; margin-top: 0.25rem;">
                             <?= date('F j, Y', strtotime($c['start_time'])) ?> • <span style="color: var(--text);"><?= date('g:i A', strtotime($c['start_time'])) ?></span>
                        </div>
                    </div>
                </div>
                <div style="display: flex; gap: 0.75rem;">
                    <button type="button" class="btn btn-ghost" style="font-weight: 700; padding: 0.5rem 1rem; border-radius: 10px;" onclick='openModal("", <?= json_encode($c) ?>)'>
                        Edit Details
                    </button>
                    <form method="POST" onsubmit="return confirm('Permanently remove this scheduled session?')" style="display: inline;">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button type="submit" class="btn btn-ghost" style="color: var(--red-600); font-weight: 700; padding: 0.5rem 1rem; border-radius: 10px;">Delete</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($classes)): ?>
            <div style="padding: 5rem 2rem; text-align: center;">
                <div style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.2;">📅</div>
                <p style="color: var(--text-3); font-weight: 700;">No sessions scheduled on the timeline.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
