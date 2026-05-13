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

<div class="flex-between-end mb-10 flex-wrap gap-6">
    <div>
        <h2 class="text-4xl mb-2">Financial Overview</h2>
        <p class="text-tertiary m-0 text-lg">Track and verify student payments for the current billing cycle.</p>
    </div>
    
    <div class="admin-stat-summary-large">
        <div class="text-right">
            <div class="stat-label mb-2">Validated Earnings</div>
            <div class="text-2xl font-bold text-blue-700 leading-none">LKR <?= number_format($total_approved_earnings, 2) ?></div>
        </div>
        <div class="divider-v"></div>
        <a href="?export=1&month=<?= $month_filter ?>&year=<?= $year_filter ?>&grade_id=<?= $grade_filter ?>" class="btn-export">
            <svg class="icon icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export Sheet
        </a>
    </div>
</div>

<div class="card p-5 mb-8">
    <form method="GET" class="admin-payment-filter-grid">
        <div class="relative">
            <select name="month" class="form-control select-custom w-full">
                <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= str_pad($m, 2, '0', STR_PAD_LEFT) ?>" <?= $month_filter == str_pad($m, 2, '0', STR_PAD_LEFT) ? 'selected' : '' ?>>
                        <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                    </option>
                <?php endfor; ?>
            </select>
            <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        
        <div class="relative">
            <select name="year" class="form-control select-custom w-full">
                <?php for($y=date('Y'); $y>=date('Y')-2; $y--): ?>
                    <option value="<?= $y ?>" <?= $year_filter == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
 
        <div class="relative">
            <select name="grade_id" class="form-control select-custom w-full">
                <option value="">All Academic Grades</option>
                <?php foreach ($grades as $g): ?>
                    <option value="<?= $g['id'] ?>" <?= $grade_filter == $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <svg class="icon icon-sm select-arrow"><polyline points="6,9 12,15 18,9"/></svg>
        </div>
        
        <button type="submit" class="btn btn-primary font-bold h-12 px-6 rounded-10">Filter Results</button>
    </form>
</div>

<?php if ($success): ?>
    <div class="alert alert-success mb-8"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error mb-8"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (empty($grouped_payments)): ?>
    <div class="card p-20 text-center">
        <div class="text-6xl mb-6 grayscale opacity-20">📊</div>
        <div class="text-xl font-bold">Financial records empty</div>
        <p class="text-tertiary mt-2">No payment transactions were found for the selected period.</p>
    </div>
<?php else: ?>
    <?php foreach ($grouped_payments as $grade_name => $grade_pays): ?>
        <div class="mb-14">
            <div class="flex-center-gap-5 mb-5">
                <h3 class="grade-divider-title"><?= htmlspecialchars($grade_name) ?></h3>
                <div class="grade-divider-rule"></div>
                <span class="grade-divider-badge"><?= count($grade_pays) ?> Payments</span>
            </div>
 
            <div class="card card-table">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Module</th>
                                <th>Amount</th>
                                <th>Timestamp</th>
                                <th>Status</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($grade_pays as $pay): ?>
                                <tr>
                                    <td>
                                        <div class="font-bold text-primary"><?= htmlspecialchars($pay['username']) ?></div>
                                        <div class="text-xs font-semibold text-tertiary">ACTIVE STUDENT</div>
                                    </td>
                                    <td class="font-semibold"><?= htmlspecialchars($pay['subject_name']) ?></td>
                                    <td class="stat-number text-lg">LKR <?= number_format($pay['price'], 2) ?></td>
                                    <td>
                                        <div class="font-bold text-sm"><?= date('M j, Y', strtotime($pay['created_at'])) ?></div>
                                        <div class="text-xs text-tertiary"><?= date('g:i A', strtotime($pay['created_at'])) ?></div>
                                    </td>
                                    <td>
                                        <?php if ($pay['status'] === 'approved'): ?>
                                            <span class="badge badge-green badge-uppercase">Approved</span>
                                        <?php elseif ($pay['status'] === 'rejected'): ?>
                                            <span class="badge badge-red badge-uppercase">Rejected</span>
                                        <?php else: ?>
                                            <span class="badge badge-blue badge-uppercase">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-right">
                                        <div class="flex-end-gap-2">
                                            <a href="../<?= htmlspecialchars($pay['proof_image']) ?>" target="_blank" class="btn btn-ghost btn-sm font-bold">View Slip</a>
                                            
                                            <?php if ($pay['status'] === 'pending'): ?>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                    <input type="hidden" name="action" value="approved">
                                                    <button type="submit" class="btn btn-primary btn-sm btn-approve">Approve</button>
                                                </form>
                                                <form method="POST" class="inline-block">
                                                    <input type="hidden" name="payment_id" value="<?= $pay['id'] ?>">
                                                    <input type="hidden" name="action" value="rejected">
                                                    <button type="submit" class="btn btn-ghost btn-sm font-bold text-danger">Reject</button>
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
