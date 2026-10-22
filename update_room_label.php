<?php
require_once 'auth.php';
require_login('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $floor = $_POST['floor'] ?? '';
    $room_id = $_POST['room_id'] ?? '';
    $label = $_POST['label'] ?? '';

    if ($floor && $room_id && $label) {
        $stmt = $conn->prepare("INSERT INTO facility_rooms (floor, room_id, label) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE label = ?");
        $stmt->bind_param('ssss', $floor, $room_id, $label, $label);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        $stmt->close();
        exit;
    }
}
echo json_encode(['success' => false]);