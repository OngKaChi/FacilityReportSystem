<?php
require_once 'auth.php';
require_login();
$user = current_user();

// Fetch total reports for progress bar
$countCompletedRes = $conn->query("SELECT COUNT(*) as total FROM reports WHERE user_id = " . $user['id'] . " AND status = 'Resolved'");
$reportCompletedCount = $countCompletedRes->fetch_assoc()['total'];

// Fetch all achievements and check if user has them
$sql = "SELECT a.*, ua.earned_at 
        FROM achievements a 
        LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = " . $user['id'] . "
        ORDER BY a.threshold ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Your Achievements</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 dark:bg-slate-900 transition-colors duration-300 min-h-screen">

<?php include 'components/navbar.php'; ?>
  <div class="flex min-h-[calc(100vh-4rem)]">
    <?php
      if ($user['role'] === 'admin' || $user['role'] === 'helper') {
          include 'components/admin_sidebar.php';
      } else {
          include 'components/sidebar.php';
      }
    ?>

<div class="max-w-5xl mx-auto p-6">
  <div class="mb-8">
    <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Reporting Achievements</h1>
    <p class="text-gray-600 dark:text-gray-400 mt-2">Earn badges by helping us keep the facility in top shape!</p>
  </div>

  <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-sm p-6 mb-8 border dark:border-slate-700">
    <div class="flex items-center justify-between mb-4">
      <span class="text-sm font-semibold text-gray-600 dark:text-gray-400 uppercase tracking-wider">Overall Progress</span>
      <span class="text-2xl font-bold text-blue-600"><?php echo $reportCompletedCount; ?> Reports</span>
    </div>
    <div class="w-full bg-gray-200 dark:bg-slate-700 rounded-full h-4">
      <div class="bg-blue-600 h-4 rounded-full transition-all duration-1000" style="width: <?php echo min(($reportCompletedCount / 25) * 100, 100); ?>%"></div>
    </div>
  </div>

  <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <?php while($row = $result->fetch_assoc()): 
        $isEarned = !empty($row['earned_at']);
    ?>
      <div class="relative overflow-hidden bg-white dark:bg-slate-800 rounded-2xl p-6 border transition-all duration-300 <?php echo $isEarned ? 'border-blue-500 shadow-md scale-105' : 'border-gray-200 dark:border-slate-700 opacity-60 grayscale'; ?>">
        
        <?php if($isEarned): ?>
          <div class="absolute -top-2 -right-2 bg-blue-500 text-white p-2 rounded-full transform rotate-12">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path></svg>
          </div>
        <?php endif; ?>

        <div class="text-5xl mb-4 text-center"><?php echo $row['icon']; ?></div>
        <h3 class="text-lg font-bold text-center text-gray-900 dark:text-white mb-1"><?php echo htmlspecialchars($row['title']); ?></h3>
        <p class="text-sm text-center text-gray-500 dark:text-gray-400 mb-4"><?php echo htmlspecialchars($row['description']); ?></p>
        
        <div class="mt-auto">
          <?php if($isEarned): ?>
            <div class="text-xs font-bold text-blue-600 text-center uppercase">Earned on <?php echo date('M j, Y', strtotime($row['earned_at'])); ?></div>
          <?php else: ?>
            <div class="text-xs font-semibold text-gray-400 text-center uppercase">Target: <?php echo $row['threshold']; ?> Reports</div>
          <?php endif; ?>
        </div>
      </div>
    <?php endwhile; ?>
  </div>
</div>

</body>
</html>