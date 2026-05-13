<?php
require_once __DIR__ . '/../../core/DatabaseManager.php';
require_once __DIR__ . '/../../core/TenantContext.php';

session_start();
if (!isset($_SESSION['admin_id'])) {
    die('Unauthorized');
}

$pdo = DatabaseManager::getTenantDB();
$tenant_id = TenantContext::getTenantId();

$class_id = $_GET['class_id'] ?? null;

if (!$class_id) {
    die('Missing class ID');
}

// Get class info for filename
$stmt = $pdo->prepare("SELECT title, start_time FROM classes WHERE id = ? AND tenant_id = ?");
$stmt->execute([$class_id, $tenant_id]);
$class = $stmt->fetch();

if (!$class) {
    die('Class not found');
}

$filename = "attendance_" . preg_replace('/[^a-zA-Z0-9]/', '_', $class['title']) . "_" . date('Ymd', strtotime($class['start_time'])) . ".csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

fputcsv($output, ['Student Name', 'Student ID', 'Attended At', 'Method']);

$stmt = $pdo->prepare("
    SELECT u.username, u.student_id, a.attended_at, a.method
    FROM attendance a
    JOIN users u ON a.user_id = u.id
    WHERE a.class_id = ? AND a.tenant_id = ?
    ORDER BY a.attended_at ASC
");
$stmt->execute([$class_id, $tenant_id]);

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['username'],
        $row['student_id'],
        $row['attended_at'],
        $row['method']
    ]);
}

fclose($output);
exit;
