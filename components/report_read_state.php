<script>
function markReportAsSeen(reportId) {
  const seenReports = JSON.parse(localStorage.getItem(window.seenReportsStorageKey) || '[]');
  const numericReportId = Number(reportId);

  if (!seenReports.includes(numericReportId)) {
    seenReports.push(numericReportId);
    localStorage.setItem(window.seenReportsStorageKey, JSON.stringify(seenReports));
  }

  document.querySelector(`[data-report-unread="${reportId}"]`)?.remove();
}

function initializeUnreadReportDots() {
  const seenReports = JSON.parse(localStorage.getItem(window.seenReportsStorageKey) || '[]');
  document.querySelectorAll('.report-unread-dot').forEach((dot) => {
    if (seenReports.includes(Number(dot.dataset.reportUnread))) dot.remove();
  });
}

document.addEventListener('DOMContentLoaded', initializeUnreadReportDots);
</script>