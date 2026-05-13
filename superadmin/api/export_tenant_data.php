<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../SuperAdminHelper.php';

// Auth check for superadmin
session_start();
if (!isset($_SESSION['superadmin_id'])) {
    die("Unauthorized");
}

$tenant_id = $_GET['tenant_id'] ?? null;
$month = $_GET['month'] ?? date('Y-m');
$month_start = $month . '-01 00:00:00';
$month_end = date('Y-m-t 23:59:59', strtotime($month_start));

if (!$tenant_id) die("Missing Tenant ID");

$pdo = getDB();

// Fetch tenant details to get name
$tStmt = $pdo->prepare("SELECT name FROM tenants WHERE id = ?");
$tStmt->execute([$tenant_id]);
$tenant = $tStmt->fetch();
if (!$tenant) die("Tenant not found");

$zip = new ZipArchive();
$zipName = "Full_Backup_" . preg_replace('/[^a-z0-9]/i', '_', $tenant['name']) . "_" . $month . ".zip";
$zipPath = sys_get_temp_dir() . '/' . $zipName;

if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die("Could not create zip");
}

// Function to add table data as CSV to ZIP
function addTableToZip($zip, $pdo, $tableName, $tenant_id, $month_start, $month_end, $dateColumn = 'created_at') {
    // Check if column exists for filtering
    $check = $pdo->query("SHOW COLUMNS FROM `$tableName` LIKE '$dateColumn'");
    $hasDate = $check->fetch() !== false;
    
    $query = "SELECT * FROM `$tableName` WHERE tenant_id = ?";
    $params = [$tenant_id];
    
    if ($hasDate) {
        $query .= " AND `$dateColumn` BETWEEN ? AND ?";
        $params[] = $month_start;
        $params[] = $month_end;
    }
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($rows)) return;
    
    $output = fopen('php://temp', 'r+');
    fputcsv($output, array_keys($rows[0]));
    foreach ($rows as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $zip->addFromString("database/{$tableName}.csv", stream_get_contents($output));
    fclose($output);
}

// Tables to export
$tables = [
    'users' => 'created_at',
    'subjects' => 'id', // Just export all if no date
    'grades' => 'id',
    'classes' => 'start_time',
    'attendance' => 'attended_at',
    'payments' => 'created_at',
    'notifications' => 'created_at',
    'papers' => 'created_at',
    'paper_submissions' => 'submitted_at',
    'recordings' => 'id'
];

foreach ($tables as $table => $dateCol) {
    addTableToZip($zip, $pdo, $table, $tenant_id, $month_start, $month_end, $dateCol);
}

// Add files
$tenantDir = __DIR__ . '/../../uploads/' . $tenant_id;
if (is_dir($tenantDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tenantDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file) {
        if (!$file->isDir()) {
            $filePath = $file->getRealPath();
            $relativePath = 'files/' . substr($filePath, strlen($tenantDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }
    }
}

$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Content-Length: ' . filesize($zipPath));
readfile($zipPath);
unlink($zipPath);
exit;
