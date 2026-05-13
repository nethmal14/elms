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

<div class="mb-10">
    <h2 class="text-4xl mb-2">Academic Curriculum</h2>
    <p class="text-tertiary m-0 text-lg">Structure your learning modules by defining academic grades and subjects.</p>
</div>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="grid grid-cols-2">

    <!-- Grades Section -->
    <div class="card card-p8">
        <div class="flex-center-gap-3 mb-8">
            <div class="dot-indicator"></div>
            <h3 class="card-section-title">Academic Grades</h3>
        </div>
        
        <form method="POST" class="mb-10 flex gap-3">
            <input type="hidden" name="action" value="add_grade">
            <input type="text" name="name" class="form-control flex-1 rounded-10 h-12" placeholder="e.g. Grade 11" required>
            <button class="btn btn-primary rounded-10 h-12 px-6 font-bold">Add Grade</button>
        </form>
        
        <div class="flex-col gap-3">
            <?php foreach ($grades as $g): ?>
                <div class="curriculum-item-card">
                    <div class="item-title"><?= htmlspecialchars($g['name']) ?></div>
                    <div class="flex gap-2">
                        <button onclick='openEditGrade(<?= json_encode($g) ?>)' class="btn btn-ghost btn-sm font-bold">Edit</button>
                        <form method="POST" class="inline-block" onsubmit="return confirm('Deleting this grade will affect all associated subjects. Continue?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="table" value="grades">
                            <input type="hidden" name="id" value="<?= $g['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm font-bold text-danger">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Subjects Section -->
    <div class="card card-p8">
        <div class="flex-center-gap-3 mb-8">
            <div class="dot-indicator"></div>
            <h3 class="card-section-title">Academic Subjects</h3>
        </div>
        
        <form method="POST" class="curriculum-form-card">
            <input type="hidden" name="action" value="add_subject">
            <div class="form-group mb-4">
                <label class="label-accent">Academic Grade</label>
                <div class="relative">
                    <select name="grade_id" class="form-control select-custom-white" required>
                        <option value="">Select target grade...</option>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm select-arrow-blue"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group mb-4">
                    <label class="label-accent">Subject Name</label>
                    <input type="text" name="name" class="form-control input-custom-white" placeholder="e.g. Physics" required>
                </div>
                <div class="form-group mb-4">
                    <label class="label-accent">Fee (LKR)</label>
                    <input type="number" step="0.01" name="price" class="form-control input-custom-white" placeholder="0.00" required>
                </div>
            </div>
            <div class="form-group mb-6">
                <label class="label-accent">Curriculum Overview</label>
                <textarea name="description" class="form-control textarea-custom-white" placeholder="Briefly describe what students will learn..." rows="2" required></textarea>
            </div>
            <button class="btn btn-primary btn-init-subject">Initialize Subject</button>
        </form>
        
        <div class="flex-col gap-4">
            <?php foreach ($subjects as $s): ?>
                <div class="subject-item-card">
                    <div>
                        <span class="badge badge-blue badge-xs mb-2 inline-block"><?= htmlspecialchars($s['grade_name']) ?></span>
                        <div class="item-title-lg"><?= htmlspecialchars($s['name']) ?></div>
                        <div class="item-price">LKR <?= number_format($s['price'], 2) ?></div>
                    </div>
                    <div class="flex gap-2">
                        <button onclick='openEditSubject(<?= json_encode($s) ?>)' class="btn btn-ghost btn-sm font-bold">Edit</button>
                        <form method="POST" class="inline-block" onsubmit="return confirm('Permanently remove this subject?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="table" value="subjects">
                            <input type="hidden" name="id" value="<?= $s['id'] ?>">
                            <button type="submit" class="btn btn-ghost btn-sm font-bold text-danger">Delete</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Edit Grade Modal -->
<div id="editGradeModal" class="modal-overlay flex-center p-8" style="display: none;">
    <div class="modal modal-sm p-8">
        <h3 class="mb-2">Edit Grade</h3>
        <p class="text-tertiary text-sm mb-6">Update the name of this academic level.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_grade">
            <input type="hidden" name="id" id="editGradeId">
            <div class="form-group mb-6">
                <label>Grade Title</label>
                <input type="text" name="name" id="editGradeName" class="form-control rounded-10" required>
            </div>
            <div class="flex gap-4">
                <button type="button" onclick="closeModals()" class="btn btn-ghost flex-1 rounded-10">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1 rounded-10">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal -->
<div id="editSubjectModal" class="modal-overlay flex-center p-8" style="display: none;">
    <div class="modal modal-md p-10">
        <h3 class="mb-2">Edit Subject</h3>
        <p class="text-tertiary text-sm mb-8">Modify subject details and enrollment fees.</p>
        <form method="POST">
            <input type="hidden" name="action" value="edit_subject">
            <input type="hidden" name="id" id="editSubjectId">
            <div class="form-group mb-4">
                <label>Target Grade</label>
                <div class="relative">
                    <select name="grade_id" id="editSubjectGrade" class="form-control rounded-10" required>
                        <?php foreach ($grades as $g): ?>
                            <option value="<?= $g['id'] ?>"><?= htmlspecialchars($g['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div class="form-group mb-4">
                    <label>Subject Name</label>
                    <input type="text" name="name" id="editSubjectName" class="form-control rounded-10" required>
                </div>
                <div class="form-group mb-4">
                    <label>Fee (LKR)</label>
                    <input type="number" step="0.01" name="price" id="editSubjectPrice" class="form-control rounded-10" required>
                </div>
            </div>
            <div class="form-group mb-8">
                <label>Module Description</label>
                <textarea name="description" id="editSubjectDesc" class="form-control rounded-10" rows="3" required></textarea>
            </div>
            <div class="flex gap-4">
                <button type="button" onclick="closeModals()" class="btn btn-ghost flex-1 rounded-10">Cancel</button>
                <button type="submit" class="btn btn-primary flex-1 rounded-10">Update Subject</button>
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
