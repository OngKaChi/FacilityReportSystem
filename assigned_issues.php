<?php
require_once 'auth.php';
require_login('helper');
$user = current_user();
$userId = (int) $user['id'];
$message = '';
$messageType = 'success';

$getLatestActiveAssignmentId = static function () use ($conn, $userId): int {
    $statement = $conn->prepare("
        SELECT COALESCE(MAX(ia.id), 0) AS latest_id
        FROM issue_assignments ia
        INNER JOIN reports r ON r.id = ia.report_id
        WHERE ia.staff_id = ?
          AND CURRENT_DATE BETWEEN ia.assignment_start AND ia.assignment_end
          AND r.status IN ('Pending', 'In Progress')
    ");
    if (!$statement) {
        throw new RuntimeException('Unable to prepare the assigned task check.');
    }

    $statement->bind_param('i', $userId);
    if (!$statement->execute()) {
        $statement->close();
        throw new RuntimeException('Unable to check for newly assigned tasks.');
    }

    $latestId = (int) $statement->get_result()->fetch_assoc()['latest_id'];
    $statement->close();
    return $latestId;
};

if (isset($_GET['check_new_assignments'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode([
        'success' => true,
        'latestAssignmentId' => $getLatestActiveAssignmentId(),
    ]);
    exit;
}

$latestAssignmentId = $getLatestActiveAssignmentId();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reportId = filter_input(INPUT_POST, 'report_id', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? '');
    $allowedStatuses = ['Pending', 'In Progress', 'Resolved'];

    if (!$reportId || !in_array($status, $allowedStatuses, true)) {
        $message = 'Please choose a valid status.';
        $messageType = 'error';
    } else {
      $accessStatement = $conn->prepare("
        SELECT r.id, u.email, category
        FROM reports r
        LEFT JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
          AND (
            r.assigned_to = ?
            OR EXISTS (
              SELECT 1
              FROM issue_assignments ia
              WHERE ia.report_id = r.id
                AND ia.staff_id = ?
                AND CURRENT_DATE BETWEEN ia.assignment_start AND ia.assignment_end
            )
          )
        LIMIT 1
      ");
      $accessStatement->bind_param('iii', $reportId, $userId, $userId);
      $accessStatement->execute();
      $access = $accessStatement->get_result()->fetch_assoc();
      $hasAccess = $access !== null;
      $reportedUserEmail = $access['email'] ?? null;
      $reportedCategory = $access['category'] ?? null;
      $accessStatement->close();

      if ($hasAccess) {
        $progress_description = trim($_POST['progress_description'] ?? '');
        $conn->begin_transaction();
        $statusStatement = $conn->prepare('UPDATE reports SET status = ? WHERE id = ?');
        $statusStatement->bind_param('si', $status, $reportId);
        $statusUpdated = $statusStatement->execute();
        $statusStatement->close();

        $progressStatement = $conn->prepare('UPDATE issue_assignments SET progress_description = ? WHERE report_id = ? AND staff_id = ?');
        $progressStatement->bind_param('sii', $progress_description, $reportId, $userId);
        $progressUpdated = $progressStatement->execute();
        $progressStatement->close();

        if ($statusUpdated && $progressUpdated) {
          $conn->commit();
          $message = 'Issue status updated successfully.';
          if ($reportedUserEmail !== null && $reportedUserEmail !== '') {
            $sent1 = send_notification_email($reportedUserEmail, null, $reportedCategory, $status, null);

            $adminEmail = $conn->query("SELECT email FROM users WHERE role = 'admin' LIMIT 1");
            $adminEmail = $adminEmail->fetch_assoc()['email'] ?? null;
            $emergencyTier = $conn->query("SELECT emergency_tier FROM reports WHERE id = $reportId LIMIT 1");
            $emergencyTier = $emergencyTier->fetch_assoc()['emergency_tier'] ?? null;
            if ($adminEmail !== null && $adminEmail !== '') {
              $sent2 = send_notification_email($adminEmail, $reportId, $reportedCategory, $status, $emergencyTier);
            }
          }
        } else {
          $conn->rollback();
          $message = 'Unable to update the issue status.';
          $messageType = 'error';
        }
        } else {
            $message = 'This issue is not currently assigned to you.';
            $messageType = 'error';
        }
    }
}

$issues = [];
$issueStatement = $conn->prepare("SELECT DISTINCT r.id, r.category, r.floor, r.location, r.description, r.status, ia.progress_description, r.created_at, ia.assignment_start, ia.assignment_end
    FROM reports r
    LEFT JOIN issue_assignments ia ON ia.report_id = r.id AND ia.staff_id = ?
    WHERE ((ia.staff_id = ? AND CURRENT_DATE BETWEEN ia.assignment_start AND ia.assignment_end)
        OR r.assigned_to = ?)
      AND r.status IN ('Pending', 'In Progress')
    ORDER BY ia.assignment_end ASC, r.created_at DESC");
$issueStatement->bind_param('iii', $userId, $userId, $userId);
$issueStatement->execute();
$issueResult = $issueStatement->get_result();
while ($issue = $issueResult->fetch_assoc()) {
    $issues[] = $issue;
}
$issueStatement->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Assigned Issues</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .dashboard-hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 42%, #0ea5e9 100%); }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-white">
<?php include 'components/navbar.php'; ?>
  <div class="flex min-h-screen">
  <?php 
  $view = 'assigned_issues';
  include 'components/admin_sidebar.php'; 
  ?>
  <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <section class="dashboard-hero mb-8 overflow-hidden rounded-[2rem] border border-white/10 p-6 text-white shadow-[0_20px_50px_rgba(37,99,235,0.28)]">
      <p class="text-xs font-bold uppercase tracking-[0.3em] text-cyan-200">AFM staff workspace</p>
      <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-3xl font-black tracking-tight sm:text-4xl">My assigned issues</h1>
          <p class="mt-2 text-sm text-cyan-100">Review the issues assigned to you today and keep their status current.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">Active today</p>
          <p class="mt-1 text-3xl font-black"><?php echo count($issues); ?></p>
        </div>
      </div>
    </section>

    <?php if ($message !== ''): ?>
      <div class="mb-6 rounded-xl border px-4 py-3 text-sm font-medium <?php echo $messageType === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700 dark:border-rose-900 dark:bg-rose-950/40 dark:text-rose-300' : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'; ?>" role="alert"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if (!$issues): ?>
      <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V9l-6-6H9z M14 3v6h6 M7 13h10M7 17h6"/></svg>
        </div>
        <h2 class="mt-5 text-lg font-bold">No issues assigned today</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">Pending and in-progress assignments will appear here. Resolved issues are no longer shown.</p>
      </section>
    <?php else: ?>
      <section>
        <?php foreach ($issues as $issue):
          $status = trim($issue['status']);
          $statusClass = $status === 'Resolved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : ($status === 'in progress' ? 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300');
        ?>
          <article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start justify-between gap-4">
              <div>
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-cyan-600 dark:text-cyan-300">Issue #<?php echo (int)$issue['id']; ?></p>
                <h2 class="mt-2 text-xl font-bold"><?php echo htmlspecialchars($issue['category']); ?></h2>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-bold <?php echo $statusClass; ?>"><?php echo $issue['status'] === 'Resolved' ? 'Completed' : htmlspecialchars($issue['status']); ?></span>
            </div>
            <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars($issue['floor']); ?></span>
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars($issue['location']); ?></span>
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars(date('d M', strtotime($issue['assignment_start']))); ?> - <?php echo htmlspecialchars(date('d M Y', strtotime($issue['assignment_end']))); ?></span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($issue['description']); ?></p>
            <form method="post" class="mt-5 flex flex-col gap-3 border-t border-slate-100 pt-5 dark:border-slate-800">
              <input type="hidden" name="report_id" value="<?php echo (int)$issue['id']; ?>">
              <div class="sm:flex-row sm:items-end">
                <label for="progress_description-<?php echo (int)$issue['id']; ?>" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Progress Description</label>
                <?php if ($issue['status'] !== 'Resolved'): ?>
                  <textarea id="progress_description-<?php echo (int)$issue['id']; ?>" name="progress_description" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800" rows="3"><?php echo htmlspecialchars($issue['progress_description']); ?></textarea>
                <?php else: ?>
                  <p class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800"><?php echo htmlspecialchars($issue['progress_description'] ?? 'No progress description provided.'); ?></p>
                <?php endif; ?>
              </div>
              <div class="flex-1">
                <?php if ($issue['status'] !== 'Resolved'): ?>
                  <label for="status-<?php echo (int)$issue['id']; ?>" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Update status</label>
                  <select id="status-<?php echo (int)$issue['id']; ?>" name="status" class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800" required>
                    <?php foreach (['Pending', 'In Progress', 'Resolved'] as $option): ?>
                      <option value="<?php echo $option; ?>" <?php echo $issue['status'] === $option ? 'selected' : ''; ?>><?php echo $option === 'Resolved' ? 'Completed' : $option; ?></option>
                    <?php endforeach; ?>
                  </select>
                <?php else: ?>
                  <label for="status-<?php echo (int)$issue['id']; ?>" class="mb-2 block text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Update status</label>
                  <p class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm outline-none focus:border-cyan-500 dark:border-slate-700 dark:bg-slate-800">Completed</p>
                <?php endif; ?>
              </div>
              <?php if ($issue['status'] !== 'Resolved'): ?>
                <button type="submit" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 hover:bg-cyan-500">Save status</button>
              <?php else: ?>
                <p class="text-center mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400">This issue has been completed and cannot be updated.</p>
              <?php endif; ?>
              </form>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
  <script>
    window.latestAssignmentId = <?php echo $latestAssignmentId; ?>;

    (() => {
      let checkingForAssignments = false;

      async function checkForNewAssignments() {
        if (checkingForAssignments || document.hidden) return;
        checkingForAssignments = true;

        try {
          const response = await fetch('assigned_issues.php?check_new_assignments=1', {
            cache: 'no-store',
            headers: { Accept: 'application/json' }
          });
          if (!response.ok) throw new Error(`Assignment check failed (${response.status}).`);

          const data = await response.json();
          if (!data.success || !Number.isFinite(Number(data.latestAssignmentId))) {
            throw new Error(data.error || 'The assignment check returned an invalid response.');
          }

          if (Number(data.latestAssignmentId) > window.latestAssignmentId) {
            window.location.reload();
          }
        } catch (error) {
          console.error('Unable to check for new assigned tasks:', error);
        } finally {
          checkingForAssignments = false;
        }
      }

      window.setInterval(checkForNewAssignments, 15000);
      document.addEventListener('visibilitychange', checkForNewAssignments);
    })();
  </script>
</body>
</html>
