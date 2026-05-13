<?php
require_once __DIR__ . '/../db.php';
$pdo = getDB();

// Authentication Check for Export/Logic
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get filter parameters
$grade_filter = $_GET['grade_id'] ?? '';
$month_filter = $_GET['month'] ?? date('m');
$year_filter = $_GET['year'] ?? date('Y');

// Handle CSV Export - MUST BE BEFORE HEADER.PHP
if (isset($_GET['export'])) {
    $filename = "payments_report_" . $month_filter . "_" . $year_filter . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Payment ID', 'Student Name', 'Grade', 'Subject', 'Amount', 'Date', 'Status']);
    
    $exportStmt = $pdo->prepare("
        SELECT p.id, u.username, g.name as grade_name, s.name as subject_name, s.price, p.created_at, p.status
        FROM payments p 
        JOIN users u ON p.user_id = u.id 
        JOIN subjects s ON p.subject_id = s.id 
        JOIN grades g ON u.grade_id = g.id
        WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
        ORDER BY g.name, p.created_at DESC
    ");
    $exportStmt->execute([$month_filter, $year_filter]);
    
    $total_earnings = 0;
    while ($row = $exportStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['status'] === 'approved') {
            $total_earnings += $row['price'];
        }
        fputcsv($output, $row);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['', '', '', 'TOTAL EARNINGS (Approved)', 'LKR ' . number_format($total_earnings, 2)]);
    fclose($output);
    exit;
}

require_once __DIR__ . '/includes/header.php';

$success = '';
$error = '';

// Handle Status Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payment_id = $_POST['payment_id'] ?? '';
    $action = $_POST['action'] ?? '';
    
    if ($payment_id && ($action === 'approved' || $action === 'rejected')) {
        $pStmt = $pdo->prepare("SELECT p.*, s.name as subject_name, u.username as student_name FROM payments p JOIN subjects s ON p.subject_id = s.id JOIN users u ON p.user_id = u.id WHERE p.id = ?");
        $pStmt->execute([$payment_id]);
        $payment = $pStmt->fetch();
        
        if ($payment) {
            $update = $pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
            if ($update->execute([$action, $payment_id])) {
                $success = "Payment " . htmlspecialchars($action) . " successfully.";
                $msg = "Your payment for " . $payment['subject_name'] . " has been " . $action . ".";
                $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
                $nStmt->execute([$payment['user_id'], $msg]);
            } else {
                $error = "Failed to update payment status.";
            }
        }
    }
}

// Fetch all payments with filters
$query = "
    SELECT p.*, u.username, s.name as subject_name, s.price, g.name as grade_name, g.id as grade_id
    FROM payments p 
    JOIN users u ON p.user_id = u.id 
    JOIN subjects s ON p.subject_id = s.id 
    JOIN grades g ON u.grade_id = g.id
    WHERE MONTH(p.created_at) = ? AND YEAR(p.created_at) = ?
";
$params = [$month_filter, $year_filter];

if ($grade_filter) {
    $query .= " AND g.id = ?";
    $params[] = $grade_filter;
}

$query .= " ORDER BY g.name ASC, CASE WHEN p.status = 'pending' THEN 1 ELSE 2 END, p.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$payments = $stmt->fetchAll();

// Group payments by grade
$grouped_payments = [];
$total_approved_earnings = 0;
foreach ($payments as $pay) {
    $grouped_payments[$pay['grade_name']][] = $pay;
    if ($pay['status'] === 'approved') {
        $total_approved_earnings += $pay['price'];
    }
}

// Fetch all grades for filter
$grades = $pdo->query("SELECT * FROM grades ORDER BY name ASC")->fetchAll();
?>

<div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2.5rem; flex-wrap: wrap; gap: 1.5rem;">
    <div>
        <h2 style="font-size: 2.25rem; margin-bottom: 0.5rem;">Financial Overview</h2>
        <p style="color: var(--text-3); margin: 0; font-size: 1.1rem;">Track and verify student payments for the current billing cycle.</p>
    </div>
    
    <div style="display: flex; gap: 1.25rem; align-items: center; background: var(--blue-50); padding: 1rem 1.5rem; border-radius: 16px; border: 1px solid var(--blue-100);">
        <div style="text-align: right;">
            <div style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--blue-500); font-weight: 800; margin-bottom: 0.25rem;">Validated Earnings</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: var(--blue-700); line-height: 1;">LKR <?= number_format($total_approved_earnings, 2) ?></div>
        </div>
        <div style="width: 1px; height: 40px; background: var(--blue-200);"></div>
        <a href="?export=1&month=<?= $month_filter ?>&year=<?= $year_filter ?>&grade_id=<?= $grade_filter ?>" class="btn btn-primary" style="padding: 0.75rem 1.25rem; border-radius: 10px; background: var(--blue-900); border: none; font-size: 0.9rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Sheet
        </a>
    </div>
</div>

<<div class="card mb-8" style="padding: 1.25rem;">
    <form method="GET" style="display: grid; grid-template-columns: 1fr 1fr 1.5fr auto; gap: 1rem; align-items: center;">
        <div style="position: relative;">
            <select name="month" class="form-control" style="border-radius: 10px; appearance: none; padding-right: 2.5rem; height: 48px;">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month_filter == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        
        <div style="position: relative;">
            <select name="year" class="form-control" style="border-radius: 10px; appearance: none; padding-right: 2.5rem; height: 48px;">
                <?php for($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                    <option value="<?= $y ?>" <?= $year_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
 
        <div style="position: relative;">
            <select name="grade_id" class="form-control" style="border-radius: 10px; appearance: none; padding-right: 2.5rem; height: 48px;">
                <option value="">All Academic Grades</option>
                <?php foreach ($grades as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $grade_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <svg class="icon icon-sm" style="position: absolute; right: 1rem; top: 50%; transform: translateY(-50%); pointer-events: none; color: var(--text-3);"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        
        <button type="submit" class="btn btn-primary" style="height: 48px; padding: 0 1.5rem; border-radius: 10px; font-weight: 700;">Filter Results</button>
    </form>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="margin-bottom: 2rem;"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error" style="margin-bottom: 2rem;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($grouped_payments)): ?>
    <div class="card" style="padding: 5rem 2rem; text-align: center;">
        <div style="font-size: 4rem; margin-bottom: 1.5rem; filter: grayscale(1); opacity: 0.2;">📊</div>
        <div style="font-weight: 700; color: var(--text); font-size: 1.25rem;">Financial records empty</div>
        <p style="color: var(--text-3); margin-top: 0.5rem;">No payment transactions were found for the selected period.</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped_payments as $grade_name => $grade_pays): ?        <div style="margin-bottom: 3.5rem;">
            <div style="display: flex; align-items: center; gap: 1.25rem; margin-bottom: 1.25rem;">
                <h3 style="margin: 0; font-size: 1.1rem; font-weight: 800; color: var(--blue-600); text-transform: uppercase; letter-spacing: 0.05em;"><?= htmlspecialchars($grade_name) ?></h3>
                <div style="height: 1px; flex: 1; background: var(--border); opacity: 0.5;"></div>
                <span style="font-size: 0.8rem; font-weight: 700; color: var(--text-3); background: var(--surface-2); padding: 0.25rem 0.75rem; border-radius: 99px; border: 1px solid var(--border);"><?= count($grade_pays) ?> Payments</span>
            </div>
 
            <div class="card" style="padding: 0; overflow: hidden; border-radius: 16px;">
                <div class="table-responsive">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="text-align: left; border-bottom: 1px solid var(--border); background: var(--surface-2);">
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Student</th>
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Module</th>
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Amount</th>
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Timestamp</th>
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3);">Status</th>
                                <th style="padding: 1.25rem 1.5rem; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-3); text-align: right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>dy>
                            <?php foreach ($grade_pays as $pay): ?>
                                <tr style="border-bottom: 1px solid var(--border);">
                                    <td style="padding: 1.5rem;">
                                        <div style="font-weight: 700; color: var(--text);"><?= htmlspecialchars($pay['username']) ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-3); font-weight: 600;">ACTIVE STUDENT</div>
                                    </td>
                                    <td style="padding: 1.5rem; font-weight: 600; color: var(--text);"><?= htmlspecialchars($pay['subject_name']) ?></td>
                                    <td style="padding: 1.5rem; font-weight: 800; color: var(--blue-600); font-size: 1rem;">LKR <?= number_format($pay['price'], 2) ?></td>
                                    <td style="padding: 1.5rem;">
                                        <div style="font-weight: 700; color: var(--text);"><?= date('M j, Y', strtotime($pay['created_at'])) ?></div>
                                        <div style="font-size: 0.8rem; color: var(--text-3);"><?= date('g:i A', strtotime($pay['created_at'])) ?></div>
                                    </td>
                                    <td style="padding: 1.5rem;">
                                        <?php if ($pay['status'] === 'approved'): ?>
                                            <span class="badge badge-green" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 800;">Approved</span>
                                        <?php elseif ($pay['status'] === 'rejected'): ?>
                                            <span class="badge badge-red" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 800;">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-blue" style="text-transform: uppercase; font-size: 0.7rem; font-weight: 800;">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 1.5rem; text-align: right;">
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <a href="../<?= htmlspecialchars($pay['proof_image']) ?>" target="_blank" class="btn btn-ghost btn-sm" style="font-weight: 700;">View Slip</a>
                                            
                                            <?php if ($pay['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" class="btn btn-primary btn-sm" style="background: var(--blue-600); border: none; font-weight: 700; border-radius: 8px;">Approve</button>
                                                </form>
                                                <form method="POST" style="display: inline-block;">
                                                    <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" class="btn btn-ghost btn-sm" style="color: var(--red-600); font-weight: 700;">Reject</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
