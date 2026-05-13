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

<div style="margin-bottom: 2.5rem;">
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Student Directory</h2>
            <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Manage your learning community and track individual progress.</p>
        </div>
        
        <div style="display: flex; align-items: center; gap: 1.5rem; background: var(--blue-50); padding: 0.75rem 1.5rem; border-radius: 12px; border: 1px solid var(--blue-100);">
            <div style="text-align: center;">
                <div style="font-weight: 800; color: var(--blue-600); line-height: 1; font-size: 1.5rem;"><?= count($students) ?></div>
                <div style="font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--blue-500); font-weight: 700; margin-top: 0.25rem;">Total Students</div>
            </div>
        </div>
    </div>
    
    <div class="card mb-8" style="padding: 1.25rem;">
        <form method="GET" style="display: grid; grid-template-columns: 1fr auto auto auto; gap: 1rem; align-items: center;">
            <div style="position: relative;">
                <svg class="icon" style="position: absolute; left: 1rem; top: 50%; transform: translateY(-50%); width: 20px; height: 20px; color: var(--text-3);" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                <input type="text" name="search" placeholder="Search students by name or ID..." value="<?= htmlspecialchars($search) ?>" class="form-control" style="padding-left: 3rem; border-radius: 10px; height: 48px;">
            </div>
            
            <div style="position: relative;">
                <select name="grade_id" class="form-control" style="border-radius: 10px; min-width: 180px; appearance: none; padding-right: 2.5rem; height: 48px;">
                    <option value="">All Academic Grades</option>
                    <?php foreach ($grades as $g): ?>
                        <option value="<?= $g['id'] ?>" <?= $grade_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
            </div>
            
            <button type="submit" class="btn btn-primary" style="height: 48px; padding: 0 1.5rem; border-radius: 10px; font-weight: 700;">
                Update View
            </button>
            
            <?php if (!empty($search) || !empty($grade_filter)): ?>
                <a href="students.php" class="btn btn-ghost" style="height: 48px; display: flex; align-items: center; border-radius: 10px;">
                    Reset Filters
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
    <div class="table-responsive">
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Identity</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Student Profile</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Academic Grade</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Registration</th>
                    <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Action Control</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" style="padding: 5rem 2rem; text-align: center;">
                            <div style="font-size: 3.5rem; margin-bottom: 1.25rem; filter: grayscale(1); opacity: 0.2;">🔭</div>
                            <div style="font-weight: 700; color: var(--text); font-size: 1.25rem;">No matching students</div>
                            <p style="color: var(--text-3); margin-top: 0.5rem;">We couldn't find any student records for your search.</p>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $stu): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 1.5rem;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <?php if (!empty($stu['photo'])): ?>
                                        <img src="../<?= htmlspecialchars($stu['photo']) ?>" alt="" style="width: 48px; height: 48px; border-radius: 14px; object-fit: cover; border: 1px solid var(--border);">
                                    <?php else: ?>
                                        <div style="width: 48px; height: 48px; border-radius: 14px; background: var(--blue-50); color: var(--blue-600); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.1rem; border: 1px solid var(--blue-100);">
                                            <?= strtoupper(substr($stu['username'], 0, 1)) ?>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--blue-600); font-size: 1rem; letter-spacing: -0.02em;"><?= htmlspecialchars($stu['student_id'] ?: 'NEW_STUDENT') ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-3); font-weight: 600;">SYSTEM ID: #<?= $stu['id'] ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="padding: 1.5rem;">
                                <div style="font-weight: 700; font-size: 1.1rem; color: var(--text);"><?= htmlspecialchars($stu['username']) ?></div>
                                <div style="font-size: 0.85rem; color: var(--text-3);">Active Member</div>
                            </td>
                            <td style="padding: 1.5rem;">
                                <?php if ($stu['grade_name']): ?>
                                    <span class="badge badge-blue" style="font-size: 0.85rem; padding: 0.4rem 0.8rem;"><?= htmlspecialchars($stu['grade_name']) ?></span>
                                <?php else: ?>
                                    <span class="badge badge-neutral">Unassigned</span>
                                <?php endif; ?>
                            </td>
                            <td style="padding: 1.5rem;">
                                <div style="color: var(--text); font-weight: 700; font-size: 0.95rem;"><?= date('M j, Y', strtotime($stu['created_at'])) ?></div>
                                <div style="font-size: 0.8rem; color: var(--text-3);"><?= date('g:i A', strtotime($stu['created_at'])) ?></div>
                            </td>
                            <td style="padding: 1.5rem; text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                                    <?php if ($stu['student_id']): ?>
                                        <button onclick="showStudentDetails('<?= $stu['student_id'] ?>')" class="btn btn-ghost btn-sm" style="font-weight: 700;">
                                            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                                            View Profile
                                        </button>
                                    <?php endif; ?>
                                    <a href="student_payments.php?student_id=<?= $stu['id'] ?>" class="btn btn-ghost btn-sm" style="color: var(--blue-600); font-weight: 700;">
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

<!-- Student Detail Modal (Command Center) -->
<style>
    .detail-modal-container {
        width: 100%;
        max-width: 900px;
    }
    .detail-modal-content {
        background: var(--bg-color);
        border-radius: 32px;
        padding: 3rem;
        position: relative;
        border: 1px solid rgba(255,255,255,0.1);
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }
    .spinner {
        width: 40px; height: 40px; border: 4px solid rgba(255,255,255,0.1);
        border-top-color: var(--primary-color); border-radius: 50%;
        animation: spin 1s linear infinite;
        margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    @media (max-width: 768px) {
        .detail-modal-content {
            padding: 1.5rem;
            border-radius: 20px;
        }
        #studentDetailModal {
            padding: 10px;
        }
    }
    @media (max-width: 900px) {
        form {
            grid-template-columns: 1fr !important;
        }
    }
</style>

<div id="studentDetailModal" class="modal-overlay" style="display: none; align-items: flex-start; justify-content: center; padding: 40px 20px;">
    <div class="modal" style="max-width: 960px; width: 100%; padding: 0; background: var(--bg-body); overflow: hidden;">
        <div style="padding: 1.5rem 2rem; background: var(--surface); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 10;">
            <h3 style="margin: 0;">Student Control Center</h3>
            <button onclick="document.getElementById('studentDetailModal').style.display='none'" class="btn btn-ghost btn-sm" style="padding: 0.5rem; border-radius: 50%;">
                <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div id="studentDetailContent" style="padding: 2rem;">
            <!-- Loaded via AJAX -->
            <div style="text-align: center; padding: 5rem 2rem;">
                <div class="spinner"></div>
                <p style="margin-top: 1.5rem; color: var(--text-3); font-weight: 600;">Syncing student records...</p>
            </div>
        </div>
    </div>
</div>

<script>
    function showStudentDetails(studentId) {
        document.getElementById('studentDetailModal').style.display = 'flex';
        document.getElementById('studentDetailContent').innerHTML = `
            <div style="text-align: center; padding: 4rem;">
                <div class="spinner"></div>
                <p style="margin-top: 1rem; color: var(--text-secondary);">Fetching student records...</p>
            </div>
        `;
        
        fetch('api/get_student_details.php?student_id=' + studentId)
            .then(response => response.text())
            .then(html => {
                document.getElementById('studentDetailContent').innerHTML = html;
            })
            .catch(err => {
                document.getElementById('studentDetailContent').innerHTML = '<p style="color:red; text-align:center;">Error loading student details.</p>';
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
