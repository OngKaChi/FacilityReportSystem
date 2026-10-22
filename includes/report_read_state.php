<?php
function render_unread_report_dot(int $reportId): string
{
    return '<span class="report-unread-dot absolute left-1 top-1 h-2.5 w-2.5 rounded-full bg-red-500 shadow-sm shadow-red-500/50" data-report-unread="' . $reportId . '" title="New report"></span>';
}
