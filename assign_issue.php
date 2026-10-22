<?php
require_once 'auth.php';
require_login('admin');

$reportId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
}

if (!$reportId || $reportId < 1) {
    header('Location: admindashboard.php?view=reports&assign_error=invalid_report');
    exit;
}

$message = '';
$messageType = 'error';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $staffIds = $_POST['assigned_to'] ?? [];
  $starts = $_POST['assignment_start'] ?? [];
  $ends = $_POST['assignment_end'] ?? [];
  $assignments = [];
  $datePattern = '/^\d{4}-\d{2}-\d{2}$/';

  foreach ($staffIds as $index => $staffIdValue) {
    if ($staffIdValue === '') {
      continue;
    }
    $staffId = filter_var($staffIdValue, FILTER_VALIDATE_INT);
    $start = trim($starts[$index] ?? '');
    $end = trim($ends[$index] ?? '');
    if (!$staffId || !preg_match($datePattern, $start) || !preg_match($datePattern, $end)) {
      $message = 'Each assigned staff member needs a valid start and end date.';
      break;
    }
    if ($start > $end) {
      $message = 'An assignment start date cannot be later than its end date.';
      break;
    }
    $assignments[] = ['staff_id' => $staffId, 'start' => $start, 'end' => $end];
  }

  if ($message === '' && count($assignments) > 20) {
    $message = 'You can assign a maximum of 20 staff members to one issue.';
  }

  if ($message === '') {
    $staffIdsToCheck = array_column($assignments, 'staff_id');
    $staffExists = true;
    foreach ($staffIdsToCheck as $staffId) {
      $staffStatement = $conn->prepare("SELECT id FROM users WHERE id = ? AND role = 'helper' LIMIT 1");
      $staffStatement->bind_param('i', $staffId);
      $staffStatement->execute();
      if ($staffStatement->get_result()->num_rows !== 1) {
        $staffExists = false;
      }
      $staffStatement->close();
    }

    if (!$staffExists) {
      $message = 'One or more selected staff members are not available.';
    } else {
      $conn->begin_transaction();
      $deleteStatement = $conn->prepare('DELETE FROM issue_assignments WHERE report_id = ?');
      $deleteStatement->bind_param('i', $reportId);
      $saved = $deleteStatement->execute();
      $deleteStatement->close();

      $insertStatement = $conn->prepare('INSERT INTO issue_assignments (report_id, staff_id, assignment_start, assignment_end) VALUES (?, ?, ?, ?)');
      foreach ($assignments as $assignment) {
        $insertStatement->bind_param('iiss', $reportId, $assignment['staff_id'], $assignment['start'], $assignment['end']);
        $saved = $saved && $insertStatement->execute();
      }
      $insertStatement->close();

      $primary = $assignments[0] ?? null;
      if ($primary) {
        $updateStatement = $conn->prepare('UPDATE reports SET assigned_to = ? WHERE id = ?');
        $updateStatement->bind_param('si', $primary['staff_id'], $reportId);
      } else {
        $updateStatement = $conn->prepare('UPDATE reports SET assigned_to = NULL WHERE id = ?');
        $updateStatement->bind_param('i', $reportId);
      }
      $saved = $saved && $updateStatement->execute();
      $updateStatement->close();

      if ($saved) {
        $conn->commit();
        header('Location: admindashboard.php?view=reports&assigned=1');
        $assignedStaffEmailStmt = $conn->prepare("SELECT u.email FROM users u WHERE u.id = ?"); 
        $assignedStaffEmailStmt->bind_param('i', $primary['staff_id']);
        $assignedStaffEmailStmt->execute();
        $assignedStaffEmailResult = $assignedStaffEmailStmt->get_result();
        if ($assignedStaffEmailRow = $assignedStaffEmailResult->fetch_assoc()) {
            $sent = send_notification_email_to_staff($assignedStaffEmailRow['email'], 'New Assignment', 'You have been assigned to a new issue. Please check the system for details.');
        }
        $assignedStaffEmailStmt->close();
        exit;
      }
      $conn->rollback();
      $message = 'Unable to save the assignments. Please try again.';
    }
  }
}

$reportStatement = $conn->prepare("SELECT r.id, r.category, r.floor, r.location, r.description, r.assigned_to, ia.assignment_start, ia.assignment_end, u.name AS assigned_name FROM reports r LEFT JOIN issue_assignments ia ON r.id = ia.report_id LEFT JOIN users u ON r.assigned_to = u.id WHERE r.id = ? LIMIT 1");
$reportStatement->bind_param('i', $reportId);
$reportStatement->execute();
$report = $reportStatement->get_result()->fetch_assoc();
$reportStatement->close();

if (!$report) {
    header('Location: admindashboard.php?view=reports&assign_error=invalid_report');
    exit;
}

$helpers = [];
$helperResult = $conn->query("SELECT id, name FROM users WHERE role = 'helper' ORDER BY name ASC");
while ($helper = $helperResult->fetch_assoc()) {
    $helpers[] = $helper;
}

$assignmentRows = [];
$assignmentStatement = $conn->prepare('SELECT staff_id, assignment_start, assignment_end FROM issue_assignments WHERE report_id = ? ORDER BY id ASC');
$assignmentStatement->bind_param('i', $reportId);
$assignmentStatement->execute();
$assignmentResult = $assignmentStatement->get_result();
while ($assignment = $assignmentResult->fetch_assoc()) {
  $assignmentRows[] = $assignment;
}
$assignmentStatement->close();
if (!$assignmentRows && !empty($report['assigned_to'])) {
  $assignmentRows[] = ['staff_id' => $report['assigned_to'], 'assignment_start' => $report['assignment_start'], 'assignment_end' => $report['assignment_end']];
}
if (!$assignmentRows) {
  $assignmentRows[] = ['staff_id' => '', 'assignment_start' => '', 'assignment_end' => ''];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Assign Issue | Facility System</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-slate-900 min-h-screen transition-colors duration-300 text-slate-800 dark:text-slate-200">
  <?php include 'components/navbar.php'; ?>
  <div class="flex min-h-[calc(100vh-4rem)]">
    <?php $view = 'reports'; 
    include 'components/admin_sidebar.php'; ?>
    <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 md:ml-32">
      <div class="mx-auto max-w-3xl">
        <section class="mt-5 rounded-[2rem] bg-white p-6 shadow-sm dark:border dark:border-slate-800 dark:bg-slate-900 sm:p-8">
          <div class="mb-7">
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-cyan-600 dark:text-cyan-300">Report #<?php echo (int)$report['id']; ?></p>
            <h1 class="mt-2 text-3xl font-black tracking-tight">Assign issue</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Choose the staff member responsible and define the assignment period.</p>
          </div>

          <?php if ($message !== ''): ?>
            <div class="mb-6 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300" role="alert"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <div class="mb-7 rounded-2xl bg-slate-50 p-5 dark:bg-slate-800/70">
            <div class="flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
              <span class="rounded-lg bg-white px-3 py-1.5 shadow-sm dark:bg-slate-700"><?php echo htmlspecialchars($report['category']); ?></span>
              <span class="rounded-lg bg-white px-3 py-1.5 shadow-sm dark:bg-slate-700"><?php echo htmlspecialchars($report['floor']); ?></span>
              <span class="rounded-lg bg-white px-3 py-1.5 shadow-sm dark:bg-slate-700"><?php echo htmlspecialchars($report['location']); ?></span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($report['description']); ?></p>
          </div>

          <form method="post" class="space-y-5">
            <input type="hidden" name="id" value="<?php echo (int)$report['id']; ?>">
            <div class="flex items-center justify-between">
              <div>
                <h2 class="text-sm font-bold">Staff schedule</h2>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Add one date range for each staff member.</p>
              </div>
              <button type="button" onclick="addAssignmentRow()" class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-900">+ Add staff</button>
            </div>
            <div id="assignmentRows" class="space-y-3">
              <?php foreach ($assignmentRows as $assignmentRow): ?>
                <div class="assignment-row grid gap-3 rounded-2xl border border-slate-200 p-4 dark:border-slate-700 sm:grid-cols-[1.3fr_1fr_1fr_auto] sm:items-end">
                  <div>
                    <label class="mb-2 block text-xs font-semibold">Staff member</label>
                    <select name="assigned_to[]" class="staff-select w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800">
                      <option value="">Select staff</option>
                      <?php foreach ($helpers as $helper): ?>
                        <option value="<?php echo (int)$helper['id']; ?>" <?php echo (string)$helper['id'] === (string)$assignmentRow['staff_id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($helper['name']); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div>
                    <label class="mb-2 block text-xs font-semibold">Start date</label>
                    <input type="date" name="assignment_start[]" value="<?php echo htmlspecialchars($assignmentRow['assignment_start'] ?? ''); ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800">
                  </div>
                  <div>
                    <label class="mb-2 block text-xs font-semibold">End date</label>
                    <input type="date" name="assignment_end[]" value="<?php echo htmlspecialchars($assignmentRow['assignment_end'] ?? ''); ?>" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800">
                  </div>
                  <button type="button" onclick="removeAssignmentRow(this)" class="rounded-xl border border-rose-200 px-3 py-2.5 text-xs font-bold text-rose-600 hover:bg-rose-50 dark:border-rose-900 dark:hover:bg-rose-950/40">Remove</button>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="flex flex-col-reverse gap-3 pt-3 sm:flex-row sm:justify-end">
              <a href="admindashboard.php?view=reports" class="rounded-xl border border-slate-300 px-5 py-3 text-center text-sm font-bold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">Cancel</a>
              <button type="submit" class="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-500">Save assignment</button>
            </div>
          </form>
        </section>
      </div>
    </main>
  </div>
  <script>
    function addAssignmentRow() {
      const template = document.querySelector('.assignment-row');
      const row = template.cloneNode(true);
      row.querySelectorAll('select, input').forEach((field) => field.value = '');
      document.getElementById('assignmentRows').appendChild(row);
    }

    function removeAssignmentRow(button) {
      button.closest('.assignment-row').remove();
    }
  </script>
</body>
</html>
