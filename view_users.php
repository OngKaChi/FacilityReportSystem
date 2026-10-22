<?php
require_once 'auth.php';
require_login('admin');

$usersResult = $conn->query("SELECT u.*, 
    (SELECT COUNT(*) FROM reports r WHERE r.user_id = u.id) as report_count,
    (SELECT GROUP_CONCAT(plate_number SEPARATOR ', ') FROM user_car_plates ucp WHERE ucp.user_id = u.id) as car_plates
    FROM users u ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>View Users - Head of AFM</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-100 dark:bg-slate-900 text-slate-900 dark:text-white transition-colors duration-300">

<?php include 'components/navbar.php'; ?>

<div class="flex min-h-screen">
  <!-- Sidebar -->
  <aside class="w-32 bg-slate-950 text-slate-100 flex flex-col items-center py-6 gap-6 shadow-xl sticky top-16 h-[calc(100vh-64px)] z-30 transition-all">
    <div class="text-2xl font-bold tracking-[0.2em]">FS</div>
    <a href="admindashboard.php?view=dashboard" class="w-24 py-3 rounded-2xl flex items-center justify-center text-xs font-bold bg-slate-900 hover:bg-slate-800 transition-all" title="Statistics Overview">STATISTICS</a>
    <a href="admindashboard.php?view=map" class="w-24 py-3 rounded-2xl flex items-center justify-center text-xs font-bold bg-slate-900 hover:bg-slate-800 transition-all" title="Facility Map">MAP</a>
    <a href="admindashboard.php?view=reports" class="w-24 py-3 rounded-2xl flex items-center justify-center text-xs font-bold bg-slate-900 hover:bg-slate-800 transition-all" title="Facility Table">REPORTS</a>
    <a href="admindashboard.php?view=users" class="w-24 py-3 rounded-2xl flex items-center justify-center text-xs font-bold bg-blue-600 shadow-lg shadow-blue-900/20 transition-all" title="Manage Users">USERS</a>
    <?php if ($_SESSION['user']['role'] === 'admin'): ?>
      <a href="manage_helpers.php" class="w-24 py-3 rounded-2xl flex items-center justify-center text-xs font-bold bg-slate-900 hover:bg-slate-800 transition-all" title="Manage AFM Staff">AFM STAFF</a>
    <?php endif; ?>
  </aside>

  <main class="flex-1 p-6 overflow-x-hidden">
    <div class="max-w-7xl mx-auto space-y-6">
      <header class="mb-8">
        <p class="text-sm uppercase tracking-[0.35em] text-slate-500 dark:text-slate-400">Dashboards / Users</p>
        <h1 class="mt-2 text-3xl font-bold text-slate-900 dark:text-white">Registered Users</h1>
      </header>

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
            <thead>
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
                  <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition cursor-pointer user-row" 
                      onclick='showUserDetails(<?php echo json_encode($row); ?>)'
                      data-name="<?php echo htmlspecialchars($row['name']); ?>"
                      data-plates="<?php echo htmlspecialchars($row['car_plates'] ?? ''); ?>">
                    <td class="py-4 px-3 font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row['name']); ?></td>
                    <td class="py-4 px-3"><?php echo htmlspecialchars($row['email']); ?></td>
                    <td class="py-4 px-3">
                      <span class="rounded-full px-3 py-1 text-xs <?php 
                        echo $row['role'] === 'admin' ? 'bg-rose-100 text-rose-700' : ($row['role'] === 'helper' ? 'bg-sky-100 text-sky-700' : 'bg-slate-100 text-slate-700'); 
                      ?>">
                        <?php 
                          if($row['role'] === 'admin') echo 'Head of AFM';
                          elseif($row['role'] === 'helper') echo 'AFM Staff';
                          else echo htmlspecialchars($row['role']); 
                        ?>
                      </span>
                    </td>
                    <td class="py-4 px-3"><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td>
                    <td class="py-4 px-3"><?php echo $row['report_count']; ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
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

<script>
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

// Close on outside click
window.onclick = function(event) {
    const modal = document.getElementById('detailsModal');
    if (event.target == modal) {
        closeModal();
    }
}
</script>

</body>
</html>