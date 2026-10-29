<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user = $_SESSION['user'] ?? null;
?>
<script>
    // Check for saved theme preference or system preference on load
    if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        document.documentElement.classList.add('dark');
    } else {
        document.documentElement.classList.remove('dark');
    }
    
    // Configure Tailwind to use the 'class' strategy for dark mode
    window.tailwind = window.tailwind || {};
    window.tailwind.config = window.tailwind.config || {};
    window.tailwind.config.darkMode = 'class';
</script>

<nav class="sticky top-0 z-50 border-b border-blue-200/20 bg-white/75 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/75 text-slate-800 shadow-[0_8px_30px_rgba(15,23,42,0.08)] dark:text-white transition-all duration-300">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">

      <div class="flex items-center">
        <a href="<?php echo ($user ? ($_SESSION['login_role'] === 'admin' ? 'admindashboard.php' : 'dashboard.php') : 'Login.php'); ?>"
           class="flex items-center gap-3 group transition-transform duration-200 hover:scale-[1.02]">
          <div class="bg-gradient-to-br from-blue-600 to-cyan-500 p-2 rounded-xl shadow-lg shadow-blue-500/20 ring-1 ring-white/40">
            <img src="./img/logo.png" alt="INTI Logo" class="h-7 w-auto">
          </div>
          <div class="leading-none">
            <span class="block text-lg font-black tracking-tight text-slate-900 dark:text-white">Facility<span class="text-blue-600 dark:text-blue-300">System</span></span>
          </div>
        </a>
      </div>

      <div class="flex items-center gap-3">
        <button id="theme-toggle" type="button" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 p-2.5 text-slate-700 shadow-sm transition-all hover:border-blue-200 hover:bg-blue-50 hover:text-blue-700 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-blue-500/40 dark:hover:bg-slate-700" title="Toggle Light/Dark Mode">
          <svg id="theme-toggle-dark-icon" class="hidden h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
          <svg id="theme-toggle-light-icon" class="hidden h-5 w-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 100 2h1z"></path></svg>
        </button>

        <div class="hidden md:flex items-center gap-5">
          <?php if ($user): ?>
            <div class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50/80 px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-800/80">
              <div class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-600 to-cyan-500 text-sm font-bold text-white shadow-md">
                <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
              </div>
              <div class="hidden lg:block text-left">
                <p class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($user['name']); ?></p>
                <p class="text-[10px] uppercase tracking-[0.18em] text-slate-500 dark:text-slate-300">
                  <?php
                    $actualRole = $user['role'] ?? 'user';
                    if($actualRole === 'admin') echo 'Head of AFM';
                    elseif($actualRole === 'helper') echo 'AFM Staff';
                    else echo ucfirst(htmlspecialchars($actualRole));
                  ?>
                </p>
              </div>
            </div>

            <?php if (!in_array($user['role'] ?? '', ['admin', 'helper'])): ?>
              <a href="achievements.php" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-blue-300">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-7.714 2.143L11 21l-2.143-7.714L1 12l6.857-2.286L11 3z"></path></svg>
                Achievements
              </a>
            <?php endif; ?>

            <a href="profile.php" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-blue-300">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
              Profile
            </a>

            <a href="notifications.php" aria-label="Notifications" title="Notifications" class="inline-flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-medium text-slate-600 transition-colors hover:bg-blue-50 hover:text-blue-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:hover:text-blue-300">
              <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/></svg>
            </a>

            <a href="logout.php" class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-4 py-2 text-sm font-bold text-white shadow-lg shadow-slate-900/15 transition-all hover:-translate-y-0.5 hover:bg-slate-700 dark:bg-white dark:text-slate-900 dark:hover:bg-slate-100">
              Logout
            </a>
          <?php else: ?>
            <a href="Login.php" class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-blue-500/20 transition-all hover:-translate-y-0.5 hover:bg-blue-500">
              Login
            </a>
          <?php endif; ?>
        </div>

        <div class="md:hidden flex items-center">
          <button onclick="toggleMenu()" class="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-slate-100 p-2 text-slate-700 transition-colors hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200">
            <svg id="menuIcon" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7"></path>
            </svg>
          </button>
        </div>
      </div>
    </div>
  </div>

  <div id="mobileMenu" class=" absolute left-0 right-0 top-full shadow-xl hidden md:hidden border-t border-slate-200 bg-white/95 backdrop-blur-xl dark:border-slate-700 dark:bg-slate-900/95 animate-slide-down">
    <div class="space-y-2 px-4 py-4">
      <?php if ($user): ?>
        <div class="mb-2 flex items-center gap-3 rounded-2xl bg-slate-100 p-3 dark:bg-slate-800">
          <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-gradient-to-br from-blue-600 to-cyan-500 font-bold text-white">
            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
          </div>
          <div>
            <p class="text-sm font-semibold"><?php echo htmlspecialchars($user['name']); ?></p>
            <p class="text-xs text-slate-500 dark:text-slate-300"><?php echo htmlspecialchars($user['email']); ?></p>
          </div>
        </div>
        <a href="profile.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Profile</a>
        <?php if (($user['role'] ?? '') === 'admin'): ?>
          <a href="admindashboard.php?view=dashboard" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Statistics</a>
          <a href="admindashboard.php?view=map" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Map</a>
          <a href="admindashboard.php?view=reports" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Reports</a>
          <a href="admindashboard.php?view=users" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Users</a>
          <a href="manage_helpers.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">AFM Staff</a>
          <a href="assigned_issue_progress.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Assigned Issue Progress</a>
        <?php elseif (($user['role'] ?? '') === 'helper'): ?>
          <a href="assigned_issues.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">My Issues</a>
          <a href="admindashboard.php?view=map" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Map</a>
          <a href="admindashboard.php?view=reports" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Reports</a>
          <a href="admindashboard.php?view=users" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Users</a>
        <?php elseif (($user['role'] ?? '') === 'user'): ?>
          <a href="dashboard.php?view=map" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Map</a>
          <a href="dashboard.php?view=reports" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Reports</a>
          <a href="achievements.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Achievements</a>
        <?php endif; ?>
        <a href="notifications.php" class="block rounded-xl px-4 py-3 text-sm font-medium text-slate-700 transition-colors hover:bg-slate-100 dark:text-slate-200 dark:hover:bg-slate-800">Notifications</a>
        <a href="logout.php" class="block rounded-xl bg-red-500/10 px-4 py-3 text-sm font-semibold text-red-600 transition-colors hover:bg-red-500/20 dark:text-red-300">Logout</a>
      <?php else: ?>
        <a href="Login.php" class="block rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-bold text-white">Login</a>
      <?php endif; ?>
    </div>
  </div>
</nav>

<style>
@keyframes slide-down {
  from { opacity: 0; transform: translateY(-10px); }
  to { opacity: 1; transform: translateY(0); }
}
.animate-slide-down { animation: slide-down 0.2s ease-out forwards; }
</style>

<script>
function toggleMenu() {
  const menu = document.getElementById("mobileMenu");
  menu.classList.toggle("hidden");
}

const themeToggleDarkIcon = document.getElementById('theme-toggle-dark-icon');
const themeToggleLightIcon = document.getElementById('theme-toggle-light-icon');

// Set correct icon on load based on document class
if (document.documentElement.classList.contains('dark')) {
    themeToggleLightIcon.classList.remove('hidden');
} else {
    themeToggleDarkIcon.classList.remove('hidden');
}

const themeToggleBtn = document.getElementById('theme-toggle');

themeToggleBtn.addEventListener('click', function() {
    // toggle icons inside button
    themeToggleDarkIcon.classList.toggle('hidden');
    themeToggleLightIcon.classList.toggle('hidden');

    // if set via local storage previously
    if (localStorage.getItem('color-theme')) {
        if (localStorage.getItem('color-theme') === 'light') {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        }
    // if NOT set via local storage previously
    } else {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('color-theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('color-theme', 'dark');
        }
    }
});
</script>