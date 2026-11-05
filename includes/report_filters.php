<?php

function getReportFilters(array $query): array
{
    $selectedMonth = $query['month'] ?? date('Y-m');
    $search = trim($query['search'] ?? '');
    $status = $query['status'] ?? '';
    $dateStart = $query['date_start'] ?? '';
    $dateEnd = $query['date_end'] ?? '';
    $dateFilterType = $query['date_filter'] ?? 'month';

    if ($dateFilterType === 'range') {
        $selectedMonth = '';
    } elseif (!preg_match('/^\d{4}-\d{2}$/', $selectedMonth)) {
        $selectedMonth = date('Y-m');
    }

    return [
        'selectedMonth' => $selectedMonth,
        'search' => $search,
        'status' => $status,
        'dateStart' => $dateStart,
        'dateEnd' => $dateEnd,
        'dateFilterType' => $dateFilterType,
    ];
}

function buildReportFilterClause(array $filters): array
{
    $conditions = [];
    $params = [];
    $types = '';

    if ($filters['dateFilterType'] !== 'range' && preg_match('/^\d{4}-\d{2}$/', $filters['selectedMonth'])) {
        $conditions[] = "DATE_FORMAT(r.created_at, '%Y-%m') = ?";
        $params[] = $filters['selectedMonth'];
        $types .= 's';
    }

    if ($filters['search'] !== '') {
        $conditions[] = '(r.description LIKE ? OR r.location LIKE ?)';
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }

    if (in_array($filters['status'], ['Pending', 'In Progress', 'Resolved'], true)) {
        $conditions[] = 'r.status = ?';
        $params[] = $filters['status'];
        $types .= 's';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['dateStart'])) {
        $conditions[] = 'DATE(r.created_at) >= ?';
        $params[] = $filters['dateStart'];
        $types .= 's';
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $filters['dateEnd'])) {
        $conditions[] = 'DATE(r.created_at) <= ?';
        $params[] = $filters['dateEnd'];
        $types .= 's';
    }

    return [
        'where' => $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '',
        'params' => $params,
        'types' => $types,
    ];
}

function buildReportPaginationQuery(array $filters, array $extra = []): array
{
    $query = ['view' => 'reports'];

    if ($filters['search'] !== '') {
        $query['search'] = $filters['search'];
    }

    if ($filters['status'] !== '') {
        $query['status'] = $filters['status'];
    }

    if ($filters['dateFilterType'] === 'range') {
        $query['date_filter'] = 'range';
        if ($filters['dateStart'] !== '') {
            $query['date_start'] = $filters['dateStart'];
        }
        if ($filters['dateEnd'] !== '') {
            $query['date_end'] = $filters['dateEnd'];
        }
    } else {
        $query['date_filter'] = 'month';
        if ($filters['selectedMonth'] !== '') {
            $query['month'] = $filters['selectedMonth'];
        }
    }

    foreach ($extra as $key => $value) {
        if ($value !== '' && $value !== null) {
            $query[$key] = $value;
        }
    }

    return $query;
}
