<?php
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

$student_id = $_GET['student_id'] ?? '';
if (!$student_id) die("No student ID provided");

$pdo = getDB();

// Fetch Student Info
$stmt = $pdo->prepare("
    SELECT u.*, g.name as grade_name 
    FROM users u 
    LEFT JOIN grades g ON u.grade_id = g.id 
    WHERE u.student_id = ? AND u.role = 'student'
");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("<div style='text-align:center; padding: 2rem;'><h3 style='color:var(--accent-color);'>Student Not Found</h3><p>The scanned ID does not match any record in our database.</p></div>");
}

$user_id = $student['id'];

// Fetch Payments
$pStmt = $pdo->prepare("
    SELECT p.*, s.name as subject_name, s.price as amount
    FROM payments p 
    JOIN subjects s ON p.subject_id = s.id 
    WHERE p.user_id = ? 
    ORDER BY p.created_at DESC 
    LIMIT 10
");
$pStmt->execute([$user_id]);
$payments = $pStmt->fetchAll();

// Fetch Paper Submissions
$sStmt = $pdo->prepare("
    SELECT ps.*, p.title as paper_title, s.name as subject_name 
    FROM paper_submissions ps 
    JOIN papers p ON ps.paper_id = p.id 
    JOIN subjects s ON p.subject_id = s.id 
    WHERE ps.user_id = ? 
    ORDER BY ps.submitted_at DESC 
    LIMIT 10
");
$sStmt->execute([$user_id]);
$submissions = $sStmt->fetchAll();

// Get Site Name
$site_name = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'site_name'")->fetchColumn() ?: 'Elms';

?>

<style>
    .student-details-grid {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 2rem;
        align-items: start;
    }
    @media (max-width: 1100px) {
        .student-details-grid {
            grid-template-columns: 260px 1fr;
            gap: 1.5rem;
        }
    }
    @media (max-width: 900px) {
        .student-details-grid {
            grid-template-columns: 1fr;
            gap: 2rem;
        }
        .student-profile-side {
            text-align: center;
        }
        .student-profile-side .btn {
            justify-content: center;
            width: 100%;
            max-width: 300px;
            margin: 0 auto;
        }
    }
    @media (max-width: 480px) {
        .student-details-grid {
            gap: 1.5rem;
        }
    }
</style>

<div class="student-details-grid">
    <!-- Left Column: Quick Profile & ID Card -->
    <div class="student-profile-side">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 120px; height: 120px; border-radius: 50%; border: 4px solid var(--primary-color); padding: 5px; margin: 0 auto 1.5rem; background: var(--bg-color); overflow: hidden;">
                <?php if ($student['photo']): ?>
                    <img src="../<?= htmlspecialchars($student['photo']) ?>" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                <?php else: ?>
                    <div style="width: 100%; height: 100%; border-radius: 50%; background: var(--hover-bg); display: flex; align-items: center; justify-content: center; font-size: 3rem; font-weight: 800; color: var(--text-secondary);">
                        <?= strtoupper(substr($student['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <h2 style="margin: 0; font-size: 1.8rem;"><?= htmlspecialchars($student['username']) ?></h2>
            <p style="color: var(--accent-color); font-weight: 700; margin-top: 5px;"><?= htmlspecialchars($student['grade_name']) ?></p>
            
            <div style="display: flex; gap: 0.5rem; justify-content: center; margin-top: 1rem;">
                <a href="tel:<?= htmlspecialchars($student['phone']) ?>" class="btn btn-secondary" style="padding: 0.5rem 1rem; font-size: 0.8rem; display: flex; align-items: center; gap: 5px;">
                    <svg width="14" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    Call Student
                </a>
            </div>
        </div>

        <div style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.05); border-radius: 20px; padding: 1.5rem;">
            <h4 style="margin-bottom: 1rem; font-size: 0.8rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 1px;">Contact Details</h4>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <div>
                    <span style="font-size: 0.7rem; color: var(--text-secondary);">School</span>
                    <div style="font-weight: 600;"><?= htmlspecialchars($student['school'] ?: 'Not Provided') ?></div>
                </div>
                <div>
                    <span style="font-size: 0.7rem; color: var(--text-secondary);">Phone</span>
                    <div style="font-weight: 600;"><?= htmlspecialchars($student['phone'] ?: 'Not Provided') ?></div>
                </div>
                <div>
                    <span style="font-size: 0.7rem; color: var(--text-secondary);">Member Since</span>
                    <div style="font-weight: 600;"><?= date('M j, Y', strtotime($student['created_at'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Activity History -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Payment History -->
        <div class="card" style="background: rgba(255,255,255,0.02); margin: 0; padding: 1.5rem;">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" fill="var(--primary-color)" viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                Payment History
            </h3>
            <?php if (empty($payments)): ?>
                <p style="color: var(--text-secondary);">No payment records found.</p>
            <?php else: ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem;">
                    <?php foreach ($payments as $p): ?>
                        <div style="padding: 1rem; border-radius: 12px; border: 1px solid var(--border-light); background: var(--bg-color);">
                            <div style="font-size: 0.65rem; color: var(--text-secondary); text-transform: uppercase;"><?= date('M Y', strtotime($p['created_at'])) ?></div>
                            <div style="font-weight: 700; margin: 4px 0;"><?= htmlspecialchars($p['subject_name']) ?></div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.9rem; font-weight: 800;">LKR <?= number_format($p['amount'], 0) ?></span>
                                <span style="font-size: 0.7rem; padding: 2px 8px; border-radius: 20px; background: <?= $p['status'] === 'approved' ? 'rgba(52, 199, 89, 0.1)' : 'rgba(255, 149, 0, 0.1)' ?>; color: <?= $p['status'] === 'approved' ? '#34c759' : '#ff9500' ?>;">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paper Submissions -->
        <div class="card" style="background: rgba(255,255,255,0.02); margin: 0; padding: 1.5rem;">
            <h3 style="margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem;">
                <svg width="20" fill="#ff9500" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                Academic Performance
            </h3>
            <?php if (empty($submissions)): ?>
                <p style="color: var(--text-secondary);">No papers submitted yet.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border-light);">
                                <th style="padding: 1rem; color: var(--text-secondary); font-size: 0.8rem;">Subject / Paper</th>
                                <th style="padding: 1rem; color: var(--text-secondary); font-size: 0.8rem;">Submitted</th>
                                <th style="padding: 1rem; color: var(--text-secondary); font-size: 0.8rem; text-align: right;">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $s): ?>
                                <tr style="border-bottom: 1px solid var(--border-light);">
                                    <td style="padding: 1rem;">
                                        <div style="font-size: 0.7rem; color: var(--primary-color); font-weight: 700; text-transform: uppercase;"><?= htmlspecialchars($s['subject_name']) ?></div>
                                        <div style="font-weight: 600;"><?= htmlspecialchars($s['paper_title']) ?></div>
                                    </td>
                                    <td style="padding: 1rem; font-size: 0.85rem; color: var(--text-secondary);">
                                        <?= date('M j, Y', strtotime($s['submitted_at'])) ?>
                                    </td>
                                    <td style="padding: 1rem; text-align: right;">
                                        <?php if ($s['status'] === 'marked'): ?>
                                            <div style="font-weight: 900; font-size: 1.2rem; color: var(--primary-color);"><?= $s['marks'] ?>%</div>
                                        <?php else: ?>
                                            <span style="font-size: 0.7rem; opacity: 0.5;">Pending</span>
                                        <?php endif; ?>
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
