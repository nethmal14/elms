<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/TenantContext.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$pdo = getDB();

$submission_id = $_POST['submission_id'] ?? null;
$marks = $_POST['marks'] ?? null;

if (!$submission_id || $marks === null || !isset($_FILES['marked_pdf'])) {
    echo json_encode(['success' => false, 'message' => 'Missing data']);
    exit;
}

try {
    // Save the PDF
    $marked_filename = 'marked_' . time() . '_' . uniqid() . '.pdf';
    $upload_dir = TenantContext::getUploadDir('marked/');
    $upload_path = $upload_dir . '/' . $marked_filename;

    if (move_uploaded_file($_FILES['marked_pdf']['tmp_name'], $upload_path)) {
        // Update DB
        $stmt = $pdo->prepare("UPDATE paper_submissions SET essay_marks = ?, total_marks = (IFNULL(mcq_score, 0) + ?), marked_pdf_path = ?, status = 'marked', essay_status = 'marked' WHERE id = ?");
        $stmt->execute([$marks, $marks, $marked_filename, $submission_id]);
        
        // Notify student
        $submission = $pdo->prepare("SELECT user_id, paper_id FROM paper_submissions WHERE id = ?");
        $submission->execute([$submission_id]);
        $subData = $submission->fetch();
        
        if ($subData) {
            $paper = $pdo->prepare("SELECT title FROM papers WHERE id = ?");
            $paper->execute([$subData['paper_id']]);
            $paperTitle = $paper->fetchColumn();
            
            $msg = "Your paper '$paperTitle' has been marked. Marks: $marks";
            $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)")->execute([$subData['user_id'], $msg]);
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
