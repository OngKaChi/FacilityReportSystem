<?php
// Fetch latest updates (new reports or status changes) for the current day
$latestUpdatesQuery = $conn->query("SELECT category, status, description, updated_at FROM reports WHERE DATE(updated_at) = CURDATE() ORDER BY updated_at DESC");
$allLatestUpdates = [];
while ($row = $latestUpdatesQuery->fetch_assoc()) $allLatestUpdates[] = $row;
?>

<div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4" role="alert">
  <p class="font-bold">Latest Updates</p>
  <?php if (count($allLatestUpdates) === 0): ?>
    <p class="mt-2 text-sm">No new reports or status changes today.</p>
  <?php else: ?>
    <ul class="mt-2 text-sm list-disc list-inside space-y-1">
      <?php foreach ($allLatestUpdates as $update): ?>
        <li>
          <span class="font-semibold"><?php echo htmlspecialchars($update['category']); ?></span> - 
          <span class="italic"><?php echo htmlspecialchars($update['status']); ?></span>: 
          <?php echo htmlspecialchars($update['description']); ?> 
          <span class="text-gray-500">(<?php echo date('H:i', strtotime($update['updated_at'])); ?>)</span>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>