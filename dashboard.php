<?php
require_once 'auth.php';
require_login();
$user = current_user();
require_once __DIR__ . '/includes/report_read_state.php';
require_once __DIR__ . '/components/achivement_earned.php'; // Include the achievement check logic

// Fetch floor statistics for the map visualization
$view = $_GET['view'] ?? 'map';

$floorList = ['Rooftop', 'Level 6', 'Level 5', 'Level 4', 'Level 3', 'Level 2', 'Level 1', 'Ground Floor', 'Basement'];
$floorStats = [];
foreach ($floorList as $f) { $floorStats[$f] = 0; }

$statsQuery = $conn->query("SELECT floor, COUNT(*) as count FROM reports WHERE status != 'Resolved' GROUP BY floor");
while ($row = $statsQuery->fetch_assoc()) {
    $floorStats[$row['floor']] = (int)$row['count'];
}

$totalOpenIssues = array_sum($floorStats);
$activeFloors = count(array_filter($floorStats, fn($count) => $count > 0));
$resolvedTodayQuery = $conn->query("SELECT COUNT(*) AS count FROM reports WHERE status = 'Resolved' AND DATE(updated_at) = CURDATE()");
$resolvedToday = $resolvedTodayQuery && $resolvedTodayQuery->num_rows > 0 ? (int) $resolvedTodayQuery->fetch_assoc()['count'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .custom-scrollbar::-webkit-scrollbar { width: 6px; }
    .custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
    .dark .custom-scrollbar::-webkit-scrollbar-thumb { background: #475569; }
    .dashboard-hero { background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 45%, #0ea5e9 100%); }
    .panel-surface { background: rgba(255,255,255,0.98); }
    .dark .panel-surface { background: rgba(15,23,42,0.96); }
    @media (max-width: 639px) {
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
      .responsive-report-table td:not([data-label]) { display: block; text-align: center; }
      .responsive-report-table td.max-w-xs {
        max-width: none;
        overflow: visible;
        text-overflow: clip;
        white-space: normal;
      }
    }
  </style>
</head>

<body class="bg-slate-100 dark:bg-slate-900 transition-colors duration-300 min-h-screen">

<?php include 'components/navbar.php'; ?>
<div class="flex min-h-screen">
  <!-- Navigation Sidebar (collapsible on mobile) -->
  <?php include 'components/sidebar.php'; ?>

  <main class="flex-1 p-4 sm:p-6 overflow-x-hidden md:ml-32">
    <div class="max-w-7xl mx-auto">

  <div class="dashboard-hero mb-8 overflow-hidden rounded-[2rem] border border-white/10 p-6 text-white shadow-[0_20px_50px_rgba(37,99,235,0.28)]">
    <div class="flex flex-col gap-6 xl:flex-row xl:items-center xl:justify-between">
      <div class="flex items-center gap-4">
        <div>
          <p class="text-xs font-bold uppercase tracking-[0.35em] text-blue-100/80">Operations</p>
          <h2 class="mt-3 text-3xl font-bold text-white"><?php echo $view === 'map' ? 'Facility Status Map' : 'Reported Issues'; ?></h2>
          <p class="mt-2 text-sm text-blue-100">Welcome back, <?php echo htmlspecialchars($user['name']); ?>.</p>
        </div>
      </div>


      <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
          <p class="text-[10px] uppercase tracking-[0.2em] text-blue-100/80">Active issues</p>
          <p class="mt-2 text-2xl font-bold"><?php echo $totalOpenIssues; ?></p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
          <p class="text-[10px] uppercase tracking-[0.2em] text-blue-100/80">Affected floors</p>
          <p class="mt-2 text-2xl font-bold"><?php echo $activeFloors; ?></p>
        </div>
        <div class="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur-sm">
          <p class="text-[10px] uppercase tracking-[0.2em] text-blue-100/80">Completed today</p>
          <p class="mt-2 text-2xl font-bold"><?php echo $resolvedToday; ?></p>
        </div>
      </div>
    </div>

    <div id="latest-updates-container" class="mt-6 space-y-4">
      <?php include "components/notify.php"; ?>
    </div>     

    <?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
      <div class="mt-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
          </svg>
          Issue reported successfully!
        </div>
      </div>
    <?php endif; ?>
    <?php if (isset($_GET['duplicate']) && $_GET['duplicate'] == 1): ?>
      <div class="mt-4 bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded-lg">
        <div class="flex items-center">
          <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
            <path d="M8.257 3.099c.366-.446.957-.59 1.466-.39l.094.04 5.25 2.5a1 1 0 01.527.89v6.922a1 1 0 01-.527.89l-.094.04-5.25 2.5a1 1 0 01-1.45-.89V4.5a1 1 0 01.29-.707z" />
          </svg>
          A similar issue was already reported recently. Please check your existing report before submitting again.
        </div>
      </div>
    <?php endif; ?>
  </div>

  <?php if ($view === 'map'): ?>
    <?php include 'components/facility_map.php'; ?>
  <?php else: ?>

  <div class="bg-white dark:bg-slate-800 dark:border dark:border-slate-700 rounded-xl shadow-lg p-6 mb-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between mb-6">
      <h3 class="text-xl font-semibold text-gray-800 dark:text-white">All Reported Issues</h3>
      <form method="get" class="flex flex-wrap items-center gap-3">
        <input type="hidden" name="view" value="reports">
        <div class="inline-flex items-center gap-3 rounded-3xl bg-gray-50 dark:bg-slate-700/50 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 border dark:border-slate-700 shadow-sm">
          <input type="text" id="reportSearch" name="search" value="<?php echo htmlspecialchars($_GET['search'] ?? '', ENT_QUOTES); ?>" placeholder="Search description..." class="w-48 border-0 bg-transparent focus:outline-none dark:text-white" />
        </div>
        <select id="filterStatus" name="status" onchange="this.form.submit()" class="rounded-3xl bg-gray-50 dark:bg-slate-700/50 px-4 py-2 text-sm text-gray-600 dark:text-gray-300 border dark:border-slate-700 focus:ring-0 cursor-pointer shadow-sm">
          <option value="">All Status</option>
          <option value="Pending" <?php echo ($_GET['status'] ?? '') === 'Pending' ? 'selected' : ''; ?>>Pending</option>
          <option value="In Progress" <?php echo ($_GET['status'] ?? '') === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
          <option value="Resolved" <?php echo ($_GET['status'] ?? '') === 'Resolved' ? 'selected' : ''; ?>>Completed</option>
        </select>
        <div class="inline-flex items-center gap-2 rounded-3xl bg-gray-50 dark:bg-slate-700/50 px-4 py-2 text-sm text-gray-500 dark:text-gray-300 border dark:border-slate-700 shadow-sm">
          <span class="text-xs font-semibold uppercase">From:</span>
          <input type="date" id="filterDateStart" name="date_start" value="<?php echo htmlspecialchars($_GET['date_start'] ?? '', ENT_QUOTES); ?>" onchange="this.form.submit()" class="bg-transparent border-0 focus:ring-0 text-xs p-0">
          <span class="text-xs font-semibold uppercase">To:</span>
          <input type="date" id="filterDateEnd" name="date_end" value="<?php echo htmlspecialchars($_GET['date_end'] ?? '', ENT_QUOTES); ?>" onchange="this.form.submit()" class="bg-transparent border-0 focus:ring-0 text-xs p-0">
        </div>
        <button type="submit" class="rounded-3xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700">Search</button>
      </form>
    </div>

    <?php
    $reportsPerPage = 10;
    $currentPage = max(1, (int)($_GET['page'] ?? 1));
    $search = trim($_GET['search'] ?? '');
    $status = $_GET['status'] ?? '';
    $dateStart = $_GET['date_start'] ?? '';
    $dateEnd = $_GET['date_end'] ?? '';
    $conditions = [];
    $params = [];
    $types = '';

    if ($search !== '') {
      $conditions[] = 'r.description LIKE ?';
      $params[] = '%' . $search . '%';
      $types .= 's';
    }
    if (in_array($status, ['Pending', 'In Progress', 'Resolved'], true)) {
      $conditions[] = 'r.status = ?';
      $params[] = $status;
      $types .= 's';
    } else {
      $status = '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateStart)) {
      $conditions[] = 'DATE(r.created_at) >= ?';
      $params[] = $dateStart;
      $types .= 's';
    } else {
      $dateStart = '';
    }
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateEnd)) {
      $conditions[] = 'DATE(r.created_at) <= ?';
      $params[] = $dateEnd;
      $types .= 's';
    } else {
      $dateEnd = '';
    }

    $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    $bindParams = static function ($statement, $types, &$params): void {
      if ($types === '') {
        return;
      }
      $values = [$types];
      foreach ($params as &$param) {
        $values[] = &$param;
      }
      call_user_func_array([$statement, 'bind_param'], $values);
    };

    $countStatement = $conn->prepare("SELECT COUNT(*) AS total FROM reports r$where");
    $bindParams($countStatement, $types, $params);
    $countStatement->execute();
    $totalReports = (int)$countStatement->get_result()->fetch_assoc()['total'];
    $countStatement->close();

    $totalPages = max(1, (int)ceil($totalReports / $reportsPerPage));
    $currentPage = min($currentPage, $totalPages);
    $offset = ($currentPage - 1) * $reportsPerPage;
    $sql = "SELECT r.*, u_reporter.name as reporter_name
        FROM reports r
        LEFT JOIN users u_reporter ON r.user_id = u_reporter.id
        $where
        ORDER BY r.created_at DESC
        LIMIT $reportsPerPage OFFSET $offset";
    $resultStatement = $conn->prepare($sql);
    $bindParams($resultStatement, $types, $params);
    $resultStatement->execute();
    $result = $resultStatement->get_result();

    if ($result->num_rows > 0) {
        echo '<div class="responsive-report-table-wrapper overflow-x-auto">';
        echo '<table class="responsive-report-table w-full table-auto border-collapse">';
        echo '<thead>';
        echo '<tr class="bg-gray-50 dark:bg-slate-700/50">';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Category</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Reporter</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Floor</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Location</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Description</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Urgency</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Status</th>';
        echo '<th class="px-4 py-3 text-left text-sm font-semibold text-gray-600 dark:text-gray-300">Date</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody id="reportTableBody">';

        while($row = $result->fetch_assoc()) {
            $statusColor = '';
            switch(strtolower($row['status'])) {
                case 'pending':
                    $statusColor = 'bg-yellow-100 text-yellow-800';
                    break;
                case 'in progress':
                    $statusColor = 'bg-blue-100 text-blue-800';
                    break;
                case 'resolved':
                    $statusColor = 'bg-green-100 text-green-800';
                    break;
                default:
                    $statusColor = 'bg-gray-100 text-gray-800';
            }
            // Pass the entire row object as JSON to the showReportDetails function
            echo '<tr class="relative border-b border-gray-200 dark:border-slate-700 hover:bg-gray-50 dark:hover:bg-slate-700/30 transition-colors report-row" 
                      data-description="' . strtolower(htmlspecialchars($row['description'], ENT_QUOTES)) . '" 
                      data-status="' . htmlspecialchars($row['status'], ENT_QUOTES) . '" 
                      data-date="' . date('Y-m-d', strtotime($row['created_at'])) . '"
                      onclick=\'showReportDetails(' . json_encode($row) . ')\'>';
            echo '<td data-label="Category" class="relative px-4 py-3 text-sm text-gray-900 dark:text-gray-200">' . render_unread_report_dot((int)$row['id']) . $row['category'] . '</td>';
            echo '<td data-label="Reporter" class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">' . htmlspecialchars($row['reporter_name'] ?? 'Guest') . '</td>';
            echo '<td data-label="Floor" class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">' . $row['floor'] . '</td>';
            echo '<td data-label="Location" class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200">' . $row['location'] . '</td>';
            echo '<td data-label="Description" class="px-4 py-3 text-sm text-gray-900 dark:text-gray-200 max-w-xs truncate">' . $row['description'] . '</td>';
            echo '<td data-label="Urgency" class="px-4 py-3 text-sm font-semibold text-gray-900 dark:text-gray-200">' . htmlspecialchars($row['emergency_tier'] ?? 'Normal') . '</td>';
            echo '<td data-label="Status" class="px-4 py-3"><span class="px-2 py-1 text-xs font-medium rounded-full ' . $statusColor . '">' . htmlspecialchars($row['status'] === 'Resolved' ? 'Completed' : $row['status']) . '</span></td>';
            echo '<td data-label="Date" class="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">' . date('M d, Y', strtotime($row['created_at'])) . '</td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '</div>';

        if ($totalPages > 1):
          $paginationQuery = ['view' => 'reports'];
          if ($search !== '') $paginationQuery['search'] = $search;
          if ($status !== '') $paginationQuery['status'] = $status;
          if ($dateStart !== '') $paginationQuery['date_start'] = $dateStart;
          if ($dateEnd !== '') $paginationQuery['date_end'] = $dateEnd;
          echo '<nav class="mt-5 flex flex-wrap items-center justify-center gap-2" aria-label="Issue pages">';
          if ($currentPage > 1) {
            $paginationQuery['page'] = $currentPage - 1;
            echo '<a href="dashboard.php?' . htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES) . '" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Previous</a>';
          }
          for ($page = 1; $page <= $totalPages; $page++) {
            $pageClass = $page === $currentPage
              ? 'bg-blue-600 text-white'
              : 'border border-slate-200 text-slate-600 hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700';
            $paginationQuery['page'] = $page;
            echo '<a href="dashboard.php?' . htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES) . '" class="rounded-lg px-3 py-2 text-sm font-semibold transition-colors ' . $pageClass . '" aria-current="' . ($page === $currentPage ? 'page' : 'false') . '">' . $page . '</a>';
          }
          if ($currentPage < $totalPages) {
            $paginationQuery['page'] = $currentPage + 1;
            echo '<a href="dashboard.php?' . htmlspecialchars(http_build_query($paginationQuery), ENT_QUOTES) . '" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-600 dark:text-slate-200 dark:hover:bg-slate-700">Next</a>';
          }
          echo '</nav>';
        endif;
    } else {
        echo '<div class="text-center py-8">';
        echo '<p class="text-gray-500 text-lg">No issues reported yet.</p>';
        echo '<p class="text-gray-400 text-sm mt-2">Click the "Report Issue" button below to get started.</p>';
        echo '</div>';
    }
    ?>
  </div>
  <?php endif; ?>

    </div>
  </main>
</div>

  <!-- Bottom Buttons -->
  <div class="fixed bottom-4 left-4 md:bottom-6 md:left-6 md:right-6 md:z-40">
    <!-- Report Issue Button (Bottom Left) -->
    <button onclick="openReportModal()"
      class="w-full sm:w-auto bg-blue-600 hover:bg-blue-700 text-white font-semibold px-5 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
      </svg>
      Report Issue
    </button>
  </div>

    <!-- AI Chatbot Button (Bottom Right) -->
  <div class="fixed bottom-4 right-4 md:bottom-6 md:right-6 md:z-40">
    <button onclick="openChatbotModal()"
      class="w-full sm:w-auto bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-3 rounded-xl shadow-lg hover:shadow-xl transform hover:scale-[1.02] transition-all duration-300 flex items-center justify-center gap-2">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
      </svg>
      AI Chatbot
    </button>
  </div>

  <!-- Report Modal -->
  <div id="reportModal"
      class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white dark:bg-slate-800 p-4 sm:p-6 rounded-xl shadow-lg w-full max-w-md sm:max-w-2xl max-h-[90vh] overflow-y-auto transform transition duration-300 scale-95" id="reportModalBox">
      <button onclick="closeReportModal()"
        class="absolute top-4 right-4 text-gray-500 hover:text-gray-700 text-2xl">
        &times;
      </button>
      <?php include 'components/report.php'; ?>
    </div>
  </div>

  <!-- Chatbot Modal -->
  <div id="chatbotModal"
      class="fixed inset-0 bg-black bg-opacity-60 hidden items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-slate-800 rounded-xl shadow-lg w-full max-w-md h-[85vh] sm:h-[70vh] max-h-[700px] transform transition duration-300 scale-95 overflow-hidden flex flex-col" id="chatbotModalBox">
      <div class="bg-gradient-to-r from-blue-600 to-blue-700 text-white p-4 rounded-t-xl flex items-center justify-between">
        <div>
          <h3 class="font-semibold">Facility AI Assistant</h3>
          <p class="text-xs text-blue-100">Ask about issue types or facility help</p>
        </div>
        <button onclick="closeChatbotModal()"
          class="text-white hover:text-gray-200 text-2xl leading-none">
          &times;
        </button>
      </div>

      <div id="chatMessages" class="p-4 flex-1 overflow-y-auto bg-gray-50 dark:bg-slate-900 custom-scrollbar">
        <div class="mb-4">
          <div class="bg-blue-100 dark:bg-blue-900/30 rounded-2xl p-4 max-w-full">
            <p class="text-sm text-blue-800">Hi! I'm your facility assistant. I can help you:</p>
            <ul class="text-sm text-blue-800 mt-2 space-y-1">
              <li>✓ Categorize your facility issue</li>
              <li>✓ Answer facility questions</li>
            </ul>
            <p class="text-xs text-blue-700 mt-3 font-semibold">Try asking: "My lights are broken, what issue type?"</p>
          </div>
        </div>
      </div>

      <div class="p-4 border-t dark:border-slate-700 bg-white dark:bg-slate-800">
        <div class="flex flex-col sm:flex-row gap-2">
          <input type="text" id="chatInput"
            placeholder="Ask me anything..."
            class="flex-1 border border-gray-300 dark:border-slate-600 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-slate-700 dark:text-white">
          <button onclick="sendMessage()"
            class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-3 rounded-xl">
            Send
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- Report Details Modal -->
  <div id="reportDetailsModal" class="fixed inset-0 bg-black/60 hidden items-center justify-center z-50 p-4 backdrop-blur-sm">
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

      <!-- Modal Footer -->
      <div class="p-6 border-t dark:border-slate-700 bg-gray-50/50 dark:bg-slate-700/30">
        <button onclick="closeReportDetailsModal()" class="w-full bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-bold py-4 rounded-2xl hover:opacity-90 transition-opacity">
          Dismiss
        </button>
      </div>
    </div>
  </div>

</div>

</body>
</html>

<?php include 'components/report_read_state.php'; ?>
<script>
window.seenReportsStorageKey = 'facilitySystemSeenReports_<?php echo (int)$user['id']; ?>_<?php echo htmlspecialchars($_SESSION['user']['role'] ?? 'user', ENT_QUOTES); ?>';

  

// Report Modal Functions
function openReportModal() {
  const modal = document.getElementById("reportModal");
  const box = document.getElementById("reportModalBox");

  modal.classList.remove("hidden");
  modal.classList.add("flex");

  setTimeout(() => {
    box.classList.remove("scale-95");
    box.classList.add("scale-100");
  }, 10);
}

function closeReportModal() {
  const modal = document.getElementById("reportModal");
  const box = document.getElementById("reportModalBox");

  box.classList.remove("scale-100");
  box.classList.add("scale-95");

  setTimeout(() => {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }, 200);
}

// Chatbot Modal Functions
function openChatbotModal() {
  const modal = document.getElementById("chatbotModal");
  const box = document.getElementById("chatbotModalBox");

  modal.classList.remove("hidden");
  modal.classList.add("flex");

  setTimeout(() => {
    box.classList.remove("scale-95");
    box.classList.add("scale-100");
  }, 10);
}

function closeChatbotModal() {
  const modal = document.getElementById("chatbotModal");
  const box = document.getElementById("chatbotModalBox");

  box.classList.remove("scale-100");
  box.classList.add("scale-95");

  setTimeout(() => {
    modal.classList.add("hidden");
    modal.classList.remove("flex");
  }, 200);
}

// Report Details Modal Functions (for viewing existing report)
function showReportDetails(report) {
  markReportAsSeen(report.id);
  document.getElementById('detailId').textContent = report.id;
  document.getElementById('detailCategory').textContent = report.category;
  document.getElementById('detailEmergencyTier').textContent = report.emergency_tier || 'Normal';
  document.getElementById('detailReporter').textContent = report.reporter_name || 'Guest';
  document.getElementById('detailFloor').textContent = report.floor;
  document.getElementById('detailLocation').textContent = report.location;
  document.getElementById('detailDescription').textContent = report.description;
  document.getElementById('detailDate').textContent = new Date(report.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: 'numeric' });

  // Status styling
  const statusSpan = document.getElementById('detailStatus');
  statusSpan.textContent = report.status === 'Resolved' ? 'Completed' : report.status;
  statusSpan.className = 'inline-flex px-3 py-1 text-xs font-bold rounded-full'; // Reset classes
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

// This array holds the chat history in the exact structural layout Gemini expects
let chatHistory = [];

// Get references to your specific modal HTML elements
const chatMessages = document.getElementById('chatMessages');
const chatInput = document.getElementById('chatInput');

function appendMessage(text, role) {
  const msgWrapper = document.createElement('div');
  msgWrapper.className = "mb-4 flex flex-col";

  const msgBubble = document.createElement('div');
  
  if (role === 'user') {
    msgWrapper.classList.add('items-end');
    msgBubble.className = "bg-blue-600 text-white rounded-2xl rounded-tr-none p-3 max-w-[85%] text-sm shadow-sm";
  } else {
    msgWrapper.classList.add('items-start');
    msgBubble.className = "bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-slate-100 rounded-2xl rounded-tl-none p-3 max-w-[85%] text-sm shadow-sm border border-gray-200/50 dark:border-slate-600/50";
  }
  
  const parts = text.split(/(\*\*.*?\*\*)/g);
  parts.forEach(part => {
    if (part.startsWith('**') && part.endsWith('**')) {
      const strong = document.createElement('strong');
      strong.textContent = part.slice(2, -2);
      msgBubble.appendChild(strong);
    } else {
      msgBubble.appendChild(document.createTextNode(part));
    }
  });
  
  msgWrapper.appendChild(msgBubble);
  chatMessages.appendChild(msgWrapper);
  
  // Auto scroll window to the latest message
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

function appendReportDraftAction(draft) {
  const actionWrapper = document.createElement('div');
  actionWrapper.className = 'report-draft-action mb-4 flex flex-col items-start';

  const button = document.createElement('button');
  button.type = 'button';
  button.className = 'rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-blue-700';
  button.textContent = 'Fill report form with these details';
  button.addEventListener('click', () => {
    window.populateReportDraft(draft);
    closeChatbotModal();
    setTimeout(openReportModal, 220);
  });

  actionWrapper.appendChild(button);
  chatMessages.appendChild(actionWrapper);
  chatMessages.scrollTop = chatMessages.scrollHeight;
}

let chatRequestInProgress = false;

async function sendMessage() {
  const text = chatInput.value.trim();
  if (!text || chatRequestInProgress) return;
  chatRequestInProgress = true;

  chatMessages.querySelectorAll('.report-draft-action').forEach((action) => action.remove());

  // 1. Display user message in the modal
  appendMessage(text, 'user');
  chatInput.value = '';

  // 2. Track the message in the history timeline
  chatHistory.push({
    "role": "user",
    "parts": [{ "text": text }]
  });

  try {
    // 3. Send history payload to chatbot.php file
    const response = await fetch('chatbot/chatbot.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ history: chatHistory })
    });
    
    const data = await response.json();
    if (!response.ok || typeof data.reply !== 'string') {
      throw new Error(data.reply || 'The assistant could not process this request.');
    }

    appendMessage(data.reply, 'model');
    chatHistory.push({
      "role": "model",
      "parts": [{ "text": data.history_content || JSON.stringify({ reply: data.reply, draft: data.draft ?? null }) }]
    });

    if (data.draft && typeof data.draft === 'object' && typeof window.populateReportDraft === 'function') {
      appendMessage("I found details for a report draft. You can continue chatting or fill the report form with the details so far.", 'model');
      appendReportDraftAction(data.draft);
    }

  } catch (error) {
    appendMessage(error.message || "System Error: Unable to connect to the assistant server.", 'model');
  } finally {
    chatRequestInProgress = false;
  }
}

// Allow user to send messages by pressing the 'Enter' key inside the input box
chatInput.addEventListener('keypress', (e) => { 
  if (e.key === 'Enter') sendMessage(); 
});

// Handle asynchronous report form submission
document.addEventListener('DOMContentLoaded', function() {
    const reportForm = document.getElementById('reportForm');
    if (reportForm) {
        reportForm.addEventListener('submit', function(e) {
            e.preventDefault(); // Prevent the default form submission

            const formData = new FormData(reportForm);
            const submitButton = reportForm.querySelector('button[type="submit"]');

            // Disable button
            submitButton.disabled = true;

            fetch('components/submit_report.php', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
              const data = await response.json();
                if (!response.ok) {
                    // Handle non-2xx responses like duplicates (409)
                    if (response.status === 409) {
                        window.location.href = 'dashboard.php?view=reports&duplicate=1';
                    } else {
                  throw new Error(data.error || 'The server could not save the report.');
                    }
                }
              return data;
            })
            .then(data => {
                if (data && data.success) {
                    // On success, redirect to the reports view with a success message
                    window.location.href = 'dashboard.php?view=reports&success=1';
                }
            })
            .catch(error => {
                console.error('Error submitting report:', error);
              alert('Unable to submit the report: ' + error.message);
                // Restore button on error
                submitButton.disabled = false;
            });
        });
    }
});
</script>