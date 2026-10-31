<?php
require_once 'auth.php';
require_login('admin');

if (isset($_GET['check_new_reports'])) {
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
    exit;
}

require_once __DIR__ . '/includes/report_read_state.php';
require_once __DIR__ . '/includes/report_filters.php';

$todayCount = 0;
$latestReportResult = $conn->query('SELECT COALESCE(MAX(id), 0) AS latest_id FROM reports');
if (!$latestReportResult) {
    throw new RuntimeException('Unable to load report update state.');
}
$latestReportId = (int)$latestReportResult->fetch_assoc()['latest_id'];
$view = $_GET['view'] ?? 'dashboard';
$reportsPerPage = 10;
$currentPage = max(1, (int)($_GET['page'] ?? 1));

$filters = getReportFilters($_GET);
$selectedMonth = $filters['selectedMonth'];
$search = $filters['search'];
$status = $filters['status'];
$dateStart = $filters['dateStart'];
$dateEnd = $filters['dateEnd'];
$dateFilterType = $filters['dateFilterType'];

$filterClause = buildReportFilterClause($filters);
$conditions = $filterClause['where'];
$params = $filterClause['params'];
$types = $filterClause['types'];

// Fetch floor statistics for the map visualization
$floorList = ['Rooftop', 'Level 6', 'Level 5', 'Level 4', 'Level 3', 'Level 2', 'Level 1', 'Ground Floor', 'Basement'];
$floorStats = [];
foreach ($floorList as $f) { $floorStats[$f] = 0; }

$fStatsQuery = $conn->query("SELECT floor, COUNT(*) as count FROM reports WHERE status != 'Resolved' GROUP BY floor");
while ($row = $fStatsQuery->fetch_assoc()) {
    $floorStats[$row['floor']] = (int)$row['count'];
}

$usersResult = null;
if ($view === 'users') {
    $usersResult = $conn->query("SELECT u.*, 
        (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) as report_count,
        (SELECT GROUP_CONCAT(plate_number SEPARATOR ', ') FROM user_car_plates ucp WHERE ucp.user_id = u.id) as car_plates
        FROM users u ORDER BY created_at DESC");
}

$weekCount = 0;
$monthCount = 0;
$recentReports = null;
$categoryCounts = [];
$topFaults = [];
$issueDataByDate = [];
$barLabels = [];
$barData = [];
$statusCounts = [
    'pending' => 0,
    'in progress' => 0,
    'resolved' => 0,
    'other' => 0
];

$todayResult = $conn->query("SELECT COUNT(*) AS count FROM reports WHERE DATE(created_at) = CURDATE()");
if ($todayResult) {
    $todayCount = $todayResult->fetch_assoc()['count'];
}
$weekResult = $conn->query("SELECT COUNT(*) AS count FROM reports WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)");
if ($weekResult) {
    $weekCount = $weekResult->fetch_assoc()['count'];
}
$monthResult = $conn->query("SELECT COUNT(*) AS count FROM reports WHERE YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())");
if ($monthResult) {
    $monthCount = $monthResult->fetch_assoc()['count'];
}

$where = $conditions;

$totalReports = 0;
$countStatement = $conn->prepare("SELECT COUNT(*) AS total FROM reports r$where");
if ($types !== '') {
    $bindValues = [$types];
    foreach ($params as &$param) {
        $bindValues[] = &$param;
    }
    call_user_func_array([$countStatement, 'bind_param'], $bindValues);
}
$countStatement->execute();
if ($countStatement) {
    $totalReports = (int)$countStatement->get_result()->fetch_assoc()['total'];
    $countStatement->close();
}
$totalPages = max(1, (int)ceil($totalReports / $reportsPerPage));
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $reportsPerPage;

if ($view === 'reports') {
    $reportsSql = "SELECT r.*, u_assigned.name as assigned_name, u_reporter.name as reporter_name 
                   FROM reports r 
                   LEFT JOIN users u_assigned ON r.assigned_to = u_assigned.id 
                   LEFT JOIN users u_reporter ON r.user_id = u_reporter.id
                   $where
                   ORDER BY r.created_at DESC 
                   LIMIT ? OFFSET ?";

    $reportsStatement = $conn->prepare($reportsSql);
    $limitValue = (int) $reportsPerPage;
    $offsetValue = (int) $offset;
    $bindValues = [$types . 'ii'];
    foreach ($params as &$param) {
        $bindValues[] = &$param;
    }
    $bindValues[] = &$limitValue;
    $bindValues[] = &$offsetValue;
    call_user_func_array([$reportsStatement, 'bind_param'], $bindValues);
    $reportsStatement->execute();
    $reportsResult = $reportsStatement->get_result();
} else {
    $reportsResult = $conn->query("SELECT r.*, u_assigned.name as assigned_name, u_reporter.name as reporter_name 
                                   FROM reports r 
                                   LEFT JOIN users u_assigned ON r.assigned_to = u_assigned.id 
                                   LEFT JOIN users u_reporter ON r.user_id = u_reporter.id 
                                   ORDER BY r.created_at DESC LIMIT 10");
}

if ($reportsResult) {
    $recentReports = $reportsResult;
}

$helpers = [];
$helperRes = $conn->query("SELECT id, name FROM users WHERE role = 'helper'");
while($h = $helperRes->fetch_assoc()) $helpers[] = $h;

$categoryResult = $conn->query("SELECT category, COUNT(*) AS count FROM reports GROUP BY category ORDER BY count DESC");
while ($row = $categoryResult->fetch_assoc()) {
    $categoryCounts[] = $row;
}

$topResult = $conn->query("SELECT category, COUNT(*) AS count FROM reports WHERE DATE(created_at) = CURDATE() GROUP BY category ORDER BY count DESC LIMIT 3");
while ($row = $topResult->fetch_assoc()) {
    $topFaults[] = $row;
}

$emergencyCases = [];
if ($view === 'dashboard') {
    $emergencyCasesResult = $conn->query("
        SELECT r.*, u_assigned.name AS assigned_name, u_reporter.name AS reporter_name
        FROM reports r
        LEFT JOIN users u_assigned ON r.assigned_to = u_assigned.id
        LEFT JOIN users u_reporter ON r.user_id = u_reporter.id
        WHERE emergency_tier = 'emergency'
          AND r.created_at >= CURDATE()
          AND r.created_at < CURDATE() + INTERVAL 1 DAY
          AND r.status != 'Resolved'
        ORDER BY r.created_at DESC
    ");
    if (!$emergencyCasesResult) {
        throw new RuntimeException('Unable to load today\'s emergency cases.');
    }
    while ($row = $emergencyCasesResult->fetch_assoc()) {
        $emergencyCases[] = $row;
    }
}

$statusResult = $conn->query("SELECT LOWER(status) AS status, COUNT(*) AS count FROM reports GROUP BY LOWER(status)");
while ($row = $statusResult->fetch_assoc()) {
    $key = trim($row['status']);
    if (isset($statusCounts[$key])) {
        $statusCounts[$key] = $row['count'];
    } else {
        $statusCounts['other'] += $row['count'];
    }
}

$dailyCounts = [];
// Prepare daily counts for the past 90 days so older weeks can be selected
for ($i = 89; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-{$i} days"));
  $dailyCounts[$date] = 0;
}
$dailyResult = $conn->query("SELECT DATE(created_at) AS day, COUNT(*) AS count FROM reports WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 89 DAY) GROUP BY day ORDER BY day");
while ($row = $dailyResult->fetch_assoc()) {
    $dailyCounts[$row['day']] = $row['count'];
}
$weekDates = array_slice(array_keys($dailyCounts), -7);
foreach ($weekDates as $date) {
    $barLabels[] = date('D', strtotime($date));
    $barData[] = $dailyCounts[$date];
}

$issueDateResult = $conn->query("SELECT DATE(created_at) AS report_date, category, COUNT(*) AS count FROM reports GROUP BY report_date, category ORDER BY report_date, category");
while ($row = $issueDateResult->fetch_assoc()) {
    $issueDataByDate[$row['report_date']][$row['category']] = (int) $row['count'];
}

?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Head of AFM Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    /* Clean card system for a minimal, modern look */
    :root { --card-bg: #ffffff; --muted: #6b7280; --card-radius: 1rem; }
    .dark { --card-bg: #0f172a; --muted: #9ca3af; }
    .card {
      background: linear-gradient(180deg, rgba(255,255,255,0.98) 0%, rgba(248,250,252,0.98) 100%);
      border-radius: 1.5rem;
      padding: 1.25rem;
      box-shadow: 0 10px 30px rgba(15,23,42,0.08);
      border: 1px solid rgba(148,163,184,0.18);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    .dark .card {
      background: linear-gradient(180deg, rgba(15,23,42,0.96) 0%, rgba(15,23,42,0.98) 100%);
      border-color: rgba(148,163,184,0.18);
      box-shadow: 0 10px 30px rgba(15,23,42,0.22);
    }
    .card:hover { transform: translateY(-2px); }
    .card--stat { display:flex; flex-direction:column; gap:0.5rem; }
    .card--stat p.title { font-size:0.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:0.12em; }
    .card--stat p.value { font-size:1.9rem; font-weight:700; color:inherit; }
    .card--compact { padding:0.75rem; border-radius:0.75rem; }
    .chart-wrap { padding:1rem; background:transparent; border-radius:0.75rem; }
    .dashboard-hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 42%, #0ea5e9 100%); }
    @media (max-width: 639px) {
      .card--stat { gap:0.25rem; padding:0.75rem; min-width:0; }
      .card--stat p.title { font-size:0.58rem; letter-spacing:0.04em; line-height:1.25; }
      .card--stat p.value { font-size:1.35rem; line-height:1.2; }
      .responsive-report-table-wrapper { overflow: visible; }
      .responsive-report-table,
      .responsive-report-table tbody,
      .responsive-report-table tr,
      .responsive-report-table td { display: block; width: 100%; }
      .responsive-report-table thead { display: none; }
      .responsive-report-table tbody { display: grid; gap: 0.75rem; }
      .responsive-report-table tr {
        border: 1px solid rgb(226 232 240);
        border-radius: 0.875rem;
        padding: 0.5rem 0;
      }
      .dark .responsive-report-table tr { border-color: rgb(51 65 85); }
      .responsive-report-table td {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 0.625rem 0.875rem;
        text-align: right;
        overflow-wrap: anywhere;
      }
      .responsive-report-table td::before {
        content: attr(data-label);
        flex: 0 0 34%;
        color: rgb(100 116 139);
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-align: left;
        text-transform: uppercase;
      }
      .dark .responsive-report-table td::before { color: rgb(148 163 184); }
      .responsive-report-table td > div { justify-content: flex-end; flex-wrap: wrap; }
      .responsive-report-table td:not([data-label]) { display: block; text-align: center; }
    } 
  </style>
</head>

<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-white transition-colors duration-300">
<?php include 'components/navbar.php'; ?>
<?php include 'components/editModal.php'; ?>

<!-- All Content of Dashboard -->
<div class="flex min-h-screen">
  <!-- Sidebar (collapsible on mobile) -->
  <?php include 'components/admin_sidebar.php'; ?>

  <main class="flex-1 p-4 sm:p-6 overflow-x-hidden md:ml-32">
    <div class="max-w-7xl mx-auto space-y-6">
      <header class="dashboard-hero mb-8 overflow-hidden rounded-[2rem] border border-white/10 p-6 text-white shadow-[0_20px_50px_rgba(37,99,235,0.28)]">
        <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
          <div class="flex items-center gap-4">

            <div class="flex-1">
              <p class="text-xs font-bold uppercase tracking-[0.35em] text-blue-100/80">Dashboards / <?php echo ucfirst($view); ?></p>
              <h1 class="mt-3 text-3xl font-bold text-white">
                <?php 
                  if ($view === 'map') echo 'Facility Status Map';
                  elseif ($view === 'reports') echo 'Reported Cases';
                  elseif ($view === 'users') echo 'Registered Users';
                  else echo 'Case Log Overview';
                ?>
              </h1>
            </div>
          </div>

          <div class="grid grid-cols-3 gap-2 sm:gap-3">
            <div class="min-w-0 rounded-2xl border border-white/10 bg-white/10 p-3 sm:p-4 backdrop-blur-sm">
              <p class="text-[9px] uppercase tracking-[0.08em] text-blue-100/80 sm:text-[10px] sm:tracking-[0.2em]">Today</p>
              <p class="mt-1 text-xl font-bold sm:mt-2 sm:text-2xl"><?php echo $todayCount; ?></p>
            </div>
            <div class="min-w-0 rounded-2xl border border-white/10 bg-white/10 p-3 sm:p-4 backdrop-blur-sm">
              <p class="text-[9px] uppercase tracking-[0.08em] text-blue-100/80 sm:text-[10px] sm:tracking-[0.2em]">This week</p>
              <p class="mt-1 text-xl font-bold sm:mt-2 sm:text-2xl"><?php echo $weekCount; ?></p>
            </div>
            <div class="min-w-0 rounded-2xl border border-white/10 bg-white/10 p-3 sm:p-4 backdrop-blur-sm">
              <p class="text-[9px] uppercase tracking-[0.08em] text-blue-100/80 sm:text-[10px] sm:tracking-[0.2em]">This month</p>
              <p class="mt-1 text-xl font-bold sm:mt-2 sm:text-2xl"><?php echo $monthCount; ?></p>
            </div>
          </div>
        </div>

        <div id="latest-updates-container" class="mt-6 space-y-4">
          <?php include "components/notify.php"; ?>
        </div>     
      </header>

      <?php if (isset($_GET['updated']) && $_GET['updated'] == '1'): ?>
        <div class="rounded-3xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 p-4 text-emerald-700 dark:text-emerald-300 shadow-sm">
          Issue updated successfully.
        </div>
      <?php elseif (isset($_GET['assigned']) && $_GET['assigned'] == '1'): ?>
        <div class="rounded-3xl bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-200 dark:border-emerald-700 p-4 text-emerald-700 dark:text-emerald-300 shadow-sm">
          Issue assignment saved successfully.
        </div>
      <?php elseif (isset($_GET['error']) && $_GET['error'] == 'not_assigned'): ?>
        <div class="rounded-3xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-700 p-4 text-red-700 dark:text-red-300 shadow-sm">
          You can only edit reports that are assigned to you.
        </div>
      <?php endif; ?>

      <?php if ($view === 'map'): ?>
        <?php include 'components/facility_map.php'; ?>
      <?php elseif ($view === 'dashboard'): ?>

      <section class="grid gap-6 xl:grid-cols-[1.8fr_1fr]">
        <div class="space-y-6 order-2 xl:order-1">
          <article class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
            <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div class="dark:text-white">
                <p class="text-m uppercase tracking-[0.35em] text-slate-400">Cases Overview</p>
              </div>
              
              <div class="flex items-center gap-3">

                <!-- Calendar Picker -->
                <button onclick="changeWeek(-7)" class="px-3 py-2 bg-gray-200 dark:bg-slate-700 dark:text-white rounded hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors"><</button>
                <input type="date" id="datePicker1"
                  class="border dark:border-slate-600 rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-700 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button onclick="changeWeek(7)" class="px-3 py-2 bg-gray-200 dark:bg-slate-700 dark:text-white rounded hover:bg-gray-300 dark:hover:bg-slate-600 transition-colors">></button>



                <!-- Display Week Range -->
                <div id="weekRange"
                  class="rounded-full bg-slate-100 dark:bg-slate-700 px-5 py-3 text-sm text-slate-500 dark:text-slate-300 shadow-inner">
                  Select a date
                </div>

              </div>

            </div>

            <div class="mt-6 grid grid-cols-3 gap-2 sm:mt-8 sm:gap-4">
              <div class="card card--stat">
                <p class="title">Today</p>
                <p id="statTodayCount" class="value"><?php echo $todayCount; ?></p>
                <p id="statTodayLabel" class="hidden text-sm text-emerald-600 dark:text-emerald-400 sm:block">Based on today's reports</p>
              </div>
              <div class="card card--stat">
                <p class="title">This week</p>
                <p id="statWeekCount" class="value"><?php echo $weekCount; ?></p>
                <p id="statWeekLabel" class="hidden text-sm text-emerald-600 dark:text-emerald-400 sm:block">Count for the current week</p>
              </div>
              <div class="card card--stat">
                <p class="title">This month</p>
                <p id="statMonthCount" class="value"><?php echo $monthCount; ?></p>
                <p id="statMonthLabel" class="hidden text-sm text-slate-600 dark:text-slate-400 sm:block">Count for the current month</p>
              </div>
            </div>

            <div class="mt-8 chart-wrap card--compact">
              <canvas id="barChart"></canvas>
            </div>
          </article>

          <article class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div>
                <p class="text-m uppercase tracking-[0.35em] text-slate-400">Quick Summary</p>
              </div>
              <div class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-4 py-2 text-sm text-slate-500">
                Filter
                <span class="rounded-full bg-slate-900 px-3 py-1 text-white text-xs">3</span>
              </div>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-2 sm:gap-4">
              <div class="min-w-0 rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-3 sm:p-5">
                <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">Pending</p>
                <p class="mt-2 text-xl font-semibold text-rose-600 dark:text-rose-400 sm:mt-3 sm:text-2xl"><?php echo $statusCounts['pending']; ?></p>
              </div>
              <div class="min-w-0 rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-3 sm:p-5">
                <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">In Progress</p>
                <p class="mt-2 text-xl font-semibold text-amber-600 dark:text-amber-400 sm:mt-3 sm:text-2xl"><?php echo $statusCounts['in progress']; ?></p>
              </div>
              <div class="min-w-0 rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-3 sm:p-5">
                <p class="text-xs text-slate-500 dark:text-slate-400 sm:text-sm">Completed</p>
                <p class="mt-2 text-xl font-semibold text-emerald-600 dark:text-emerald-400 sm:mt-3 sm:text-2xl"><?php echo $statusCounts['resolved']; ?></p>
              </div>
            </div>
          </article>
        </div>

        <aside class="space-y-6 order-1 xl:order-2">
          <article class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-m uppercase tracking-[0.35em] text-slate-400">Emergency Cases (Today)</p>
              </div>
            </div>

            <div class="mt-6">
              <div class="h-[300px] overflow-y-auto rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
                <?php if ($emergencyCases): ?>
                  <div class="space-y-3">
                    <?php foreach ($emergencyCases as $case): ?>
                      <button type="button"
                        onclick="showReportDetails(JSON.parse(this.dataset.report))"
                        data-report="<?php echo htmlspecialchars(json_encode($case), ENT_QUOTES, 'UTF-8'); ?>"
                        class="block w-full rounded-2xl bg-white p-4 text-left shadow-sm transition hover:ring-2 hover:ring-rose-300 focus:outline-none focus:ring-2 focus:ring-rose-500 dark:bg-slate-800"
                        aria-label="View details for <?php echo htmlspecialchars($case['category'] ?: 'emergency report', ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="flex items-start justify-between gap-3">
                          <div class="min-w-0">
                            <p class="font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($case['category'] ?: 'Uncategorized', ENT_QUOTES, 'UTF-8'); ?></p>
                            <p class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($case['location'] ?: 'Location not specified', ENT_QUOTES, 'UTF-8'); ?></p>
                          </div>
                          <span class="shrink-0 rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-300">Emergency</span>
                        </div>
                        <div class="mt-3 flex items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400">
                          <span><?php echo htmlspecialchars($case['status'] ?: 'Unknown status', ENT_QUOTES, 'UTF-8'); ?></span>
                          <time datetime="<?php echo htmlspecialchars($case['created_at'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo date('g:i A', strtotime($case['created_at'])); ?></time>
                        </div>
                      </button>
                    <?php endforeach; ?>
                  </div>
                <?php else: ?>
                  <div class="flex h-full items-center justify-center text-center text-sm text-slate-500 dark:text-slate-400">
                    No emergency cases reported today.
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </article>

          <article class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
            <div class="flex items-center justify-between gap-4">
              <div>
                <p class="text-m uppercase tracking-[0.35em] text-slate-400">Top Recurring Cases</p>
              </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                  <label class="inline-flex items-center gap-2 rounded-3xl bg-slate-50 dark:bg-slate-700/50 px-4 py-3 text-sm text-slate-600 dark:text-slate-300 shadow-sm">
                    <span>Select day</span>
                    <input type="date" id="datePicker2"
                      class="border rounded-lg px-3 py-2 text-sm bg-white dark:bg-slate-800 dark:border-slate-600 dark:text-white" />
                  </label>
                </div>
              </div>

            <div class="mt-8 space-y-4">
              <div class="h-64 rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
                <canvas id="issueChart"></canvas>
              </div>
              <div id="topFaultsList" class="space-y-3">
                <?php if (!empty($topFaults)): ?>
                  <?php foreach ($topFaults as $fault): ?>
                    <div class="flex items-center justify-between rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
                      <span class="font-semibold dark:text-white"><?php echo htmlspecialchars($fault['category']); ?></span>
                      <span class="text-slate-500 dark:text-slate-400"><?php echo htmlspecialchars($fault['count']); ?></span>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="flex items-center justify-between rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
                    <span class="font-semibold dark:text-white">No reports yet</span>
                    <span class="text-slate-500 dark:text-slate-400">0</span>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </article>

          
        </aside>
      </section>
      <?php endif; ?>

      <?php if ($view === 'users'): ?>
      <!-- Search and Filter Section -->
      <div class="flex flex-col sm:flex-row gap-4 mb-6">
        <div class="relative flex-1">
          <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-slate-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
          </span>
          <input type="text" id="userSearch" onkeyup="filterUsers()" placeholder="Search users..." 
            class="w-full pl-10 pr-4 py-3 rounded-2xl border dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 outline-none transition-all">
        </div>
        <div class="w-full sm:w-48">
          <select id="searchType" onchange="filterUsers()" 
            class="w-full px-4 py-3 rounded-2xl border dark:border-slate-700 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-blue-500 outline-none cursor-pointer transition-all">
            <option value="name">Search by Name</option>
            <option value="plate">Search by Plate</option>
          </select>
        </div>
      </div>

      <section class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
        <div class="overflow-x-auto">
          <table class="min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
            <thead class="hidden sm:table-header-group">
              <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                <th class="py-4 px-3">Name</th>
                <th class="py-4 px-3">Email</th>
                <th class="py-4 px-3">Role</th>
                <th class="py-4 px-3">Joined Date</th>
                <th class="py-4 px-3">Reports</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
                <?php if ($usersResult && $usersResult->num_rows > 0): ?>
                    <?php while ($row = $usersResult->fetch_assoc()): ?>
                        <tr class="block sm:table-row hover:bg-slate-50 dark:hover:bg-slate-700/30 transition cursor-pointer user-row"
                            onclick='showUserDetails(<?php echo json_encode($row); ?>)'
                            data-name="<?php echo htmlspecialchars($row['name']); ?>"
                            data-plates="<?php echo htmlspecialchars($row['car_plates'] ?? ''); ?>">
                            <td class="flex sm:table-cell items-center py-2 sm:py-4 px-3 font-semibold text-slate-900 dark:text-white" data-label="Name">
                                <span class="sm:hidden w-20 text-xs text-slate-500 uppercase">Name:</span>
                                <?php echo htmlspecialchars($row['name']); ?>
                            </td>
                            <td class="flex sm:table-cell items-center py-2 sm:py-4 px-3" data-label="Email">
                                <span class="sm:hidden w-20 text-xs text-slate-500 uppercase">Email:</span>
                                <?php echo htmlspecialchars($row['email']); ?>
                            </td>
                            <td class="flex sm:table-cell items-center py-2 sm:py-4 px-3" data-label="Role">
                                <span class="sm:hidden w-20 text-xs text-slate-500 uppercase">Role:</span>
                                <span class="rounded-full px-3 py-1 text-xs <?php echo $row['role'] === 'admin' ? 'bg-rose-100 text-rose-700' : ($row['role'] === 'helper' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700'); ?>">
                                    <?php
                                    if($row['role'] === 'admin') echo 'Head of AFM';
                                    elseif($row['role'] === 'helper') echo 'AFM Staff';
                                    else echo htmlspecialchars($row['role']);
                                    ?>
                                </span>
                            </td>
                            <td class="flex sm:table-cell items-center py-2 sm:py-4 px-3" data-label="Joined">
                                <span class="sm:hidden w-20 text-xs text-slate-500 uppercase">Joined:</span>
                                <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                            </td>
                            <td class="flex sm:table-cell items-center py-2 sm:py-4 px-3" data-label="Reports">
                                <span class="sm:hidden w-20 text-xs text-slate-500 uppercase">Reports:</span>
                                <?php echo $row['report_count']; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
      <?php endif; ?>

      <?php if ($view === 'reports'): ?>
      <section class="rounded-[2rem] bg-white dark:bg-slate-800 p-6 shadow-sm dark:shadow-none dark:border dark:border-slate-700">
        <div class="flex flex-col gap-5">
          <div>
            <h2 class="mt-2 text-2xl font-semibold dark:text-white">
              <?php echo $view === 'Reports' ? 'All Reported Cases' : 'Recent Reported Cases'; ?>
            </h2>
          </div>
          <form method="get" action="admindashboard.php">
            <input type="hidden" name="view" value="reports">
            <input type="hidden" name="page" value="1">
            <div class="grid grid-cols-1 items-stretch gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(12rem,1fr)_minmax(9rem,auto)_minmax(16rem,auto)_auto_auto]">
              <div class="flex min-w-0 items-center rounded-3xl bg-slate-50 dark:bg-slate-700/50 px-4 py-2 text-sm text-slate-600 dark:text-slate-300 shadow-sm">
                <input type="text" id="reportSearch" name="search" value="<?php echo htmlspecialchars($search, ENT_QUOTES); ?>" placeholder="Search description..." class="w-full min-w-0 border-0 bg-transparent focus:outline-none dark:text-white" />
              </div>
              <select id="filterStatus" name="status" class="w-full rounded-3xl bg-slate-50 dark:bg-slate-700/50 px-4 py-2 text-sm text-slate-600 dark:text-slate-300 border-0 focus:ring-0 cursor-pointer">
                <option value="">All Status</option>
                <option value="Pending" <?php echo $status === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="In Progress" <?php echo $status === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                <option value="Resolved" <?php echo $status === 'Resolved' ? 'selected' : ''; ?>>Completed</option>
              </select>
              <div class="min-w-0 rounded-3xl bg-slate-50 dark:bg-slate-700/50 px-3 py-2 text-sm text-slate-500 dark:text-slate-300 shadow-sm">
                <div class="flex flex-wrap items-center gap-2">
                  <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase">
                    <input type="radio" name="date_filter" value="month" <?php echo $dateFilterType !== 'range' ? 'checked' : ''; ?> onchange="toggleDateFilterMode(this.value)" class="accent-blue-600">
                    <span>Month</span>
                  </label>
                  <label class="inline-flex items-center gap-2 text-xs font-semibold uppercase">
                    <input type="radio" name="date_filter" value="range" <?php echo $dateFilterType === 'range' ? 'checked' : ''; ?> onchange="toggleDateFilterMode(this.value)" class="accent-blue-600">
                    <span>From / To</span>
                  </label>
                </div>
                <div id="monthFilterWrapper" class="mt-2 <?php echo $dateFilterType === 'range' ? 'hidden' : ''; ?>">
                  <label class="flex items-center gap-2 text-xs font-semibold uppercase">
                    <span>Month:</span>
                    <input type="month" name="month" value="<?php echo htmlspecialchars($selectedMonth, ENT_QUOTES); ?>" class="bg-transparent border-0 focus:ring-0 text-xs p-0 min-w-[130px]">
                  </label>
                </div>
                <div id="rangeFilterWrapper" class="mt-2 flex flex-wrap items-center gap-2 <?php echo $dateFilterType !== 'range' ? 'hidden' : ''; ?>">
                  <label class="flex items-center gap-2 text-xs font-semibold uppercase">
                    <span>From:</span>
                    <input type="date" id="filterDateStart" name="date_start" value="<?php echo htmlspecialchars($dateStart, ENT_QUOTES); ?>" class="min-w-0 bg-transparent border-0 focus:ring-0 text-xs p-0">
                  </label>
                  <label class="flex items-center gap-2 text-xs font-semibold uppercase">
                    <span>To:</span>
                    <input type="date" id="filterDateEnd" name="date_end" value="<?php echo htmlspecialchars($dateEnd, ENT_QUOTES); ?>" class="min-w-0 bg-transparent border-0 focus:ring-0 text-xs p-0">
                  </label>
                </div>
              </div>
              <button type="submit" class="w-full rounded-3xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700">Search</button>
              <?php
                $pdfQuery = ['month' => $selectedMonth, 'view' => 'reports'];
                if ($dateFilterType === 'range') {
                  $pdfQuery['date_filter'] = 'range';
                }
                if ($search !== '') $pdfQuery['search'] = $search;
                if ($status !== '') $pdfQuery['status'] = $status;
                if ($dateStart !== '') $pdfQuery['date_start'] = $dateStart;
                if ($dateEnd !== '') $pdfQuery['date_end'] = $dateEnd;
              ?>
              <a href="generate_report_pdf.php?<?php echo htmlspecialchars(http_build_query($pdfQuery), ENT_QUOTES); ?>" target="_blank" class="w-full rounded-3xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-emerald-700 text-center">Generate PDF</a>
            </div>
          </form>
        </div>

         <div class="responsive-report-table-wrapper mt-6 overflow-x-auto">
          <table class="responsive-report-table min-w-full text-left text-sm text-slate-700 dark:text-slate-300">
            <thead>
              <tr class="border-b border-slate-200 dark:border-slate-700 text-slate-500 dark:text-slate-400">
                <th class="py-4 px-3">Issue Type</th>
                <th class="py-4 px-3">Urgency</th>
                <th class="py-4 px-3">Reported By</th>
                <th class="py-4 px-3">Reported Time</th>
                <th class="py-4 px-3">Description</th>
                <th class="py-4 px-3">Assigned To</th>
                <th class="py-4 px-3">Status</th>
                <th class="py-4 px-3">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-200 dark:divide-slate-700">
              <?php if ($recentReports && $recentReports->num_rows > 0): ?>
                <?php while ($row = $recentReports->fetch_assoc()): ?>
                  <?php
                    $rowStatus = $row['status'];
                    $statusClass = 'bg-slate-100 text-slate-700';
                    $lowerStatus = strtolower(trim($rowStatus));
                    if ($lowerStatus === 'pending') {
                        $statusClass = 'bg-amber-100 text-amber-700';
                    } elseif ($lowerStatus === 'resolved') {
                        $statusClass = 'bg-emerald-100 text-emerald-700';
                    } elseif ($lowerStatus === 'in progress' || $lowerStatus === 'inprogress') {
                        $statusClass = 'bg-sky-100 text-sky-700';
                    }
                    $isEmergency = strtolower(trim((string)($row['emergency_tier'] ?? ''))) === 'emergency';
                    $rowColorClass = $isEmergency
                        ? 'bg-red-50 dark:bg-red-900/20 hover:bg-red-100 dark:hover:bg-red-900/40'
                        : ($lowerStatus === 'resolved'
                            ? 'bg-gray-50 dark:bg-slate-700/50 hover:bg-gray-100 dark:hover:bg-slate-700'
                            : 'hover:bg-slate-50 dark:hover:bg-slate-700/30');
                    ?>

                    <tr class="relative transition report-row <?php echo $rowColorClass; ?> cursor-pointer"
                        onclick='showReportDetails(<?php echo json_encode($row); ?>)'
                        data-description="<?php echo strtolower(htmlspecialchars($row['description'], ENT_QUOTES)); ?>"
                        data-status="<?php echo htmlspecialchars($rowStatus, ENT_QUOTES); ?>"
                        data-date="<?php echo date('Y-m-d', strtotime($row['created_at'])); ?>">
                      <td class="relative py-4 px-3" data-label="Issue Type"><?php echo render_unread_report_dot((int)$row['id']); ?><span class="rounded-full bg-slate-100 dark:bg-slate-700 px-3 py-1 text-slate-700 dark:text-slate-300 text-xs"><?php echo htmlspecialchars($row['category']); ?></span></td>
                      <td class="py-4 px-3 text-xs font-bold text-slate-900 dark:text-white" data-label="Urgency"><?php echo htmlspecialchars($row['emergency_tier']); ?></td>
                      <td class="py-4 px-3 dark:text-slate-300" data-label="Reported By"><?php echo htmlspecialchars($row['reporter_name'] ?? 'Guest'); ?></td>
                      <td class="py-4 px-3 dark:text-slate-300" data-label="Reported Time"><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                      <td class="py-4 px-3 font-semibold text-slate-900 dark:text-white" data-label="Description"><?php echo htmlspecialchars($row['description']); ?></td>
                      <td class="py-4 px-3 text-xs font-medium text-blue-600 dark:text-blue-400" data-label="Assigned To"><?php echo htmlspecialchars($row['assigned_name'] ?: 'Unassigned'); ?></td>
                      <td class="py-4 px-3" data-label="Status"><span class="rounded-full <?php echo $statusClass; ?> px-3 py-1 text-xs"><?php echo $rowStatus === 'Resolved' ? 'Completed' : htmlspecialchars($rowStatus); ?></span></td>
                      <td class="py-4 px-3 text-slate-600 dark:text-slate-300 whitespace-nowrap" data-label="Action" onclick="event.stopPropagation();">
                        <div class="flex items-center gap-2">
                          <?php if ($lowerStatus !== 'resolved' && $_SESSION['user']['role'] === 'admin'): ?>
                            <button type="button" onclick="openEditModal(this)" class="rounded-full bg-slate-100 dark:bg-slate-700 px-3 py-1 text-slate-700 dark:text-slate-300 text-xs hover:bg-slate-200 dark:hover:bg-slate-600 transition"
                              data-id="<?php echo htmlspecialchars($row['id']); ?>"
                              data-description="<?php echo htmlspecialchars($row['description'], ENT_QUOTES); ?>"
                              data-category="<?php echo htmlspecialchars($row['category'], ENT_QUOTES); ?>"
                              data-location="<?php echo htmlspecialchars($row['location'], ENT_QUOTES); ?>"
                              data-floor="<?php echo htmlspecialchars($row['floor'], ENT_QUOTES); ?>"
                              data-status="<?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?>"
                              data-assigned="<?php echo htmlspecialchars($row['assigned_to'] ?? ''); ?>">
                              Edit
                            </button>
                            <a href="assign_issue.php?id=<?php echo (int)$row['id']; ?>" onclick="event.stopPropagation();" class="rounded-full bg-cyan-100 dark:bg-cyan-900/30 px-3 py-1 text-cyan-700 dark:text-cyan-300 text-xs hover:bg-cyan-200 dark:hover:bg-cyan-800 transition">Assign</a>
                            <a href="components/delete_report.php?id=<?php echo $row['id']; ?>" 
                              onclick="return confirm('Are you sure you want to permanently delete this report? This action cannot be undone.')"
                              class="rounded-full bg-rose-100 dark:bg-rose-900/30 px-3 py-1 text-rose-700 dark:text-rose-300 text-xs hover:bg-rose-200 dark:hover:bg-rose-800 transition">Delete</a>
                          <?php endif; ?>
                        </div>
                      </td>
                    </tr>
                  <?php endwhile; ?>
              <?php else: ?>
                <tr class="dark:text-slate-400">
                  <td colspan="8" class="py-8 px-3 text-center text-slate-500 dark:text-slate-400">No reports found yet.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <?php if ($view === 'reports' && $totalPages > 1): ?>
          <?php $paginationQuery = ['view' => 'reports']; ?>
          <?php if ($search !== '') $paginationQuery['search'] = $search; ?>
          <?php if ($status !== '') $paginationQuery['status'] = $status; ?>
          <?php if ($dateFilterType === 'range') { $paginationQuery['date_filter'] = 'range'; if ($dateStart !== '') $paginationQuery['date_start'] = $dateStart; if ($dateEnd !== '') $paginationQuery['date_end'] = $dateEnd; } else { $paginationQuery['date_filter'] = 'month'; if ($selectedMonth !== '') $paginationQuery['month'] = $selectedMonth; } ?>
          <nav class="mt-5 flex flex-wrap items-center justify-center gap-2" aria-label="Reported cases pages">
            <?php if ($currentPage > 1): ?>
              <?php $paginationQuery['page'] = $currentPage - 1; ?>
              <a href="admindashboard.php?<?php echo htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Previous</a>
            <?php endif; ?>

            <?php for ($page = 1; $page <= $totalPages; $page++): ?>
              <?php $paginationQuery['page'] = $page; ?>
              <?php $pageClass = $page === $currentPage ? 'bg-blue-600 text-white' : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700'; ?>
              <a href="admindashboard.php?<?php echo htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES); ?>" class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors <?php echo $pageClass; ?>" aria-current="<?php echo $page === $currentPage ? 'page' : 'false'; ?>"><?php echo $page; ?></a>
            <?php endfor; ?>

            <?php if ($currentPage < $totalPages): ?>
              <?php $paginationQuery['page'] = $currentPage + 1; ?>
              <a href="admindashboard.php?<?php echo htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES); ?>" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Next</a>
            <?php endif; ?>
          </nav>
        <?php endif; ?>
      </section>
      <?php endif; ?>

    </div>
  </main>
</div>

<!-- User Details Modal -->
<div id="detailsModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60] p-4 transition-all">
  <div class="bg-white dark:bg-slate-800 rounded-[2rem] p-8 w-full max-w-md shadow-2xl border dark:border-slate-700 relative">
    <button onclick="closeModal()" class="absolute top-6 right-6 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">
      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
    </button>
    
    <div class="text-center mb-6">
      <div id="modalInitials" class="w-20 h-20 rounded-full bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 text-3xl font-bold flex items-center justify-center mx-auto mb-4 border-4 border-white dark:border-slate-700 shadow-sm"></div>
      <h2 id="modalName" class="text-2xl font-bold text-slate-900 dark:text-white"></h2>
      <p id="modalRole" class="text-xs uppercase tracking-widest text-blue-500 font-bold mt-1"></p>
    </div>

    <div class="space-y-4">
      <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border dark:border-slate-700">
        <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Email Address</p>
        <p id="modalEmail" class="text-slate-900 dark:text-white font-medium"></p>
      </div>
      
      <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border dark:border-slate-700">
        <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Car Plate Numbers</p>
        <p id="modalPlates" class="text-slate-900 dark:text-white font-medium italic"></p>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border dark:border-slate-700">
          <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Joined Date</p>
          <p id="modalDate" class="text-slate-900 dark:text-white font-medium"></p>
        </div>
        <div class="bg-slate-50 dark:bg-slate-900/50 rounded-2xl p-4 border dark:border-slate-700">
          <p class="text-xs text-slate-400 uppercase font-bold tracking-wider mb-1">Total Reports</p>
          <p id="modalReports" class="text-slate-900 dark:text-white font-medium"></p>
        </div>
      </div>
    </div>

    <button onclick="closeModal()" class="w-full mt-8 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-4 rounded-2xl hover:opacity-90 transition-opacity">
      Close Details
    </button>
  </div>
</div>

<!-- Report Details Modal -->
<div id="reportDetailsModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-[60] p-4 backdrop-blur-sm">
  <div class="bg-white dark:bg-slate-800 rounded-3xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-hidden transform transition-all duration-300 scale-95 flex flex-col" id="reportDetailsModalBox">
    <!-- Modal Header -->
    <div class="p-6 border-b dark:border-slate-700 flex items-center justify-between bg-gray-50/50 dark:bg-slate-700/30">
      <div>
        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Report Details</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 uppercase tracking-widest">Case ID: #<span id="detailId"></span></p>
      </div>
      <button onclick="closeReportDetailsModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
      </button>
    </div>

    <!-- Modal Body -->
    <div class="p-8 overflow-y-auto custom-scrollbar flex-1">
      <div id="detailPhotoContainer" class="hidden mb-6">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Attached Image</p>
        <div class="rounded-2xl overflow-hidden shadow-sm border dark:border-slate-700">
          <img id="detailPhoto" src="" alt="Report Photo" class="w-full h-auto object-cover max-h-96">
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Status</p>
          <div id="detailStatus" class="inline-flex px-3 py-1 text-xs font-bold rounded-full"></div>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Submitted On</p>
          <p id="detailDate" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Category</p>
          <p id="detailCategory" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Emergency Tier</p>
          <p id="detailEmergencyTier" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Reported By</p>
          <p id="detailReporter" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mb-6">
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Floor</p>
          <p id="detailFloor" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
        <div class="space-y-1">
          <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Location</p>
          <p id="detailLocation" class="text-sm font-medium text-slate-700 dark:text-slate-200"></p>
        </div>
      </div>

      <div class="p-5 bg-slate-50 dark:bg-slate-900/50 rounded-2xl border dark:border-slate-700">
        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-2">Description</p>
        <p id="detailDescription" class="text-sm text-slate-600 dark:text-slate-300 leading-relaxed"></p>
      </div>
    </div>
    <div class="p-6 border-t dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/30">
      <button onclick="closeReportDetailsModal()" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-4 rounded-2xl hover:opacity-90 transition-opacity">Dismiss</button>
    </div>
  </div>
</div>

<?php include 'components/report_read_state.php'; ?>
<script>
window.seenReportsStorageKey = 'facilitySystemSeenReports_<?php echo (int)$_SESSION['user']['id']; ?>_<?php echo htmlspecialchars($_SESSION['user']['role'] ?? 'admin', ENT_QUOTES); ?>';
window.latestReportId = <?php echo $latestReportId; ?>;

// Inject helpers into global scope for the modal
window.adminHelpers = <?php echo json_encode($helpers); ?>;
window.allReportsData = <?php echo json_encode($recentReports ? $recentReports->fetch_all(MYSQLI_ASSOC) : []); ?>;
window.currentUserRole = '<?php echo $_SESSION['user']['role']; ?>';
window.allLatestUpdates = <?php echo json_encode($allLatestUpdates); ?>;
</script>

<script>
const barLabels = <?php echo json_encode($barLabels); ?>;
const barData = <?php echo json_encode($barData); ?>;
const dailyCountsByDate = <?php echo json_encode($dailyCounts); ?>;
const categoryLabels = <?php echo json_encode(array_column($categoryCounts, 'category')); ?>;
const categoryCounts = <?php echo json_encode(array_map('intval', array_column($categoryCounts, 'count'))); ?>;
const categoryColors = <?php echo json_encode(array_map(function($index) {
    $palette = ['#f97316','#0ea5e9','#22c55e','#a855f7','#facc15','#ec4899','#f87171'];
    return $palette[$index % count($palette)];
}, array_keys($categoryCounts))); ?>;
const issueDataByDate = <?php echo json_encode($issueDataByDate); ?>;

function toggleDateFilterMode(mode) {
  const monthWrapper = document.getElementById('monthFilterWrapper');
  const rangeWrapper = document.getElementById('rangeFilterWrapper');
  const monthInput = document.querySelector('input[name="month"]');
  const startInput = document.getElementById('filterDateStart');
  const endInput = document.getElementById('filterDateEnd');

  if (!monthWrapper || !rangeWrapper) {
    return;
  }

  const isRange = mode === 'range';
  monthWrapper.classList.toggle('hidden', isRange);
  rangeWrapper.classList.toggle('hidden', !isRange);

  if (monthInput) {
    monthInput.disabled = isRange;
    if (isRange) {
      monthInput.value = '';
    }
  }

  if (startInput) {
    startInput.disabled = !isRange;
    if (!isRange) {
      startInput.value = '';
    }
  }

  if (endInput) {
    endInput.disabled = !isRange;
    if (!isRange) {
      endInput.value = '';
    }
  }
}

const selectedDateFilter = document.querySelector('input[name="date_filter"]:checked');
toggleDateFilterMode(selectedDateFilter ? selectedDateFilter.value : 'month');

const barCtx = document.getElementById('barChart');

// State for interactive bar selection
let currentWeekDates = [];
let selectedBarIndex = -1;

function buildBarColors(length, selectedIndex, isDark) {
  const base = isDark ? '#0d9488' : '#0f766e';
  const highlight = isDark ? '#065f46' : '#0b5340';
  return Array.from({ length }).map((_, i) => i === selectedIndex ? highlight : base);
}

const barChart = new Chart(barCtx, {
  type: 'bar',
  data: {
    labels: barLabels || [],
    datasets: [{
      label: 'Reports',
      data: barData || [],
      backgroundColor: buildBarColors((barData || []).length, -1, document.documentElement.classList.contains('dark')),
      borderRadius: 12,
      maxBarThickness: 32
    }]
  },
  options: {
    plugins: {
      legend: { display: false },
      tooltip: {
        callbacks: {
          title: (items) => {
            const idx = items[0].dataIndex;
            const d = currentWeekDates[idx];
            return d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
          },
          label: (item) => `${item.dataset.label}: ${item.formattedValue}`
        }
      }
    },
    scales: {
      y: { beginAtZero: true, grid: { color: '#e2e8f0' } },
      x: { grid: { display: false } }
    },
    onHover: (evt, elements) => { evt.native.target.style.cursor = elements.length ? 'pointer' : 'default'; }
  }
});

function updateBarColors() {
  barChart.data.datasets[0].backgroundColor = buildBarColors(barChart.data.datasets[0].data.length, selectedBarIndex, document.documentElement.classList.contains('dark'));
  barChart.update();
}

// Click selects a bar and sets date picker to that day
barCtx.addEventListener('click', function(evt) {
  const points = barChart.getElementsAtEventForMode(evt, 'nearest', { intersect: true }, true);
  if (points.length) {
    selectedBarIndex = points[0].index;
    const targetDate = currentWeekDates[selectedBarIndex];
    if (targetDate) {
      datePicker1.value = targetDate;
      datePicker1.dispatchEvent(new Event('change'));
    }
    updateBarColors();
  }
});

const datePicker1 = document.getElementById("datePicker1");
const weekRange = document.getElementById("weekRange");

// Set default date to today
const today = new Date();
const todayString = today.toISOString().slice(0, 10);
datePicker1.value = todayString;

datePicker1.addEventListener("change", function () {
  const selectedDate = new Date(this.value);

  // Get Monday
  const day = selectedDate.getDay();
  const diff = selectedDate.getDate() - day + (day === 0 ? -6 : 1);
  const monday = new Date(selectedDate);
  monday.setDate(diff);

  // Get Sunday
  const sunday = new Date(monday);
  sunday.setDate(monday.getDate() + 6);

  const options = { month: 'short', day: 'numeric', year: 'numeric' };
  const start = monday.toLocaleDateString('en-US', options);
  const end = sunday.toLocaleDateString('en-US', options);

  weekRange.innerText = `${start} - ${end}`;
  console.debug('datePicker1.change -> value:', this.value, 'weekStart:', monday.toISOString().slice(0,10), 'weekEnd:', sunday.toISOString().slice(0,10));
  updateBarChartForDate(this.value);
  updateMetricCards(this.value);
});

datePicker1.dispatchEvent(new Event("change"));

function changeWeek(days) {
  let current = new Date(datePicker1.value || new Date());
  current.setDate(current.getDate() + days);

  datePicker1.valueAsDate = current;
  console.debug('changeWeek -> set datePicker1 to', current.toISOString().slice(0,10));
  datePicker1.dispatchEvent(new Event("change"));
}

function getWeekDates(date) {
  const selectedDate = new Date(date);
  const day = selectedDate.getDay();
  const diff = selectedDate.getDate() - day + (day === 0 ? -6 : 1);
  const monday = new Date(selectedDate);
  monday.setDate(diff);

  const dates = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(monday);
    d.setDate(monday.getDate() + i);
    dates.push(d.toISOString().slice(0, 10));
  }
  return dates;
}

function updateBarChartForDate(dateString) {
  const weekDates = getWeekDates(dateString);
  const labels = weekDates.map(date => new Date(date).toLocaleDateString('en-US', { weekday: 'short' }));
  const values = weekDates.map(date => parseInt(dailyCountsByDate[date] || 0, 10));
  console.debug('updateBarChartForDate -> dateString:', dateString, 'weekDates:', weekDates, 'values:', values);

  // Update chart data and internal week mapping
  currentWeekDates = weekDates.slice();
  selectedBarIndex = -1; // reset selection when changing week
  barChart.data.labels = labels;
  barChart.data.datasets[0].data = values;
  // update colors based on reset selection
  updateBarColors();
}

const issueCtx = document.getElementById('issueChart');

function updateMetricCards(dateString) {
  const d = new Date(dateString);
  fetch(`get_stats.php?date=${dateString}`)
    .then(r => r.json())
    .then(data => {
      document.getElementById('statTodayCount').textContent = data.today;
      document.getElementById('statWeekCount').textContent = data.week;
      document.getElementById('statMonthCount').textContent = data.month;
      
      document.getElementById('statTodayLabel').textContent = `Reports on ${d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
      document.getElementById('statWeekLabel').textContent = `Week of ${new Date(data.weekStart).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}`;
      document.getElementById('statMonthLabel').textContent = `Reports in ${d.toLocaleDateString('en-US', { month: 'long' })}`;
    })
    .catch(err => console.error('Error fetching stats:', err));
}

const issueChart = new Chart(issueCtx, {
  type: 'doughnut',
  data: {
    labels: ['No Issues'],
    datasets: [{ // Initial data for the chart
      data: [1], // Changed from 1 to 0 to reflect "no issues" as a zero count
      backgroundColor: ['#b0bec5'], // Changed to a neutral color for "No Issues"
      hoverOffset: 8
    }]
  },
  options: {
    plugins: {
      legend: { position: 'bottom', labels: { boxWidth: 12, padding: 16 } }
    }
  }
});

const datePicker2 = document.getElementById("datePicker2");

function getCategoryColor(category) {
  const index = categoryLabels.indexOf(category);
  return categoryColors[index] || '#b0bec5';
}

function updateIssueChartForDate(dateString) {
  const dataForDate = issueDataByDate[dateString] || {};
  const labels = Object.keys(dataForDate).length ? Object.keys(dataForDate) : ['No Issues'];
  const values = Object.keys(dataForDate).length ? Object.values(dataForDate) : [1]; // Changed from 0 to 1 to correctly display a full gray circle when no issues
  const colors = labels.map(label => getCategoryColor(label));

  issueChart.data.labels = labels;
  issueChart.data.datasets[0].data = values;
  issueChart.data.datasets[0].backgroundColor = colors.length ? colors : ['#b0bec5'];
  issueChart.update();

  // Update the Top Recurring Cases list
  const listContainer = document.getElementById('topFaultsList');
  const entries = Object.entries(dataForDate).sort((a, b) => b[1] - a[1]).slice(0, 3);

  if (entries.length > 0) {
    listContainer.innerHTML = entries.map(entry => `
      <div class="flex items-center justify-between rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
        <span class="font-semibold dark:text-white">${entry[0]}</span>
        <span class="text-slate-500 dark:text-slate-400">${entry[1]}</span>
      </div>
    `).join('');
  } else {
    listContainer.innerHTML = `
      <div class="flex items-center justify-between rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4">
        <span class="font-semibold dark:text-white">No reports for this day</span>
        <span class="text-slate-500 dark:text-slate-400">0</span>
      </div>
    `;
  }
}

datePicker2.value = todayString;
updateIssueChartForDate(todayString);

datePicker2.addEventListener("change", function () {
  if (this.value) {
    updateIssueChartForDate(this.value);
  }
});

// Update Chart.js defaults for dark mode
function updateChartTheme(isDarkMode) {
  Chart.defaults.color = isDarkMode ? '#cbd5e1' : '#64748b'; // text-slate-400 / text-slate-500
  Chart.defaults.borderColor = isDarkMode ? '#475569' : '#e2e8f0'; // slate-600 / slate-200

  if (barChart) {
    barChart.options.scales.y.grid.color = isDarkMode ? '#475569' : '#e2e8f0';
    barChart.data.datasets[0].backgroundColor = isDarkMode ? '#0d9488' : '#0f766e'; // teal-600 / teal-700
    barChart.update();
  }
  if (issueChart) {
    // Doughnut chart colors are handled by categoryColors, but default 'No Issues' color can be updated
    issueChart.data.datasets[0].backgroundColor = issueChart.data.labels.includes('No Issues') 
      ? (isDarkMode ? ['#475569'] : ['#b0bec5']) 
      : categoryColors;
    issueChart.update();
  }
}

// Observe changes to the html tag for dark mode
const observer = new MutationObserver((mutations) => {
  mutations.forEach((mutation) => {
    if (mutation.attributeName === 'class') {
      updateChartTheme(document.documentElement.classList.contains('dark'));
    }
  });
});
observer.observe(document.documentElement, { attributes: true });
updateChartTheme(document.documentElement.classList.contains('dark')); // Initial call

function filterUsers() {
    const query = document.getElementById('userSearch').value.toLowerCase();
    const type = document.getElementById('searchType').value;
    const rows = document.querySelectorAll('.user-row');

    rows.forEach(row => {
        const name = row.getAttribute('data-name').toLowerCase();
        const plates = row.getAttribute('data-plates').toLowerCase();
        
        let matches = false;
        if (type === 'name') {
            matches = name.includes(query);
        } else if (type === 'plate') {
            matches = plates.includes(query);
        }
        
        row.style.display = matches ? "" : "none";
    });
}

function showUserDetails(user) {
    document.getElementById('modalName').textContent = user.name;
    document.getElementById('modalEmail').textContent = user.email;
    
    let displayRole = user.role;
    if(user.role === 'admin') displayRole = 'Head of AFM';
    else if(user.role === 'helper') displayRole = 'AFM Staff';
    document.getElementById('modalRole').textContent = displayRole;

    document.getElementById('modalReports').textContent = user.report_count;
    document.getElementById('modalPlates').textContent = user.car_plates || 'No plates registered';
    
    const date = new Date(user.created_at);
    document.getElementById('modalDate').textContent = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    
    const initials = user.name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2);
    document.getElementById('modalInitials').textContent = initials;

    const modal = document.getElementById('detailsModal');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal() {
    const modal = document.getElementById('detailsModal');
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

// Report Details Modal Functions
function showReportDetails(report) {
  markReportAsSeen(report.id);
    document.getElementById('detailId').textContent = report.id;
    document.getElementById('detailCategory').textContent = report.category;
    document.getElementById('detailEmergencyTier').textContent = report.emergency_tier || 'Normal';
    document.getElementById('detailReporter').textContent = report.reporter_name || 'Guest';
    document.getElementById('detailFloor').textContent = report.floor;
    document.getElementById('detailLocation').textContent = report.location;
    document.getElementById('detailDescription').textContent = report.description;
    document.getElementById('detailDate').textContent = new Date(report.created_at).toLocaleDateString('en-US', { 
        month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric' 
    });

    // Status styling
    const statusSpan = document.getElementById('detailStatus');
    statusSpan.textContent = report.status === 'Resolved' ? 'Completed' : report.status;
    statusSpan.className = 'inline-flex px-3 py-1 text-xs font-bold rounded-full'; 
    let statusClass = '';
    switch(report.status.toLowerCase()) {
        case 'pending': statusClass = 'bg-yellow-100 text-yellow-800'; break;
        case 'in progress': statusClass = 'bg-blue-100 text-blue-800'; break;
        case 'resolved': statusClass = 'bg-green-100 text-green-800'; break;
        default: statusClass = 'bg-gray-100 text-gray-800'; break;
    }
    statusSpan.classList.add(...statusClass.split(' '));

    // Photo display
    const photoContainer = document.getElementById('detailPhotoContainer');
    const photoImg = document.getElementById('detailPhoto');
    if (report.photo && report.photo !== '') {
        photoImg.src = report.photo;
        photoContainer.classList.remove('hidden');
    } else {
        photoImg.src = '';
        photoContainer.classList.add('hidden');
    }

    const modal = document.getElementById("reportDetailsModal");
    const box = document.getElementById("reportDetailsModalBox");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
    setTimeout(() => {
        box.classList.remove("scale-95");
        box.classList.add("scale-100");
    }, 10);
}

function closeReportDetailsModal() {
    const modal = document.getElementById("reportDetailsModal");
    const box = document.getElementById("reportDetailsModalBox");
    box.classList.remove("scale-100");
    box.classList.add("scale-95");
    setTimeout(() => {
        modal.classList.add("hidden");
        modal.classList.remove("flex");
    }, 200);
}

// Close on outside click for modals
window.addEventListener('click', function(event) {
    const userModal = document.getElementById('detailsModal');
    const reportModal = document.getElementById('reportDetailsModal');
    if (event.target == userModal) closeModal();
    if (event.target == reportModal) closeReportDetailsModal();
});


// Latest Updates Pagination
document.addEventListener('DOMContentLoaded', function() {
    const updatesContainer = document.getElementById('latest-updates-container');
    const paginationContainer = document.getElementById('updates-pagination');
    const prevBtn = document.getElementById('updates-prev-btn');
    const nextBtn = document.getElementById('updates-next-btn');
    const pageInfo = document.getElementById('updates-page-info');

    const updates = window.allLatestUpdates || [];
    const updatesPerPage = 3;
    let currentPage = 0;
    const totalPages = Math.ceil(updates.length / updatesPerPage);

    function renderUpdates() {
        updatesContainer.innerHTML = '';
        if (updates.length === 0) {
            updatesContainer.innerHTML = '<p class="text-sm text-slate-500 dark:text-slate-400 text-center py-4 italic">No recent activity detected.</p>';
            paginationContainer.classList.add('hidden');
            return;
        }

        const start = currentPage * updatesPerPage;
        const end = start + updatesPerPage;
        const updatesToShow = updates.slice(start, end);

        updatesToShow.forEach(update => {
            const updateTime = new Date(update.updated_at).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const card = `
                <div class="rounded-3xl bg-slate-50 dark:bg-slate-700/50 p-4 animate-fade-in">
                    <div class="flex items-center justify-between text-sm text-slate-500 dark:text-slate-400">
                        <span class="font-medium">${escapeHTML(update.category)}</span>
                        <span class="font-bold uppercase text-[10px] tracking-wider">${escapeHTML(update.status)} at ${updateTime}</span>
                    </div>
                    <p class="mt-2 font-semibold dark:text-white line-clamp-2">${escapeHTML(update.description)}</p>
                </div>
            `;
            updatesContainer.insertAdjacentHTML('beforeend', card);
        });

        if (totalPages > 1) {
            paginationContainer.classList.remove('hidden');
            pageInfo.textContent = `Page ${currentPage + 1} of ${totalPages}`;
            prevBtn.disabled = currentPage === 0;
            nextBtn.disabled = currentPage === totalPages - 1;
        } else {
            paginationContainer.classList.add('hidden');
        }
    }

    prevBtn.addEventListener('click', () => {
        if (currentPage > 0) {
            currentPage--;
            renderUpdates();
        }
    });

    nextBtn.addEventListener('click', () => {
        if (currentPage < totalPages - 1) {
            currentPage++;
            renderUpdates();
        }
    });

    function escapeHTML(str) { return str.replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[m]); }

    renderUpdates();
});
</script>
<script>
    // Handle real-time form submission for editing issues
    document.addEventListener('DOMContentLoaded', function() {
        const editForm = document.getElementById('editForm');
        if (editForm) {
            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(editForm);
                const submitButton = editForm.querySelector('button[type="submit"]');
                const originalButtonText = submitButton.innerHTML;
                submitButton.disabled = true;
                submitButton.innerHTML = 'Saving...';

                fetch('update_report.php', {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error: ' + (data.error || 'Could not update report.'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while saving.');
                })
                .finally(() => {
                    submitButton.disabled = false;
                    submitButton.innerHTML = originalButtonText;
                });
            });
        }
    });

    function updateReportRow(report) {
        const row = document.querySelector(`tr[data-description][onclick*='"id":"${report.id}"']`);
        if (!row) return;

        // Update data attributes
        row.setAttribute('onclick', `showReportDetails(${JSON.stringify(report)})`);
        row.setAttribute('data-description', (report.description || '').toLowerCase());
        row.setAttribute('data-status', report.status);

        // Update cell content
        const cells = row.getElementsByTagName('td');
        cells[0].innerHTML = `<span class="rounded-full bg-slate-100 dark:bg-slate-700 px-3 py-1 text-slate-700 dark:text-slate-300 text-xs">${report.category}</span>`;
        cells[3].textContent = report.description;
        cells[4].textContent = report.assigned_name || 'Unassigned';

        let statusClass = 'bg-slate-100 text-slate-700';
        if (report.status.toLowerCase() === 'pending') statusClass = 'bg-amber-100 text-amber-700';
        else if (report.status.toLowerCase() === 'resolved') statusClass = 'bg-emerald-100 text-emerald-700';
        else if (report.status.toLowerCase().includes('progress')) statusClass = 'bg-sky-100 text-sky-700';
        const displayStatus = report.status === 'Resolved' ? 'Completed' : report.status;
        cells[5].innerHTML = `<span class="rounded-full ${statusClass} px-3 py-1 text-xs">${displayStatus}</span>`;

        // Re-apply completed class if needed
        row.classList.toggle('bg-gray-50', report.status.toLowerCase() === 'resolved');
        row.classList.toggle('dark:bg-slate-700/50', report.status.toLowerCase() === 'resolved');
    }


(() => {
  let checkingForReports = false;

  async function checkForNewReports() {
    if (checkingForReports || document.hidden) return;
    checkingForReports = true;

    try {
      const response = await fetch('admindashboard.php?check_new_reports=1', {
        cache: 'no-store',
        headers: { Accept: 'application/json' }
      });
      if (!response.ok) throw new Error(`Report check failed (${response.status}).`);

      const data = await response.json();
      if (!data.success || !Number.isFinite(Number(data.latestReportId))) {
        throw new Error(data.error || 'The report check returned an invalid response.');
      }

      if (Number(data.latestReportId) > window.latestReportId) {
        window.location.reload();
      }
    } catch (error) {
      console.error('Unable to check for new reports:', error);
    } finally {
      checkingForReports = false;
    }
  }

  window.setInterval(checkForNewReports, 15000);
  document.addEventListener('visibilitychange', checkForNewReports);
})();
</script>

</body>
</html>