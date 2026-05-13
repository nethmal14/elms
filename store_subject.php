<?php
require_once __DIR__ . '/includes/header.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$subject_id = (int)$_GET['id'];

// Get subject details
$stmt = $pdo->prepare("SELECT s.id, s.name, s.description, s.price, g.name as grade_name FROM subjects s JOIN grades g ON s.grade_id = g.id WHERE s.id = ?");
$stmt->execute([$subject_id]);
$subject = $stmt->fetch();

if (!$subject) {
    die("Subject not found.");
}

$has_access = false;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student') {
    $check = $pdo->prepare("SELECT status FROM payments WHERE user_id = ? AND subject_id = ? AND status = 'approved'");
    $check->execute([$_SESSION['user_id'], $subject_id]);
    if ($check->fetch()) {
        $has_access = true;
    }
}
?>

<main class="container py-12">
    <div class="mb-8">
        <a href="courses.php" class="btn btn-ghost btn-sm" style="padding-left: 0;">
            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
              <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12,19 5,12 12,5"/>
            </svg>
            Back to Courses
        </a>
    </div>

    <div class="card text-center" style="max-width: 800px; margin: 0 auto; padding: 5rem 2.5rem;">
        <div class="badge badge-blue mb-6"><?= htmlspecialchars($subject['grade_name']) ?></div>
        
        <h1 style="font-size: 3rem; margin-bottom: 1.5rem; line-height: 1.1;"><?= htmlspecialchars($subject['name']) ?></h1>
        
        <p class="text-secondary mb-10" style="font-size: 1.1rem; line-height: 1.7; max-width: 600px; margin: 0 auto 3rem;">
            <?= nl2br(htmlspecialchars($subject['description'])) ?>
        </p>
        
        <div class="mb-12">
            <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-tertiary); text-transform: uppercase; letter-spacing: 0.1em; margin-bottom: 0.5rem;">Course Enrollment Fee</div>
            <div style="font-size: 2.5rem; font-weight: 800; color: var(--text-primary);">LKR <?= number_format($subject['price'], 2) ?></div>
        </div>

        <div class="flex-center">
            <?php if ($has_access): ?>
                <a href="subject.php?id=<?= $subject['id'] ?>" class="btn btn-primary btn-lg" style="padding: 0 3rem;">Enter Class Dashboard</a>
            <?php elseif (isset($_SESSION['user_id']) && $_SESSION['role'] === 'student'): ?>
                <a href="payment.php?subject_id=<?= $subject['id'] ?>" class="btn btn-primary btn-lg" style="padding: 0 3rem;">Enroll Now (Secure Payment)</a>
            <?php else: ?>
                <a href="login.php?redirect=store_subject.php?id=<?= $subject['id'] ?>" class="btn btn-primary btn-lg" style="padding: 0 3rem;">Login to Enroll</a>
            <?php endif; ?>
        </div>
        
        <div class="mt-12 pt-8 flex-center gap-8" style="border-top: 1px solid var(--border-default);">
            <div class="flex items-center gap-2 text-tertiary" style="font-size: 0.85rem; font-weight: 500;">
                <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                Secure Enrollment
            </div>
            <div class="flex items-center gap-2 text-tertiary" style="font-size: 0.85rem; font-weight: 500;">
                <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
                Instant Access
            </div>
        </div>
    </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
