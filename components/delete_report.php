<?php
require_once __DIR__ . '/../auth.php';
require_login('admin');

// Security: Only full admins can delete reports
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: ../admindashboard.php?view=reports&error=permission_denied");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    $stmt = $conn->prepare(
        "SELECT r.photo, r.category, u.email AS reporter_email
         FROM reports r
         LEFT JOIN users u ON u.id = r.user_id
         WHERE r.id = ?"
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $report = $stmt->get_result()->fetch_assoc();
    if ($report) {
      if (!empty($report['photo'])) {
        if (!preg_match('~\Auploads/([a-f0-9]{32}\.(?:jpg|png|gif|webp))\z~i', $report['photo'], $matches)) {
            header("Location: ../admindashboard.php?view=reports&error=invalid_photo_path");
            exit;
        }

        $photoFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . $matches[1];
        if (is_file($photoFile) && !unlink($photoFile)) {
            header("Location: ../admindashboard.php?view=reports&error=photo_delete_failed");
            exit;
        }
      }
    }
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->bind_param('i', $id);
    $deleted = $stmt->execute() && $stmt->affected_rows === 1;
    $stmt->close();

    if ($deleted && $report && !empty($report['reporter_email'])) {
        $sent = send_notification_email($report['reporter_email'], null, $report['category'], 'Deleted', null);
        if (!$sent) {
            error_log('Unable to send report deletion notification for report ID ' . $id);
        }
    }
}

header("Location: ../admindashboard.php?view=reports&deleted=1");
exit;