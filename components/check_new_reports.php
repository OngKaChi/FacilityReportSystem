<?php
require_once __DIR__ . '/../auth.php';
require_login('admin');

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$latestReportResult = $conn->query('SELECT COALESCE(MAX(id), 0) AS latest_id FROM reports');
if (!$latestReportResult) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to check for new reports.']);
    exit;
}

echo json_encode([
    'success' => true,
    'latestReportId' => (int)$latestReportResult->fetch_assoc()['latest_id']
]);
