<?php
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$tenant_id = TenantContext::get()['id'];
$pdo = getDB();

$class_id = $_POST['class_id'] ?? null;
$student_id = trim($_POST['student_id'] ?? '');
$force_allow = ($_POST['force_allow'] ?? '0') === '1';

if (!$class_id || !$student_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing data']);
    exit;
}

try {
    // 1. Get Class Details
    $stmt = $pdo->prepare("
        SELECT c.*, s.name as subject_name 
        FROM classes c
        JOIN subjects s ON c.subject_id = s.id
        WHERE c.id = ? AND c.tenant_id = ?
    ");
    $stmt->execute([$class_id, $tenant_id]);
    $class = $stmt->fetch();
    
    if (!$class) {
        echo json_encode(['status' => 'error', 'message' => 'Class not found']);
        exit;
    }

    // 2. Get Student Details
    $stmt = $pdo->prepare("SELECT id, username FROM users WHERE student_id = ? AND tenant_id = ? AND role = 'student'");
    $stmt->execute([$student_id, $tenant_id]);
    $student = $stmt->fetch();

    if (!$student) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid Student ID']);
        exit;
    }

    // 3. Check if already marked today
    $stmt = $pdo->prepare("SELECT id FROM attendance WHERE class_id = ? AND user_id = ? AND tenant_id = ?");
    $stmt->execute([$class_id, $student['id'], $tenant_id]);
    if ($stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Already Scanned!']);
        exit;
    }

    // 4. Check Payment Status (Simplified logic: Must have an approved payment for this subject in the current month)
    $current_month_start = date('Y-m-01 00:00:00');
    $stmt = $pdo->prepare("
        SELECT id FROM payments 
        WHERE user_id = ? AND subject_id = ? AND status = 'approved' 
        AND created_at >= ? AND tenant_id = ?
    ");
    $stmt->execute([$student['id'], $class['subject_id'], $current_month_start, $tenant_id]);
    $has_paid = $stmt->fetch() !== false;

    // Grace Period check could be added here, but for simplicity we rely on the strict check
    // unless force_allow is true.
    
    if (!$has_paid && !$force_allow) {
        echo json_encode([
            'status' => 'unpaid',
            'student_name' => $student['username'],
            'subject_name' => $class['subject_name']
        ]);
        exit;
    }

    // 5. Mark Attendance
    // We store the payment status in the attendance record (method or a new column?)
    // Looking at schema, attendance has 'method'. I'll use 'qr_scan' but maybe I should add a 'payment_status' column if I wanted to be very detailed.
    // For now, I'll stick to the existing schema and use notifications to warn the student.
    $stmt = $pdo->prepare("INSERT INTO attendance (tenant_id, class_id, user_id, method) VALUES (?, ?, ?, 'qr_scan')");
    $stmt->execute([$tenant_id, $class_id, $student['id']]);

    // 6. If Forced Allow, send payment warning notification to student
    if (!$has_paid && $force_allow) {
        try {
            $msg = "⚠️ IMPORTANT: You were allowed into the " . $class['subject_name'] . " physical class today by the admin, even though our records show you haven't paid for this month yet. Please settle your payment before the next class to avoid being denied entry.";
            // Do NOT include tenant_id here — TenantAwarePDO injects it automatically for tables in $tenantTables
            $nStmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, 'notification')");
            $nStmt->execute([$student['id'], $msg]);
        } catch (Exception $ne) {
            error_log("Attendance notification failed: " . $ne->getMessage());
        }
    }

    echo json_encode([
        'status' => 'success', 
        'message' => 'Access Granted',
        'student_name' => $student['username']
    ]);

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
