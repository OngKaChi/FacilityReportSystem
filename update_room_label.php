<?php
require_once 'auth.php';
require_login('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$floor = trim($_POST['floor'] ?? '');
$room_id = trim($_POST['room_id'] ?? '');
$currentLabel = trim($_POST['current_label'] ?? '');
$label = trim($_POST['label'] ?? '');

if ($floor === '' || $room_id === '' || $currentLabel === '' || $label === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Floor, room, current label, and new label are required.']);
    exit;
}

try {
    $conn->begin_transaction();

    $reportStmt = $conn->prepare('UPDATE reports SET location = ? WHERE floor = ? AND location = ?');
    if (!$reportStmt) {
        throw new RuntimeException('Unable to prepare reported-case update.');
    }
    $reportStmt->bind_param('sss', $label, $floor, $currentLabel);
    if (!$reportStmt->execute()) {
        throw new RuntimeException('Unable to update reported cases: ' . $reportStmt->error);
    }
    $reportStmt->close();

    $roomStmt = $conn->prepare('INSERT INTO facility_rooms (floor, room_id, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = ?');
    if (!$roomStmt) {
        throw new RuntimeException('Unable to prepare room label update.');
    }
    $roomStmt->bind_param('ssss', $floor, $room_id, $label, $label);
    if (!$roomStmt->execute()) {
        throw new RuntimeException('Unable to update the room label: ' . $roomStmt->error);
    }
    $roomStmt->close();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Throwable $error) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
