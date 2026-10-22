<?php
require_once __DIR__ . '/auth.php';
require_login('admin');

require_once __DIR__ . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$selectedMonth = $_GET['month'] ?? date('Y-m');
$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$dateStart = $_GET['date_start'] ?? '';
$dateEnd = $_GET['date_end'] ?? '';
$dateFilterType = $_GET['date_filter'] ?? 'month';

$conditions = [];
$params = [];
$types = '';

if ($dateFilterType === 'range') {
    $selectedMonth = '';
} elseif (preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
    $conditions[] = "DATE_FORMAT(r.created_at, '%Y-%m') = ?";
    $params[] = $selectedMonth;
    $types .= 's';
} else {
    $selectedMonth = date('Y-m');
}
if ($search !== '') {
    $conditions[] = 'r.description LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}
if (in_array($status, ['Pending', 'In Progress', 'Resolved'], true)) {
    $conditions[] = 'r.status = ?';
    $params[] = $status;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
    $conditions[] = 'DATE(r.created_at) >= ?';
    $params[] = $dateStart;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
    $conditions[] = 'DATE(r.created_at) <= ?';
    $params[] = $dateEnd;
    $types .= 's';
}

$where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$sql = "SELECT r.*, u_reporter.name AS reporter_name, u_assigned.name AS assigned_name
        FROM reports r
        LEFT JOIN users u_reporter ON r.user_id = u_reporter.id
        LEFT JOIN users u_assigned ON r.assigned_to = u_assigned.id
        $where
        ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if ($types !== '') {
    $bindValues = [$types];
    foreach ($params as &$param) {
        $bindValues[] = &$param;
    }
    call_user_func_array([$stmt, 'bind_param'], $bindValues);
}
$stmt->execute();
$result = $stmt->get_result();

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

$monthLabel = $selectedMonth !== '' ? date('F Y', strtotime($selectedMonth . '-01')) : 'Custom Date Range';
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reported Cases - ' . htmlspecialchars($monthLabel) . '</title>
    <style>
        body { font-family: Arial, sans-serif; color: #1f2937; margin: 24px; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .meta { margin-bottom: 20px; color: #475569; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 8px; font-size: 11px; vertical-align: top; }
        th { background: #e2e8f0; text-align: left; }
        .empty { color: #64748b; font-style: italic; }
    </style>
</head>
<body>
    <h1>Reported Cases</h1>
    <div class="meta">Filter: <strong>' . htmlspecialchars($dateFilterType === 'range' ? 'From ' . $dateStart . ' To ' . $dateEnd : $monthLabel) . '</strong></div>
    <div class="meta">Generated: ' . date('Y-m-d H:i:s') . '</div>';
    $html .= '<div class="meta">Total Reports: <strong>' . count($rows) . '</strong></div>';
        $html .= '<div class="meta">Total Completed Status: <strong>' . count(array_filter($rows, function($row) { return $row['status'] === 'Resolved'; })) . '</strong></div>';
    $html .= '<div class="meta">Total In Progress Status: <strong>' . count(array_filter($rows, function($row) { return $row['status'] === 'In Progress'; })) . '</strong></div>';
    $html .= '<div class="meta">Total Pending Status: <strong>' . count(array_filter($rows, function($row) { return $row['status'] === 'Pending'; })) . '</strong></div>';

if (empty($rows)) {
    $html .= '<p class="empty">No reports found for the selected filter.</p>';
} else {
    $html .= '<table>
        <thead>
            <tr>
                <th>Category</th>
                <th>Reported By</th>
                <th>Floor</th>
                <th>Location</th>
                <th>Status</th>
                <th>Submitted</th>
                <th>Description</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($rows as $row) {
        $html .= '<tr>
            <td>' . htmlspecialchars($row['category']) . '</td>
            <td>' . htmlspecialchars($row['reporter_name'] ?? 'Guest') . '</td>
            <td>' . htmlspecialchars($row['floor']) . '</td>
            <td>' . htmlspecialchars($row['location']) . '</td>
            <td>' . htmlspecialchars($row['status'] === 'Resolved' ? 'Completed' : $row['status']) . '</td>
            <td>' . htmlspecialchars(date('M d, Y H:i', strtotime($row['created_at']))) . '</td>
            <td>' . htmlspecialchars($row['description']) . '</td>
        </tr>';
    }

    $html .= '</tbody></table>';
}

$html .= '</body></html>';

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$filename = 'reported_cases_' . ($selectedMonth !== '' ? str_replace('-', '_', $selectedMonth) : 'custom_range') . '.pdf';
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');
echo $dompdf->output();
exit;
