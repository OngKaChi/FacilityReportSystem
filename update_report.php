<?php
require_once 'auth.php';
require_login('admin');

header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id'])) {
    header('Location: admindashboard.php');
    exit;
}

$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$category = isset($_POST['category']) ? trim($_POST['category']) : '';
$location = isset($_POST['location']) ? trim($_POST['location']) : '';
$floor = isset($_POST['floor']) ? trim($_POST['floor']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Fetch the user_id of the report before updating
$reportUserId = null;
$currentStatus = null;
$reportAssignedTo = null; // Added to fetch assigned_to
$originalReportStmt = $conn->prepare("SELECT user_id, status, assigned_to FROM reports WHERE id = ?");
$originalReportStmt->bind_param('i', $id);
$originalReportStmt->execute();
$originalReportResult = $originalReportStmt->get_result();
if ($originalReport = $originalReportResult->fetch_assoc()) {
    $reportUserId = $originalReport['user_id'];
    $currentStatus = $originalReport['status'];
    $reportAssignedTo = $originalReport['assigned_to']; // Fetched assigned_to
}
$originalReportStmt->close();

$reportUserEmail = null;
$reportUserEmailStmt = $conn->prepare("SELECT u.email FROM reports r JOIN users u ON r.user_id = u.id WHERE r.id = ?");
$reportUserEmailStmt->bind_param('i', $id);
$reportUserEmailStmt->execute();
$reportUserEmailResult = $reportUserEmailStmt->get_result();
if ($reportUserEmailRow = $reportUserEmailResult->fetch_assoc()) {
    $reportUserEmail = $reportUserEmailRow['email'];
}
$reportUserEmailStmt->close();

// Prevent editing if already resolved
if ($currentStatus === 'Resolved') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'already_resolved']);
    exit;
}

if ($id <= 0 || $description === '' || $category === '') {
    header('Location: admindashboard.php?updated=0');
    exit;
}

$query = "UPDATE reports SET description = ?, category = ?, location = ?, floor = ?, status = ? WHERE id = ?";

$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param('sssssi', $description, $category, $location, $floor, $status, $id);
    $stmt->execute();

    // If the report is resolved and we have a user_id, check achievements
    if ($status === 'Resolved' && $reportUserId !== null) {
        check_user_achievements($reportUserId);
    }

    // Fetch the updated report data to send back
    $updatedReportStmt = $conn->prepare("SELECT r.*, u_assigned.name as assigned_name, u_reporter.name as reporter_name 
                                         FROM reports r 
                                         LEFT JOIN users u_assigned ON r.assigned_to = u_assigned.id 
                                         LEFT JOIN users u_reporter ON r.user_id = u_reporter.id 
                                         WHERE r.id = ?");
    $updatedReportStmt->bind_param('i', $id);
    $updatedReportStmt->execute();
    $updatedReport = $updatedReportStmt->get_result()->fetch_assoc();
    $updatedReportStmt->close();

    $stmt->close();
    
    echo json_encode(['success' => true, 'report' => $updatedReport]);
    $sent = send_notification_email($reportUserEmail, null, $category, $status, null);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database statement failed.']);
}

exit;
