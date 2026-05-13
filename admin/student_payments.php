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

<div class="mb-10">
    <a href="students.php" class="btn btn-ghost btn-sm mb-6 inline-flex items-center gap-2 font-bold">
        <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Back to Students
    </a>
    
    <div class="flex-between-center flex-wrap gap-6">
        <div class="flex items-center gap-6">
            <?php if (!empty($student['photo'])): ?>
                <img src="../<?= htmlspecialchars($student['photo']) ?>" alt="" class="w-22 h-22 rounded-22 object-cover border-default">
            <?php else: ?>
                <div class="w-22 h-22 rounded-22 bg-blue-50 text-blue-600 flex-center font-extrabold text-4xl-plus border-blue-100">
                    <?= strtoupper(substr($student['username'], 0, 1)) ?>
                </div>
            <?php endif; ?>
            <div>
                <h2 class="m-0 text-3xl"><?= htmlspecialchars($student['username']) ?></h2>
                <div class="text-tertiary mt-2 text-base font-medium">
                    <span class="student-id-mono bg-blue-50 text-blue-600 px-2 py-1 rounded-6"><?= htmlspecialchars($student['student_id'] ?: 'NEW_STUDENT') ?></span>
                    <span class="mx-3 opacity-30">|</span>
                    <?= htmlspecialchars($student['grade_name'] ?: 'No Grade Assigned') ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-3 mb-8">
    <!-- Current Month Status Card -->
    <div class="card col-span-1 h-fit">
        <h3 class="label-accent-muted-sm mb-6">Monthly Status: <?= date('F Y') ?></h3>
        <div class="flex-col gap-4">
            <?php if (empty($payment_summary)): ?>
                <p class="text-tertiary italic text-center p-4">No subjects in this grade.</p>
            <?php else: ?>
                <?php foreach ($payment_summary as $sub_id => $data): ?>
                    <div class="flex-between-center p-4 bg-surface-muted rounded-12 border-default">
                        <span class="font-bold text-primary"><?= htmlspecialchars($data['name']) ?></span>
                        <?php if ($data['paid']): ?>
                            <span class="badge badge-green badge-uppercase text-xxs font-extrabold">Paid</span>
                        <?php else: ?>
                            <span class="badge badge-red badge-uppercase text-xxs font-extrabold">Unpaid</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- History Table Card -->
    <div class="card col-span-2 p-0 overflow-hidden">
        <div class="p-6-8 border-b-default bg-surface-muted">
            <h3 class="label-accent-muted-sm m-0">Full Transaction History</h3>
        </div>
        <div class="table-responsive">
            <table class="w-full border-collapse">
                <thead>
                    <tr class="text-left border-b-default bg-surface-muted">
                        <th class="p-4-6 text-xs uppercase tracking-wider text-tertiary">Date & Time</th>
                        <th class="p-4-6 text-xs uppercase tracking-wider text-tertiary">Subject</th>
                        <th class="p-4-6 text-xs uppercase tracking-wider text-tertiary">Status</th>
                        <th class="p-4-6 text-xs uppercase tracking-wider text-tertiary text-right">Evidence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="4" class="p-20-8 text-center text-tertiary font-semibold">No payment records found for this student.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr class="border-b-default">
                                <td class="p-5-6">
                                    <div class="font-bold text-primary"><?= date('M j, Y', strtotime($p['created_at'])) ?></div>
                                    <div class="text-xs text-tertiary"><?= date('g:i A', strtotime($p['created_at'])) ?></div>
                                </td>
                                <td class="p-5-6 font-bold text-primary"><?= htmlspecialchars($p['subject_name']) ?></td>
                                <td class="p-5-6">
                                    <?php 
                                    $badge_class = 'badge-neutral';
                                    if ($p['status'] === 'approved') { $badge_class = 'badge-green'; }
                                    elseif ($p['status'] === 'pending') { $badge_class = 'badge-blue'; }
                                    elseif ($p['status'] === 'rejected') { $badge_class = 'badge-red'; }
                                    ?>
                                    <span class="badge <?= $badge_class ?> badge-uppercase text-xs font-extrabold">
                                        <?= ucfirst($p['status']) ?>
                                    </span>
                                </td>
                                <td class="p-5-6 text-right">
                                    <a href="../<?= htmlspecialchars($p['proof_image']) ?>" target="_blank" class="btn btn-ghost btn-sm text-blue-600 font-bold">
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
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
