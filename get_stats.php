<?php
require_once 'auth.php';
require_login('admin');

$date = $_GET['date'] ?? date('Y-m-d');
$timestamp = strtotime($date);

// Today count
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM reports WHERE DATE(created_at) = ?");
$stmt->bind_param('s', $date);
$stmt->execute();
$today = $stmt->get_result()->fetch_assoc()['count'];

// Week count (Monday to Sunday)
$dayOfWeek = date('w', $timestamp);
$diffToMonday = ($dayOfWeek == 0) ? -6 : 1 - $dayOfWeek;
$monday = date('Y-m-d', strtotime("$diffToMonday days", $timestamp));
$sunday = date('Y-m-d', strtotime("+6 days", strtotime($monday)));
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM reports WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->bind_param('ss', $monday, $sunday);
$stmt->execute();
$week = $stmt->get_result()->fetch_assoc()['count'];

// Month count
$month = date('m', $timestamp);
$year = date('Y', $timestamp);
$stmt = $conn->prepare("SELECT COUNT(*) AS count FROM reports WHERE MONTH(created_at) = ? AND YEAR(created_at) = ?");
$stmt->bind_param('ss', $month, $year);
$stmt->execute();
$monthCount = $stmt->get_result()->fetch_assoc()['count'];

header('Content-Type: application/json');
echo json_encode([
    'today' => $today,
    'week' => $week,
    'month' => $monthCount,
    'weekStart' => $monday
]);