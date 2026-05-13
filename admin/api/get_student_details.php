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
    die("<div class='text-center p-8'><h3 class='text-danger'>Student Not Found</h3><p class='text-tertiary'>The scanned ID does not match any record in our database.</p></div>");
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



<div class="student-details-grid">
    <!-- Left Column: Quick Profile & ID Card -->
    <div class="student-profile-side">
        <div class="text-center mb-8">
            <div class="profile-img-container">
                <?php if ($student['photo']): ?>
                    <img src="../<?= htmlspecialchars($student['photo']) ?>" class="w-full h-full rounded-full object-cover">
                <?php else: ?>
                    <div class="profile-placeholder">
                        <?= strtoupper(substr($student['username'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
            </div>
            <h2 class="m-0 text-3xl"><?= htmlspecialchars($student['username']) ?></h2>
            <p class="text-blue-600 font-bold mt-1"><?= htmlspecialchars($student['grade_name']) ?></p>
            
            <div class="flex gap-2 justify-center mt-4">
                <a href="tel:<?= htmlspecialchars($student['phone']) ?>" class="btn btn-secondary flex items-center gap-2 px-4 py-2 text-xs">
                    <svg class="w-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M6.62 10.79c1.44 2.83 3.76 5.14 6.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/></svg>
                    Call Student
                </a>
            </div>
        </div>

        <div class="p-6 rounded-20 border-default bg-surface-muted">
            <h4 class="label-accent-muted-sm mb-4">Contact Details</h4>
            <div class="flex-col gap-4">
                <div>
                    <span class="text-xxs text-tertiary">School</span>
                    <div class="font-semibold"><?= htmlspecialchars($student['school'] ?: 'Not Provided') ?></div>
                </div>
                <div>
                    <span class="text-xxs text-tertiary">Phone</span>
                    <div class="font-semibold"><?= htmlspecialchars($student['phone'] ?: 'Not Provided') ?></div>
                </div>
                <div>
                    <span class="text-xxs text-tertiary">Member Since</span>
                    <div class="font-semibold"><?= date('M j, Y', strtotime($student['created_at'])) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Activity History -->
    <div class="flex-col gap-8">
        
        <!-- Payment History -->
        <div class="card p-6 bg-surface-card m-0">
            <h3 class="flex items-center gap-3 mb-6">
                <svg class="w-5 text-blue-600" fill="currentColor" viewBox="0 0 24 24"><path d="M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                Payment History
            </h3>
            <?php if (empty($payments)): ?>
                <p class="text-tertiary">No payment records found.</p>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($payments as $p): ?>
                        <div class="p-4 rounded-12 border-default bg-surface-muted">
                            <div class="text-xxs text-tertiary uppercase"><?= date('M Y', strtotime($p['created_at'])) ?></div>
                            <div class="font-bold my-1"><?= htmlspecialchars($p['subject_name']) ?></div>
                            <div class="flex-between-center">
                                <span class="text-sm font-extrabold text-primary">LKR <?= number_format($p['amount'], 0) ?></span>
                                <span class="text-xxs px-2 py-0.5 rounded-20 <?= $p['status'] === 'approved' ? 'bg-green-100 text-green-600' : 'bg-yellow-100 text-yellow-600' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Paper Submissions -->
        <div class="card p-6 bg-surface-card m-0">
            <h3 class="flex items-center gap-3 mb-6">
                <svg class="w-5 text-warning" fill="currentColor" viewBox="0 0 24 24"><path d="M14 2H6c-1.1 0-1.99.9-1.99 2L4 20c0 1.1.89 2 1.99 2H18c1.1 0 2-.9 2-2V8l-6-6zm2 16H8v-2h8v2zm0-4H8v-2h8v2zm-3-5V3.5L18.5 9H13z"/></svg>
                Academic Performance
            </h3>
            <?php if (empty($submissions)): ?>
                <p class="text-tertiary">No papers submitted yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="w-full border-collapse">
                        <thead>
                            <tr class="text-left border-b-default">
                                <th class="p-4 text-tertiary text-xs uppercase tracking-wider">Subject / Paper</th>
                                <th class="p-4 text-tertiary text-xs uppercase tracking-wider">Submitted</th>
                                <th class="p-4 text-tertiary text-xs uppercase tracking-wider text-right">Marks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($submissions as $s): ?>
                                <tr class="border-b-default last-border-none">
                                    <td class="p-4">
                                        <div class="text-xxs text-blue-600 font-bold uppercase"><?= htmlspecialchars($s['subject_name']) ?></div>
                                        <div class="font-semibold text-primary"><?= htmlspecialchars($s['paper_title']) ?></div>
                                    </td>
                                    <td class="p-4 text-sm text-tertiary">
                                        <?= date('M j, Y', strtotime($s['submitted_at'])) ?>
                                    </td>
                                    <td class="p-4 text-right">
                                        <?php if ($s['status'] === 'marked'): ?>
                                            <div class="font-black text-xl text-blue-600"><?= $s['marks'] ?>%</div>
                                        <?php else: ?>
                                            <span class="text-xxs opacity-50">Pending</span>
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
