<?php
require_once 'auth.php';
require_login('admin');
$issueStatement = $conn->prepare("
    SELECT DISTINCT
        r.id,
        r.category,
        r.floor,
        r.location,
        r.description,
        r.status,
        ia.progress_description,
        u.name AS assigned_name,
        r.created_at,
        ia.assignment_start,
        ia.assignment_end
    FROM reports r
    INNER JOIN issue_assignments ia
        ON ia.report_id = r.id
       AND CURRENT_DATE BETWEEN ia.assignment_start AND ia.assignment_end
    LEFT JOIN users u
        ON u.id = ia.staff_id
    ORDER BY r.created_at DESC, r.id DESC
");
$issues = [];

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
  <title>Assigned Issue Progress | Facility System</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    .dashboard-hero { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 42%, #0ea5e9 100%); }
  </style>
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-white">
<?php include 'components/navbar.php'; ?>
  <div class="flex min-h-screen">
  <?php 
  $view = 'assigned_issue_progress';
  include 'components/admin_sidebar.php'; 
  ?>
  <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6 lg:px-8">
    <section class="dashboard-hero mb-8 overflow-hidden rounded-[2rem] border border-white/10 p-6 text-white shadow-[0_20px_50px_rgba(37,99,235,0.28)]">
      <p class="text-xs font-bold uppercase tracking-[0.3em] text-cyan-200">Head of AFM workspace</p>
      <div class="mt-3 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 class="text-3xl font-black tracking-tight sm:text-4xl">Assigned issue progress</h1>
          <p class="mt-2 text-sm text-cyan-100">View all currently assigned issues and the latest progress descriptions.</p>
        </div>
        <div class="rounded-2xl border border-white/15 bg-white/10 px-5 py-4 backdrop-blur-sm">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-cyan-100">Active assignments</p>
          <p class="mt-1 text-3xl font-black"><?php echo count($issues); ?></p>
        </div>
      </div>
    </section>

    <?php if (!$issues): ?>
      <section class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-16 text-center shadow-sm dark:border-slate-700 dark:bg-slate-900">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-950/60 dark:text-cyan-300">
          <svg class="h-8 w-8" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 5H5a2 2 0 00-2 2v10a2 2 0 002 2h14a2 2 0 002-2V9l-6-6H9z M14 3v6h6 M7 13h10M7 17h6"/></svg>
        </div>
        <h2 class="mt-5 text-lg font-bold">No active assignments</h2>
        <p class="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500 dark:text-slate-400">Current helper assignments and their progress descriptions will appear here.</p>
      </section>
    <?php else: ?>
      <section>
        <?php foreach ($issues as $issue):
          $status = strtolower(trim($issue['status']));
          $statusClass = $status === 'resolved' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300' : ($status === 'in progress' ? 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300');
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
              <span class="rounded-lg bg-cyan-50 px-3 py-1.5 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300">Assigned to: <?php echo htmlspecialchars($issue['assigned_name'] ?? 'Assigned staff'); ?></span>
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars($issue['floor']); ?></span>
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars($issue['location']); ?></span>
              <span class="rounded-lg bg-slate-100 px-3 py-1.5 dark:bg-slate-800"><?php echo htmlspecialchars(date('d M', strtotime($issue['assignment_start']))); ?> - <?php echo htmlspecialchars(date('d M Y', strtotime($issue['assignment_end']))); ?></span>
            </div>
            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($issue['description']); ?></p>
            <div class="mt-5 border-t border-slate-100 pt-5 dark:border-slate-800">
              <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Progress description</p>
              <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-600 dark:text-slate-300"><?php echo htmlspecialchars($issue['progress_description'] ?? 'No progress description provided.'); ?></p>
            </div>
          </article>
        <?php endforeach; ?>
      </section>
    <?php endif; ?>
  </main>
</body>
</html>
