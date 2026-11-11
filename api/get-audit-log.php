<?php
/** Role-scoped administrative audit log. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed', 405);
}

try {
    $session = requireAuth();
    $role = (string)($session['role_name'] ?? '');
    if (!in_array($role, ['super_admin', 'hr_coordinator', 'department_supervisor'], true)) {
        ob_end_clean();
        json_response(false, null, 'Forbidden', 403);
    }

    $filters = [];
    if ($role === 'department_supervisor') {
        $departmentId = (int)($session['department_id'] ?? 0);
        if ($departmentId <= 0) {
            ob_end_clean();
            json_response(false, null, 'Department scope is unavailable', 403);
        }
        $filters['department_id'] = $departmentId;
    }

    $action = trim((string)($_GET['action'] ?? ''));
    if ($action !== '') {
        if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/', $action)) {
            ob_end_clean();
            json_response(false, null, 'Invalid action filter', 422);
        }
        $filters['action'] = $action;
    }
    foreach (['start_date', 'end_date'] as $dateKey) {
        $value = trim((string)($_GET[$dateKey] ?? ''));
        if ($value !== '') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                ob_end_clean();
                json_response(false, null, 'Date filters must use YYYY-MM-DD', 422);
            }
            $filters[$dateKey] = $value;
        }
    }
    if (isset($filters['start_date'], $filters['end_date']) && $filters['start_date'] > $filters['end_date']) {
        ob_end_clean();
        json_response(false, null, 'start_date must not be after end_date', 422);
    }
    $limit = isset($_GET['limit']) ? filter_var($_GET['limit'], FILTER_VALIDATE_INT) : 100;
    $offset = isset($_GET['offset']) ? filter_var($_GET['offset'], FILTER_VALIDATE_INT) : 0;
    if ($limit === false || $limit < 1 || $limit > 100 || $offset === false || $offset < 0 || $offset > 1000000) {
        ob_end_clean();
        json_response(false, null, 'Pagination must use limit 1-100 and offset 0-1000000', 422);
    }

    $auditLogger = $GLOBALS['auditLogger'];
    $logs = $auditLogger->getAllAuditLogs($filters, $limit, $offset);
    $totalRecords = $auditLogger->countAuditLogs($filters);
    $statistics = $auditLogger->getAuditStatistics($filters);

    ob_end_clean();
    json_response(true, [
        'logs' => $logs,
        'total_records' => $totalRecords,
        'statistics' => $statistics,
        'limit' => $limit,
        'offset' => $offset,
        'user_role' => $role,
    ]);
} catch (Throwable $e) {
    error_log('Audit log retrieval failed.');
    ob_end_clean();
    json_response(false, null, 'Failed to retrieve audit logs', 500);
}
