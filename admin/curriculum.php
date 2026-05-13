<?php
require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_grade') {
            $name = $_POST['name'];
            $stmt = $pdo->prepare("INSERT INTO grades (name) VALUES (?)");
            $stmt->execute([$name]);
            $success = "Grade added successfully.";
        } elseif ($action === 'add_subject') {
            $grade_id = $_POST['grade_id'];
            $name = $_POST['name'];
            $desc = $_POST['description'];
            $price = $_POST['price'];
            $stmt = $pdo->prepare("INSERT INTO subjects (grade_id, name, description, price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$grade_id, $name, $desc, $price]);
            $success = "Subject added successfully.";
        } elseif ($action === 'edit_grade') {
            $id = $_POST['id'];
            $name = $_POST['name'];
            $stmt = $pdo->prepare("UPDATE grades SET name = ? WHERE id = ?");
            $stmt->execute([$name, $id]);
            $success = "Grade updated successfully.";
        } elseif ($action === 'edit_subject') {
            $id = $_POST['id'];
            $grade_id = $_POST['grade_id'];
            $name = $_POST['name'];
            $desc = $_POST['description'];
            $price = $_POST['price'];
            $stmt = $pdo->prepare("UPDATE subjects SET grade_id = ?, name = ?, description = ?, price = ? WHERE id = ?");
            $stmt->execute([$grade_id, $name, $desc, $price, $id]);
            $success = "Subject updated successfully.";
        } elseif ($action === 'delete') {
            $table = $_POST['table'];
            $id = $_POST['id'];
            $allowed_tables = ['grades', 'subjects'];
            if (in_array($table, $allowed_tables)) {
                $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
                $stmt->execute([$id]);
                $success = ucfirst($table) . " deleted successfully.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch data
$grades = $pdo->query("SELECT * FROM grades ORDER BY id ASC")->fetchAll();
$subjects = $pdo->query("SELECT s.*, g.name as grade_name FROM subjects s JOIN grades g ON s.grade_id = g.id ORDER BY g.id, s.id")->fetchAll();
?>

<div style="margin-bottom: 2.5rem;">
    <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Academic Curriculum</h2>
    <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Structure your learning modules by defining academic grades and subjects.</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2">

    <!-- Grades Section -->
    <div class="card" style="border-radius: 16px; padding: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--blue-600);"></div>
            <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Academic Grades</h3>
        </div>
        
        <form method="POST" style="margin-bottom: 2.5rem; display: flex; gap: 0.75rem;">
            <input type="hidden" name="action" value="add_grade">
            <input type="text" name="name" class="form-control" placeholder="e.g. Grade 11" required style="flex: 1; border-radius: 10px; height: 48px;">
            <button class="btn btn-primary" style="border-radius: 10px; height: 48px; padding: 0 1.5rem; font-weight: 700;">Add Grade</button>
        </form>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <?php foreach ($grades as $g): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; background: var(--surface-2); border: 1px solid var(--border); border-radius: 12px; transition: transform 0.2s;">
                    <div style="font-weight: 800; color: var(--text); font-size: 1.05rem;"><?= htmlspecialchars($g['name']) ?></div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick='openEditGrade(<?= json_encode($g) ?>)' class="btn btn-ghost btn-sm" style="font-weight: 700;">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Deleting this grade will affect all associated subjects. Continue?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="table" value="grades">
                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--red-600); font-weight: 700;">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Subjects Section -->
    <div class="card" style="border-radius: 16px; padding: 2rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 2rem;">
            <div style="width: 10px; height: 10px; border-radius: 50%; background: var(--blue-600);"></div>
            <h3 style="margin: 0; font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Academic Subjects</h3>
        </div>
        
        <form method="POST" style="margin-bottom: 2.5rem; background: var(--blue-50); padding: 1.5rem; border-radius: 16px; border: 1px solid var(--blue-100);">
            <input type="hidden" name="action" value="add_subject">
            <div class="form-group mb-4">
                <label style="font-weight: 700; color: var(--blue-700); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;">Academic Grade</label>
                <div style="position: relative;">
                    <select name="grade_id" class="form-control" style="border-radius: 10px; height: 48px; background: white; padding-right: 2.5rem;" required>
                        <option value="">Select target grade...</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--blue-400);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--blue-700); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;">Subject Name</label>
                    <input type="text" name="name" class="form-control" style="border-radius: 10px; height: 48px; background: white;" placeholder="e.g. Physics" required>
                </div>
                <div class="form-group mb-4">
                    <label style="font-weight: 700; color: var(--blue-700); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;">Fee (LKR)</label>
                    <input type="number" step="0.01" name="price" class="form-control" style="border-radius: 10px; height: 48px; background: white;" placeholder="0.00" required>
                </div>
            </div>
            <div class="form-group mb-6">
                <label style="font-weight: 700; color: var(--blue-700); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.02em;">Curriculum Overview</label>
                <textarea name="description" class="form-control" style="border-radius: 10px; background: white; padding: 1rem;" placeholder="Briefly describe what students will learn..." rows="2" required></textarea>
            </div>
            <button class="btn btn-primary" style="width: 100%; height: 48px; border-radius: 10px; font-weight: 800; background: var(--blue-900);">Initialize Subject</button>
        </form>
        
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php foreach ($subjects as $s): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; background: var(--surface); border: 1px solid var(--border); border-radius: 14px;">
                    <div>
                        <span class="badge badge-blue" style="font-size: 0.65rem; padding: 0.25rem 0.6rem; margin-bottom: 0.5rem; display: inline-block;"><?= htmlspecialchars($s['grade_name']) ?></span>
                        <div style="font-weight: 800; font-size: 1.1rem; color: var(--text);"><?= htmlspecialchars($s['name']) ?></div>
                        <div style="color: var(--blue-600); font-size: 0.9rem; font-weight: 700; margin-top: 0.25rem;">LKR <?= number_format($s['price'], 2) ?></div>
                    </div>
                    <div style="display: flex; gap: 0.5rem;">
                        <button onclick='openEditSubject(<?= json_encode($s) ?>)' class="btn btn-ghost btn-sm" style="font-weight: 700;">Edit</button>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Permanently remove this subject?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="table" value="subjects">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--red-600); font-weight: 700;">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Edit Grade Modal -->
<div id="editGradeModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 400px; padding: 2rem;">
        <h3 class="mb-2">Edit Grade</h3>
        <p style="color: var(--text-3); font-size: 0.9rem; margin-bottom: 1.5rem;">Update the name of this academic level.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_grade">
            <input type="hidden" name="id" id="editGradeId">
            <div class="form-group mb-6">
                <label>Grade Title</label>
                <input type="text" name="name" id="editGradeName" class="form-control" style="border-radius: 10px;" required>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="closeModals()" class="btn btn-ghost" style="flex: 1; border-radius: 10px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 10px;">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div id="editSubjectModal" class="modal-overlay" style="display: none; align-items: center; justify-content: center; padding: 2rem;">
    <div class="modal" style="max-width: 500px; padding: 2.5rem;">
        <h3 class="mb-2">Edit Subject</h3>
        <p style="color: var(--text-3); font-size: 0.9rem; margin-bottom: 2rem;">Modify subject details and enrollment fees.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="id" id="editSubjectId">
            <div class="form-group mb-4">
                <label>Target Grade</label>
                <div style="position: relative;">
                    <select name="grade_id" id="editSubjectGrade" class="form-control" style="border-radius: 10px;" required>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group mb-4">
                    <label>Subject Name</label>
                    <input type="text" name="name" id="editSubjectName" class="form-control" style="border-radius: 10px;" required>
                </div>
                <div class="form-group mb-4">
                    <label>Fee (LKR)</label>
                    <input type="number" step="0.01" name="price" id="editSubjectPrice" class="form-control" style="border-radius: 10px;" required>
                </div>
            </div>
            <div class="form-group mb-8">
                <label>Module Description</label>
                <textarea name="description" id="editSubjectDesc" class="form-control" style="border-radius: 10px;" rows="3" required></textarea>
            </div>
            <div style="display: flex; gap: 1rem;">
                <button type="button" onclick="closeModals()" class="btn btn-ghost" style="flex: 1; border-radius: 10px;">Cancel</button>
                <button type="submit" class="btn btn-primary" style="flex: 1; border-radius: 10px;">Update Subject</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openEditGrade(grade) {
        document.getElementById('editGradeId').value = grade.id;
        document.getElementById('editGradeName').value = grade.name;
        document.getElementById('editGradeModal').style.display = 'flex';
    }
    function openEditSubject(subject) {
        document.getElementById('editSubjectId').value = subject.id;
        document.getElementById('editSubjectGrade').value = subject.grade_id;
        document.getElementById('editSubjectName').value = subject.name;
        document.getElementById('editSubjectPrice').value = subject.price;
        document.getElementById('editSubjectDesc').value = subject.description;
        document.getElementById('editSubjectModal').style.display = 'flex';
    }
    function closeModals() {
        document.getElementById('editGradeModal').style.display = 'none';
        document.getElementById('editSubjectModal').style.display = 'none';
    }
    window.onclick = function(event) {
        if (event.target.classList.contains('modal-overlay')) {
            closeModals();
        }
    }
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
