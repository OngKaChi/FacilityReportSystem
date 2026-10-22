<?php
require_once __DIR__ . '/../auth.php';
require_login('admin');

// Security: Only full admins can delete reports
if ($_SESSION['user']['role'] !== 'admin') {
    header("Location: admindashboard.php?view=reports&error=permission_denied");
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // Optional: You might want to delete the associated photo from the 'uploads/' folder
    // $stmt = $conn->prepare("SELECT photo FROM reports WHERE id = ?");
    // $stmt->bind_param('i', $id);
    // $stmt->execute();
    // if ($report = $stmt->get_result()->fetch_assoc()) {
    //     if (!empty($report['photo']) && file_exists($report['photo'])) {
    //         unlink($report['photo']);
    //     }
    // }

    $stmt = $conn->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
}

header("Location: admindashboard.php?view=reports&deleted=1");
exit;