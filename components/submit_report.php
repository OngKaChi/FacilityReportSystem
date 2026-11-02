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

    if (($_POST['confirm_possible_duplicate'] ?? '') !== '1') {
        $duplicateSql = "SELECT id, category, floor, location, status, created_at
                         FROM reports
                         WHERE LOWER(TRIM(floor)) = LOWER(TRIM(?))
                           AND LOWER(TRIM(category)) = LOWER(TRIM(?))
                           AND LOWER(TRIM(location)) = LOWER(TRIM(?))
                           AND status IN ('Pending', 'In Progress')
                           AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
                         ORDER BY created_at DESC
                         LIMIT 3";
        $duplicateStmt = $conn->prepare($duplicateSql);
        if (!$duplicateStmt) {
            throw new RuntimeException('Unable to check for similar reports.');
        }
        $duplicateStmt->bind_param('sss', $floor, $category, $location);
        if (!$duplicateStmt->execute()) {
            throw new RuntimeException('Unable to check for similar reports.');
        }
        $duplicateResult = $duplicateStmt->get_result();
        $possibleDuplicates = [];
        while ($duplicate = $duplicateResult->fetch_assoc()) {
            $possibleDuplicates[] = [
                'category' => $duplicate['category'],
                'floor' => $duplicate['floor'],
                'location' => $duplicate['location'],
                'status' => $duplicate['status'],
                'created_at' => $duplicate['created_at'],
            ];
        }
        $duplicateStmt->close();

        if ($possibleDuplicates) {
            http_response_code(409);
            echo json_encode([
                'success' => false,
                'error' => 'possible_duplicate',
                'matches' => $possibleDuplicates,
            ]);
            exit;
        }
    }

    // Never create a report with an empty photo path when the required upload fails.
    $photoFile = $_FILES['photo'] ?? null;
    if (!$photoFile || !isset($photoFile['error'])) {
        throw new RuntimeException('Photo upload was not received. Please choose the image again and retry.');
    }
    if ($photoFile['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The photo exceeds the server upload limit. Please use a smaller image.',
            UPLOAD_ERR_FORM_SIZE => 'The photo exceeds the allowed upload size.',
            UPLOAD_ERR_PARTIAL => 'The photo upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please attach a photo before submitting the report.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded photo.',
            UPLOAD_ERR_EXTENSION => 'A server extension stopped the photo upload.'
        ];
        throw new RuntimeException($uploadErrors[$photoFile['error']] ?? 'The photo could not be uploaded.');
    }

    $imageInfo = @getimagesize($photoFile['tmp_name']);
    $imageExtensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp'
    ];
    $mimeType = $imageInfo['mime'] ?? '';
    if ($imageInfo === false || !isset($imageExtensions[$mimeType])) {
        throw new RuntimeException('Please upload a valid JPEG, PNG, GIF, or WebP image.');
    }

    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir) && !mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
        throw new RuntimeException('Unable to create the upload directory.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $imageExtensions[$mimeType];
    $targetFile = $targetDir . $filename;
    if (!move_uploaded_file($photoFile['tmp_name'], $targetFile)) {
        throw new RuntimeException('Unable to save the uploaded photo.');
    }
    $photo_path = 'uploads/' . $filename;

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

        $sent = send_notification_email($adminEmail, $reportId, $category, 'the Report List', $emergencyTier);

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