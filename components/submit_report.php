<?php
require_once __DIR__ . '/../auth.php';
require_login();
$user = current_user();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

try {
    $floor = trim($_POST['floor'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $emergencyTier = trim($_POST['emergency_tier'] ?? '');
    $status = 'Pending'; // Default status

    // Handle maintenance sub-category
    if ($category === 'Maintenance' && !empty($_POST['maintenance_type'])) {
        $mType = trim($_POST['maintenance_type']);
        $category = "Maintenance ($mType)";
    }

    if ($category === 'Double Parking') {
        if (!in_array($floor, ['Basement', 'Level 1', 'Level 2'], true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Double Parking reports are only available for the configured floors.']);
            exit;
        }
        $emergencyTier = 'Emergency';
    }

    $allowedEmergencyTiers = ['Normal', 'Urgent', 'Emergency'];
    if (!in_array($emergencyTier, $allowedEmergencyTiers, true)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid emergency tier.']);
        exit;
    }

    $duplicateSql = "SELECT id FROM reports WHERE LOWER(TRIM(floor)) = LOWER(TRIM(?)) AND LOWER(TRIM(category)) = LOWER(TRIM(?)) AND LOWER(TRIM(location)) = LOWER(TRIM(?)) AND LOWER(TRIM(description)) = LOWER(TRIM(?)) AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1";
    $duplicateStmt = $conn->prepare($duplicateSql);
    if (!$duplicateStmt) {
        throw new RuntimeException('Unable to check for duplicate reports.');
    }
    $duplicateStmt->bind_param('ssss', $floor, $category, $location, $description);
    $duplicateStmt->execute();
    $duplicateStmt->store_result();

    if ($duplicateStmt->num_rows > 0) {
        $duplicateStmt->close();
        header('Content-Type: application/json');
        http_response_code(409); // Conflict
        echo json_encode(['success' => false, 'error' => 'duplicate']);
        exit;
    }
    $duplicateStmt->close();

    // Handle file upload
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $target_dir = __DIR__ . '/../uploads/';
        if (!is_dir($target_dir)) {
            if (!mkdir($target_dir, 0777, true) && !is_dir($target_dir)) {
                throw new RuntimeException('Unable to create the upload directory.');
            }
        }
        // Create a unique filename to prevent overwrites
        $file_info = pathinfo($_FILES["photo"]["name"]);
        $extension = strtolower($file_info['extension']);
        $safe_filename = preg_replace('/[^A-Za-z0-9-]/', '_', $file_info['filename']);
        $target_file = $target_dir . $safe_filename . '_' . time() . '.' . $extension;

        $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));

        // Check if image file is a actual image or fake image
        $check = @getimagesize($_FILES['photo']['tmp_name']);
        if ($check === false) {
            throw new RuntimeException('The uploaded file is not a valid image.');
        }
        if (!move_uploaded_file($_FILES['photo']['tmp_name'], $target_file)) {
            throw new RuntimeException('Unable to save the uploaded photo.');
        }
        $photo_path = 'uploads/' . basename($target_file);
    }

    $stmt = $conn->prepare("INSERT INTO reports (floor, category, location, description, photo, emergency_tier, status, user_id, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    if (!$stmt) {
        throw new RuntimeException('Unable to prepare the report submission.');
    }
    $stmt->bind_param('sssssssi', $floor, $category, $location, $description, $photo_path, $emergencyTier, $status, $user['id']);

    if ($stmt->execute()) {
        $reportId = $stmt->insert_id;
        $stmt->close();

        // Fetch the newly created report to send back to the client
        $newReportStmt = $conn->prepare("SELECT r.*, u_reporter.name as reporter_name 
                                         FROM reports r 
                                         LEFT JOIN users u_reporter ON r.user_id = u_reporter.id 
                                         WHERE r.id = ?");
        if (!$newReportStmt) {
            throw new RuntimeException('Report was saved, but could not be loaded.');
        }
        $newReportStmt->bind_param('i', $reportId);
        $newReportStmt->execute();
        $newReport = $newReportStmt->get_result()->fetch_assoc();
        $newReportStmt->close();

        $adminEmail = $conn->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
        $adminEmail = $adminEmail->fetch_assoc()['email'] ?? null;

        $sent = send_notification_email($adminEmail, $newReport, 'New Report Submitted', $emergencyTier);

        echo json_encode(['success' => true, 'report' => $newReport]);
        exit;
    } else {
        throw new RuntimeException('Unable to save the report: ' . $stmt->error);
    }
} catch (Throwable $error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $error->getMessage()]);
}
?>