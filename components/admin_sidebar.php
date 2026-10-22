<?php
$currentView = $view ?? '';
$navItems = [
  ['key' => 'dashboard', 'label' => 'Statistics', 'href' => 'admindashboard.php?view=dashboard'],
  ['key' => 'map', 'label' => 'Map', 'href' => 'admindashboard.php?view=map'],
  ['key' => 'reports', 'label' => 'Reports', 'href' => 'admindashboard.php?view=reports'],
  ['key' => 'users', 'label' => 'Users', 'href' => 'admindashboard.php?view=users'],
];
?>
<aside id="adminSidebar" class="w-64 md:w-64 bg-slate-950 text-slate-100 flex-col items-center py-6 gap-4 shadow-[0_24px_60px_rgba(15,23,42,0.35)] fixed md:sticky top-16 h-[calc(100vh-64px)] z-40 transition-transform duration-300 -translate-x-full md:translate-x-0 flex rounded-r-[2rem] md:rounded-r-[2rem]">
  <div class="w-full px-3">
    <div class="flex flex-col gap-2 rounded-2xl border border-slate-800 bg-slate-900/70 p-2 shadow-inner shadow-slate-950/50">
      <?php foreach ($navItems as $item): ?>
        <a href="<?php echo $item['href']; ?>"
           class="group relative flex w-full items-center justify-center gap-2 rounded-xl px-2 py-3 text-[14px] font-bold uppercase tracking-[0.18em] transition-all duration-200 <?php echo $currentView === $item['key'] ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="<?php echo $item['label']; ?>">
          <span class="flex items-center justify-center transition-transform duration-200 group-hover:scale-110"></span>
          <span class="leading-none"><?php echo $item['label']; ?></span>
        </a>
      <?php endforeach; ?>

      <?php if ($_SESSION['user']['role'] === 'admin'): ?>
        <a href="manage_helpers.php"
           class="group relative flex w-full items-center justify-center gap-2 rounded-xl px-2 py-3 text-[14px] font-bold uppercase tracking-[0.14em] transition-all duration-200 <?php echo $currentView === 'helpers' ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="Manage AFM Staff">
          <span class="flex items-center justify-center transition-transform duration-200 group-hover:scale-110"></span>
          <span class="leading-none">AFM Staff</span>
        </a>
        <a href="assigned_issue_progress.php"
           class="group relative flex w-full items-center justify-center gap-2 rounded-xl px-2 py-3 text-[14px] font-bold uppercase tracking-[0.14em] transition-all duration-200 <?php echo $currentView === 'assigned_issue_progress' ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="Assigned Issues">
          <span class="flex items-center justify-center transition-transform duration-200 group-hover:scale-110"></span>
          <span class="leading-none">Assigned Issues</span>
        </a>
      <?php endif; ?>

      <?php if ($_SESSION['user']['role'] === 'helper'): ?>
        <a href="assigned_issues.php"
           class="group relative flex w-full items-center justify-center gap-2 rounded-xl px-2 py-3 text-[14px] font-bold uppercase tracking-[0.14em] transition-all duration-200 <?php echo $currentView === 'assigned_issues' ? 'bg-gradient-to-r from-blue-600 to-blue-500 text-white shadow-lg shadow-blue-500/20' : 'text-slate-300 hover:bg-slate-800 hover:text-white'; ?>"
           title="My Issues">
          <span class="flex items-center justify-center transition-transform duration-200 group-hover:scale-110"></span>
          <span class="leading-none">My Issues</span>
        </a>
      <?php endif; ?>
    </div>
  </div>
</aside>