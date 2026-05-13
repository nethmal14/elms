<?php
require_once __DIR__ . '/../../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Unauthorized");
}

$tenant_id = TenantContext::get()['id'];
$month = $_GET['month'] ?? date('Y-m');
$month_start = $month . '-01 00:00:00';
$month_end = date('Y-m-t 23:59:59', strtotime($month_start));

$pdo = getDB();
$zip = new ZipArchive();
$zipName = "LMS_Data_" . $month . "_" . time() . ".zip";
$zipPath = sys_get_temp_dir() . '/' . $zipName;

if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die("Could not create zip");
}

// 1. Add Payments CSV
$stmt = $pdo->prepare("
    SELECT p.created_at, u.username, u.student_id, s.name as subject_name, p.status
    FROM payments p
    JOIN users u ON p.user_id = u.id
    JOIN subjects s ON p.subject_id = s.id
    WHERE p.tenant_id = ? AND p.created_at BETWEEN ? AND ?
");
$stmt->execute([$tenant_id, $month_start, $month_end]);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csvContent = "Date,Student Name,Student ID,Subject,Status\n";
foreach ($payments as $p) {
    $csvContent .= "{$p['created_at']},\"{$p['username']}\",{$p['student_id']},\"{$p['subject_name']}\",{$p['status']}\n";
}
$zip->addFromString("payments_{$month}.csv", $csvContent);

// 2. Add Past Papers
$stmt = $pdo->prepare("SELECT title, pdf_path FROM papers WHERE tenant_id = ? AND created_at BETWEEN ? AND ?");
$stmt->execute([$tenant_id, $month_start, $month_end]);
$papers = $stmt->fetchAll();
foreach ($papers as $p) {
    $fullPath = __DIR__ . '/../../' . $p['pdf_path'];
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, "papers/" . basename($p['pdf_path']));
    }
}

// 3. Add Marked Papers
$stmt = $pdo->prepare("
    SELECT ps.marked_pdf_path, u.username, pp.title 
    FROM paper_submissions ps
    JOIN users u ON ps.user_id = u.id
    JOIN papers pp ON ps.paper_id = pp.id
    WHERE ps.tenant_id = ? AND ps.status = 'marked' AND ps.submitted_at BETWEEN ? AND ?
");
$stmt->execute([$tenant_id, $month_start, $month_end]);
$marked = $stmt->fetchAll();
foreach ($marked as $m) {
    if ($m['marked_pdf_path']) {
        $fullPath = __DIR__ . '/../../' . $m['marked_pdf_path'];
        if (file_exists($fullPath)) {
            $zip->addFile($fullPath, "marked_papers/" . $m['username'] . "_" . basename($m['marked_pdf_path']));
        }
    }
}

// 4. Add Class Notes
$stmt = $pdo->prepare("SELECT notes_pdf FROM classes WHERE tenant_id = ? AND start_time BETWEEN ? AND ? AND notes_pdf IS NOT NULL");
$stmt->execute([$tenant_id, $month_start, $month_end]);
$notes = $stmt->fetchAll();
foreach ($notes as $n) {
    $fullPath = __DIR__ . '/../../' . $n['notes_pdf'];
    if (file_exists($fullPath)) {
        $zip->addFile($fullPath, "notes/class_" . basename($n['notes_pdf']));
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
