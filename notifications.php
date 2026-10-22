<?php
require_once 'auth.php';
require_login();
$user = current_user();
$userId = (int) $user['id'];

$notifications = [];
$stmt = $conn->prepare("SELECT id, floor, category, location, description, status,updated_at FROM reports WHERE user_id = ? AND status IN ('Resolved', 'In Progress') ORDER BY updated_at DESC");
if ($stmt) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
}

$newIssuesNoti = [];
$isStaff = $user['role'] === 'admin' || $user['role'] === 'helper';
$newIssuesSql = "SELECT id, floor, category, location, description, status, updated_at
         FROM reports
         WHERE status IN ('Pending', 'In Progress', 'Resolved')";
if (!$isStaff) {
  $newIssuesSql .= " AND user_id = ?";
}
$newIssuesSql .= " ORDER BY updated_at DESC";
$stmt = $conn->prepare($newIssuesSql);
if ($stmt) {
  if (!$isStaff) {
    $stmt->bind_param('i', $userId);
  }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $newIssuesNoti[] = $row;
    }
    $stmt->close();
}

$resolvedCount = count($notifications);
$newIssuesCount = count($newIssuesNoti);
$latestNotification = $notifications[0]['updated_at'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications | Facility System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .dashboard-hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 42%, #0ea5e9 100%); }
  </style>
</head>
<body class="bg-slate-100 dark:bg-slate-900 transition-colors duration-300 min-h-screen">
  <?php include 'components/navbar.php'; ?>

  <div class="flex min-h-[calc(100vh-4rem)]">
    <?php
      $view = 'notifications';
      if ($user['role'] === 'admin' || $user['role'] === 'helper') {
          include 'components/admin_sidebar.php';
      } else {
          include 'components/sidebar.php';
      }
    ?>
    <main class="min-w-0 flex-1 px-4 py-6 sm:px-6 lg:px-10 md:ml-32">
      <div class="mx-auto max-w-5xl">
        <section class="dashboard-hero mb-8 overflow-hidden rounded-[2rem] border border-white/10 p-6 text-white shadow-[0_20px_50px_rgba(37,99,235,0.28)]">
          <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
            <div>
              <p class="text-xs font-bold uppercase tracking-[0.3em] text-cyan-200">Your inbox</p>
              <h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Notifications</h1>
              <p class="mt-3 max-w-xl text-sm leading-6 text-blue-100">Updates about the facility issues you have reported and their resolution.</p>
            </div>
            <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">Total Reports</p>
              <?php
              if ($user['role'] === 'user') {
                echo '<p class="mt-1 text-3xl font-black">' . $resolvedCount . '</p>';
              } else {
                echo '<p class="mt-1 text-3xl font-black">' . $newIssuesCount . '</p>';
              }
              ?>
            </div>
          </div>
        </section>

        <section class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">Completed issue messages</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
              <?php if ($latestNotification): ?>
                Latest update <?php echo htmlspecialchars(date('d M Y, H:i', strtotime($latestNotification))); ?>
              <?php else: ?>
                Your completed issue updates will appear here.
              <?php endif; ?>
            </p>
          </div>
          <select id="notificationFilter" class="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-200 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
            <option value="all">All messages</option>
            <option value="recent">Completed in the last 30 days</option>
          </select>
        </section>
          <?php if ($user['role'] === 'user'): ?>
            <?php if ($resolvedCount === 0): ?>
              <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300">
                  <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/></svg>
                </div>
                <h2 class="mt-5 text-lg font-bold text-slate-900 dark:text-white">No updates yet</h2>
                <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">When one of your reports is completed, the confirmation message will be saved here.</p>
                <a href="dashboard.php" class="mt-6 inline-flex rounded-xl bg-cyan-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 transition hover:bg-cyan-500">View dashboard</a>
                </div>
            <?php else: ?>
              <div id="notificationList" class="space-y-4">
                <?php foreach ($notifications as $notification):
                    $updatedAt = strtotime($notification['updated_at']);
                    $isRecent = $updatedAt >= strtotime('-30 days');
                ?>
                  <article class="notification-item group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-cyan-700" data-recent="<?php echo $isRecent ? 'true' : 'false'; ?>">
                    <div class="flex gap-4">
                      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </div>
                      <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                          <?php if ($notification['status'] === 'Resolved'): ?>
                            <h3 class="font-bold text-slate-900 dark:text-white">The issue has been completed</h3>
                          <?php elseif ($notification['status'] === 'In Progress'): ?>
                            <h3 class="font-bold text-slate-900 dark:text-white">Issue in progress</h3>
                          <?php endif; ?>
                          <time class="shrink-0 text-xs font-medium text-slate-400" datetime="<?php echo htmlspecialchars($notification['updated_at']); ?>"><?php echo htmlspecialchars(date('d M Y, H:i', $updatedAt)); ?></time>
                        </div>
                        <?php if ($notification['status'] === 'Resolved'): ?>
                          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The facilities team has completed work on your <span class="font-semibold"><?php echo htmlspecialchars($notification['category']); ?></span> report. Thank you for helping us keep the facility in good condition.</p>
                        <?php elseif ($notification['status'] === 'In Progress'): ?>
                          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The issue <span class="font-semibold"><?php echo htmlspecialchars($notification['category']); ?></span> is currently being addressed.</p>
                        <?php endif; ?>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                          <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo htmlspecialchars($notification['floor']); ?></span>
                          <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo htmlspecialchars($notification['location']); ?></span>
                          <?php if ($notification['status'] === 'Resolved'): ?>
                            <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">Completed</span>
                          <?php elseif ($notification['status'] === 'In Progress'): ?>
                            <span class="rounded-lg bg-sky-50 px-3 py-1.5 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300">In Progress</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <p id="noFilteredNotifications" class="mt-6 hidden rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">No messages match this filter.</p>
            <?php endif; ?>
            <?php elseif ($user['role'] === 'admin' || $user['role'] === 'helper'): ?>
              <?php if ($newIssuesCount === 0): ?>
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
                  <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300">
                    <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 00-12 0v3.2a2 2 0 01-.6 1.4L4 17h5m6 0a3 3 0 11-6 0m6 0H9"/></svg>
                  </div>
                  <h2 class="mt-5 text-lg font-bold text-slate-900 dark:text-white">No updates yet</h2>
                  <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">When the issue is submitted or updated, the confirmation message will be saved here.</p>
                  <a href="dashboard.php" class="mt-6 inline-flex rounded-xl bg-cyan-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-cyan-600/20 transition hover:bg-cyan-500">View dashboard</a>
                  </div>
              <?php else: ?>
              <div id="notificationList" class="space-y-4">
                <?php foreach ($newIssuesNoti as $notification):
                    $updatedAt = strtotime($notification['updated_at']);
                    $isRecent = $updatedAt >= strtotime('-30 days');
                ?>
                    <article class="notification-item group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-cyan-300 hover:shadow-md dark:border-slate-800 dark:bg-slate-900 dark:hover:border-cyan-700" data-recent="<?php echo $isRecent ? 'true' : 'false'; ?>">
                    <div class="flex gap-4">
                      <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600 dark:bg-emerald-950/50 dark:text-emerald-300">
                        <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                      </div>
                      <div class="min-w-0 flex-1">
                        <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                          <?php if ($notification['status'] === 'Resolved'): ?>
                            <h3 class="font-bold text-slate-900 dark:text-white">The issue has been completed</h3>
                          <?php elseif ($notification['status'] === 'Pending'): ?>
                            <h3 class="font-bold text-slate-900 dark:text-white">New issue reported</h3>
                          <?php elseif ($notification['status'] === 'In Progress'): ?>
                            <h3 class="font-bold text-slate-900 dark:text-white">Issue in progress</h3>
                          <?php endif; ?>
                          <time class="shrink-0 text-xs font-medium text-slate-400" datetime="<?php echo htmlspecialchars($notification['updated_at']); ?>"><?php echo htmlspecialchars(date('d M Y, H:i', $updatedAt)); ?></time>
                        </div>
                        <?php if ($notification['status'] === 'Resolved'): ?>
                          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The issue <span class="font-semibold"><?php echo htmlspecialchars($notification['category']); ?></span> has been completed.</p>
                        <?php elseif ($notification['status'] === 'Pending'): ?>
                          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The issue <span class="font-semibold"><?php echo htmlspecialchars($notification['category']); ?></span> has been reported and is pending assignment.</p>
                        <?php elseif ($notification['status'] === 'In Progress'): ?>
                          <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">The issue <span class="font-semibold"><?php echo htmlspecialchars($notification['category']); ?></span> is currently being addressed.</p>
                        <?php endif; ?>
                        <div class="mt-4 flex flex-wrap gap-2 text-xs font-semibold">
                          <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo htmlspecialchars($notification['floor']); ?></span>
                          <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-slate-600 dark:bg-slate-800 dark:text-slate-300"><?php echo htmlspecialchars($notification['location']); ?></span>
                          
                          <?php if ($notification['status'] === 'Resolved'): ?>
                            <span class="rounded-lg bg-emerald-50 px-3 py-1.5 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">Completed</span>
                          <?php elseif ($notification['status'] === 'Pending'): ?>
                          <span class="rounded-lg bg-blue-50 px-3 py-1.5 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300">Pending</span>
                          <?php elseif ($notification['status'] === 'In Progress'): ?>
                            <span class="rounded-lg bg-yellow-50 px-3 py-1.5 text-yellow-700 dark:bg-yellow-950/50 dark:text-yellow-300">In Progress</span>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </article>
                <?php endforeach; ?>
              </div>
              <p id="noFilteredNotifications" class="mt-6 hidden rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400">No messages match this filter.</p>
            <?php endif; ?>
          <?php endif; ?>
      </div>
    </main>
  </div>

  <script>
    const notificationFilter = document.getElementById('notificationFilter');
    const notificationItems = document.querySelectorAll('.notification-item');
    const noFilteredNotifications = document.getElementById('noFilteredNotifications');

    if (notificationFilter) {
      notificationFilter.addEventListener('change', function () {
        const showRecentOnly = this.value === 'recent';
        let visibleCount = 0;
        notificationItems.forEach(function (item) {
          const visible = !showRecentOnly || item.dataset.recent === 'true';
          item.classList.toggle('hidden', !visible);
          if (visible) visibleCount++;
        });
        if (noFilteredNotifications) noFilteredNotifications.classList.toggle('hidden', visibleCount !== 0);
      });
    }
  </script>
</body>
</html>
