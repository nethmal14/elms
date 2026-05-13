<?php
require_once __DIR__ . '/includes/header.php';

$student_id = $_GET['student_id'] ?? null;

if (!$student_id) {
    header("Location: students.php");
    exit;
}

// Fetch student details
$stmt = $pdo->prepare("SELECT u.*, g.name as grade_name FROM users u LEFT JOIN grades g ON u.grade_id = g.id WHERE u.id = ? AND u.role = 'student'");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student not found.");
}

// Fetch payment history
$payStmt = $pdo->prepare("SELECT p.*, s.name as subject_name FROM payments p JOIN subjects s ON p.subject_id = s.id WHERE p.user_id = ? ORDER BY p.created_at DESC");
$payStmt->execute([$student_id]);
$payments = $payStmt->fetchAll();

// Check current month payment status for enrolled subjects
$curr_month = (int)date('n');
$curr_year = (int)date('Y');

// Get subjects in the student's grade
$subStmt = $pdo->prepare("SELECT * FROM subjects WHERE grade_id = ?");
$subStmt->execute([$student['grade_id']]);
$subjects = $subStmt->fetchAll();

$payment_summary = [];
foreach ($subjects as $sub) {
    $check = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? AND subject_id = ? AND status = 'approved' AND MONTH(created_at) = ? AND YEAR(created_at) = ?");
    $check->execute([$student_id, $sub['id'], $curr_month, $curr_year]);
    $payment_summary[$sub['id']] = [
        'name' => $sub['name'],
        'paid' => (bool)$check->fetch()
    ];
}
?>

<div style="margin-bottom: 2.5rem;">
    <a href="students.php" class="btn btn-ghost btn-sm mb-6" style="display: inline-flex; align-items: center; gap: 0.5rem; font-weight: 700;">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to Students
    </a>
    
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.5rem;">
        <div style="display: flex; align-items: center; gap: 1.5rem;">
            <?php if (!empty($student['photo'])): ?>
                <img src="../<?= htmlspecialchars($student['photo']) ?>" alt="" style="width: 88px; height: 88px; border-radius: 22px; object-fit: cover; border: 1px solid var(--border);">
            <?php else: ?>
                <div style="width: 88px; height: 88px; border-radius: 22px; background: var(--blue-50); color: var(--blue-600); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 2.5rem; border: 1px solid var(--blue-100);">
                    <?= strtoupper(substr($student['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 style="margin: 0; font-size: 2rem;"><?= htmlspecialchars($student['username']) ?></h2>
                <div style="color: var(--text-3); margin-top: 0.5rem; font-size: 1rem; font-weight: 500;">
                    <span style="font-family: 'JetBrains Mono', monospace; font-weight: 800; color: var(--blue-600); background: var(--blue-50); padding: 0.2rem 0.6rem; border-radius: 6px;"><?= htmlspecialchars($student['student_id'] ?: 'NEW_STUDENT') ?></span>
                    <span style="margin: 0 0.75rem; opacity: 0.3;">|</span>
                    <?= htmlspecialchars($student['grade_name'] ?: 'No Grade Assigned') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-3 mb-8">
    <!-- Current Month Status Card -->
    <div class="card" style="grid-column: span 1; height: fit-content;">
        <h3 style="margin-bottom: 1.5rem; font-size: 0.8rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Monthly Status: <?= date('F Y') ?></h3>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <?php if (empty($payment_summary)): ?>
                <p style="color: var(--text-3); font-style: italic; text-align: center; padding: 1rem;">No subjects in this grade.</p>
            <?php else: ?>
                <?php foreach ($payment_summary as $sub_id => $data): ?>
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: var(--surface-2); border-radius: 12px; border: 1px solid var(--border);">
                        <span style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($data['name']) ?></span>
                        <?php if ($data['paid']): ?>
                            <span class="badge badge-green" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 800;">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-red" style="text-transform: uppercase; font-size: 0.65rem; font-weight: 800;">Unpaid</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- History Table Card -->
    <div class="card" style="grid-column: span 2; padding: 0; overflow: hidden;">
        <div style="padding: 1.5rem 2rem; border-bottom: 1px solid var(--border); background: var(--surface-2);">
            <h3 style="margin: 0; font-size: 0.8rem; color: var(--text-3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 800;">Full Transaction History</h3>
        </div>
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Date & Time</th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Subject</th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Status</th>
                        <th style="padding: 1rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Evidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="4" style="padding: 5rem 2rem; text-align: center; color: var(--text-3); font-weight: 600;">No payment records found for this student.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 1.25rem 1.5rem;">
                                    <div style="font-weight: 700; color: var(--text);"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                                    <div style="font-size: 0.8rem; color: var(--text-3);"><?= date('g:i A', strtotime($p['created_at'])) ?></div>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; font-weight: 700; color: var(--text);"><?= htmlspecialchars($p['subject_name']) ?></td>
                                <td style="padding: 1.25rem 1.5rem;">
                                    <?php 
                                    $badge_class = 'badge-neutral';
                                    if ($p['status'] === 'approved') { $badge_class = 'badge-green'; }
                                    elseif ($p['status'] === 'pending') { $badge_class = 'badge-blue'; }
                                    elseif ($p['status'] === 'rejected') { $badge_class = 'badge-red'; }
                                    ?>
                                    <span class="badge <?= $badge_class ?>" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 800;">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td style="padding: 1.25rem 1.5rem; text-align: right;">
                                    <a href="../<?= htmlspecialchars($p['proof_image']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="color: var(--blue-600); font-weight: 700;">
                                        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                        View Proof
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
