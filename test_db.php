<?php
// Read the config manually to get DB connection details
$configContent = file_get_contents('core/DatabaseManager.php');
// But DatabaseManager gets creds from platform_config.php... which is missing?
// Let's just include db.php and see what it does.
require_once 'db.php';
$stmt = $pdo->query("SELECT id, title, paper_type, pdf_path, essay_pdf_path, mcq_pdf_path FROM papers ORDER BY id DESC LIMIT 5");
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
file_put_contents('scratch_db.txt', print_r($results, true));
