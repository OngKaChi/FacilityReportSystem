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
    $stmt = $conn->prepare("SELECT photo FROM reports WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    if ($report = $stmt->get_result()->fetch_assoc()) {
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
    $stmt->execute();
    $stmt->close();
}

header("Location: ../admindashboard.php?view=reports&deleted=1");
exit;