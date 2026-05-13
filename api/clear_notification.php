<?php
require_once __DIR__ . '/../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$u_id = $_SESSION['user_id'];
$notif_id = $_POST['id'] ?? null;

if (!$notif_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing ID']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare("UPDATE notifications SET is_cleared = 1 WHERE id = ? AND user_id = ?");
$stmt->execute([$notif_id, $u_id]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Notification not found or already cleared']);
}
