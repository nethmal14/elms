<?php
require_once __DIR__ . '/includes/header.php';

// Get filter parameters
$search = $_GET['search'] ?? '';
$grade_filter = $_GET['grade_id'] ?? '';

// Build query
$query = "SELECT u.*, g.name as grade_name FROM users u LEFT JOIN grades g ON u.grade_id = g.id WHERE u.role = 'student'";
$params = [];

if (!empty($search)) {
    $query .= " AND (u.username LIKE ? OR u.student_id LIKE ? OR u.id LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($grade_filter)) {
    $query .= " AND u.grade_id = ?";
    $params[] = $grade_filter;
}

$query .= " ORDER BY u.created_at DESC";

// Fetch students
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Fetch all grades for filter
$gradeStmt = $pdo->query("SELECT * FROM grades ORDER BY name ASC");
$grades = $gradeStmt->fetchAll();
?>

<div class="mb-10">
    <div class="flex-between-end mb-10 flex-wrap gap-4">
        <div>
            <h2 class="text-4xl mb-2">Student Directory</h2>
            <p class="text-tertiary m-0 text-lg">Manage your learning community and track individual progress.</p>
        </div>
        
        <div class="admin-stat-summary">
            <div class="text-center">
                <div class="stat-number"><?= count($students) ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
    </div>
    
    <div class="card p-5 mb-8">
        <form method="GET" class="admin-filter-grid">
            <div class="relative">
                <svg class="icon input-icon-left" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" name="search" placeholder="Search students by name or ID..." value="<?= htmlspecialchars($search) ?>" class="form-control input-with-icon">
            </div>
            
            <div class="relative">
                <select name="grade_id" class="form-control select-custom">
                    <option value="">All Academic Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $grade_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <button type="submit" class="btn btn-primary font-bold h-12 px-6 rounded-10">
                Update View
            </button>
            
            <?php if (!empty($search) || !empty($grade_filter)): ?>
                <a href="students.php" class="btn btn-ghost flex items-center h-12 rounded-10">
                    Reset Filters
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card card-table">
    <div class="table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Identity</th>
                    <th>Student Profile</th>
                    <th>Academic Grade</th>
                    <th>Registration</th>
                    <th class="text-right">Action Control</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" class="p-20 text-center">
                            <div class="text-5xl-plus mb-5 grayscale opacity-20">🔭</div>
                            <div class="text-xl font-bold">No matching students</div>
                            <p class="text-tertiary mt-2">We couldn't find any student records for your search.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                        <tr>
                            <td>
                                <div class="flex-center-gap-4">
                                    <?php if (!empty($stu['photo'])): ?>
                                        <img src="../<?= htmlspecialchars($stu['photo']) ?>" alt="" class="avatar-img">
                                    <?php else: ?>
                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($stu['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="student-id-mono"><?= htmlspecialchars($stu['student_id'] ?: 'NEW_STUDENT') ?></div>
                                        <div class="text-xs font-semibold text-tertiary">SYSTEM ID: #<?= $stu['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="font-bold text-lg"><?= htmlspecialchars($stu['username']) ?></div>
                                <div class="text-sm text-tertiary">Active Member</div>
                            </td>
                            <td>
                                <?php if ($stu['grade_name']): ?>
                                    <span class="badge badge-blue text-sm px-3 py-1.5"><?= htmlspecialchars($stu['grade_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="font-bold text-sm"><?= date('M j, Y', strtotime($stu['created_at'])) ?></div>
                                <div class="text-xs text-tertiary"><?= date('g:i A', strtotime($stu['created_at'])) ?></div>
                            </td>
                            <td class="text-right">
                                <div class="flex-end-gap-3">
                                    <?php if ($stu['student_id']): ?>
                                        <button onclick="showStudentDetails('<?= $stu['student_id'] ?>')" class="btn btn-ghost btn-sm font-bold">
                                            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            View Profile
                                        </button>
                                    <?php endif; ?>
                                    <a href="student_payments.php?student_id=<?= $stu['id'] ?>" class="btn btn-ghost btn-sm font-bold text-blue-600">
                                        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        Ledger
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>



<div id="studentDetailModal" class="modal-overlay flex-center-top p-10-5" style="display: none;">
    <div class="modal modal-large">
        <div class="modal-header-sticky">
            <h3 class="m-0">Student Control Center</h3>
            <button onclick="document.getElementById('studentDetailModal').style.display='none'" class="btn btn-ghost btn-sm btn-icon-rounded">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div id="studentDetailContent" class="p-8">
            <!-- Loaded via AJAX -->
            <div class="text-center p-20">
                <div class="spinner"></div>
                <p class="mt-6 font-semibold text-tertiary">Syncing student records...</p>
            </div>
        </div>
    </div>
</div>

<script>
    function showStudentDetails(studentId) {
        document.getElementById('studentDetailModal').style.display = 'flex';
        document.getElementById('studentDetailContent').innerHTML = `
            <div class="text-center p-20">
                <div class="spinner"></div>
                <p class="mt-4 text-tertiary">Fetching student records...</p>
            </div>
        `;
        
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
