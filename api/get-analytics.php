<?php
/**
 * Get Advanced Analytics API (Feature #10)
 * GET /api/get-analytics.php
 *
 * Returns advanced analytics datasets for admins, scoped by department.
 * Super Admin / HR Coordinator: system-wide ('ALL').
 * Department Supervisor: constrained to their own department name string
 * (matched against submissions.department_applied).
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ob_end_clean();
    json_response(false, null, 'Method not allowed');
}

try {
    $session = requireAnyAdmin();

    $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);

    // Build the optional department-scope condition once, reuse everywhere.
    // When scope !== 'ALL' we constrain by department name string via a bound param.
    $scopeCond = '';
    $scopeParams = [];
    if ($scope !== 'ALL') {
        $scopeCond = 's.department_applied = ?';
        $scopeParams[] = $scope;
    }

    // 1. monthly — applications per month for the last 12 months.
    // Combine the date filter with the optional scope condition.
    $conditions = ["s.submitted_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)"];
    $params = [];
    if ($scopeCond !== '') {
        $conditions[] = $scopeCond;
        $params = array_merge($params, $scopeParams);
    }
    $where = 'WHERE ' . implode(' AND ', $conditions);
    $sql = "SELECT DATE_FORMAT(s.submitted_at, '%Y-%m') AS month, COUNT(*) AS count
            FROM submissions s
            $where
            GROUP BY month
            ORDER BY month";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $monthly = $stmt->fetchAll();

    // 2. by_status — submission counts grouped by status.
    $conditions = [];
    $params = [];
    if ($scopeCond !== '') {
        $conditions[] = $scopeCond;
        $params = array_merge($params, $scopeParams);
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $sql = "SELECT status, COUNT(*) AS count
            FROM submissions s
            $where
            GROUP BY status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $by_status = $stmt->fetchAll();

    // 3. acceptance — per department totals and accepted counts.
    $conditions = [];
    $params = [];
    if ($scopeCond !== '') {
        $conditions[] = $scopeCond;
        $params = array_merge($params, $scopeParams);
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $sql = "SELECT department_applied AS department,
                   COUNT(*) AS total,
                   SUM(status IN ('accepted','deployed','ongoing','completed')) AS accepted
            FROM submissions s
            $where
            GROUP BY department_applied";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $acceptance = $stmt->fetchAll();

    // 4. avg_days — average days from submission to first accept/reject decision.
    $conditions = [];
    $params = [];
    if ($scopeCond !== '') {
        $conditions[] = $scopeCond;
        $params = array_merge($params, $scopeParams);
    }
    $where = $conditions ? ('WHERE ' . implode(' AND ', $conditions)) : '';
    $sql = "SELECT s.department_applied AS department,
                   ROUND(AVG(DATEDIFF(
                       (SELECT MIN(rh.reviewed_at) FROM review_history rh
                        WHERE rh.submission_id = s.id AND rh.status IN ('accepted','rejected')),
                       s.submitted_at))) AS avg_days
            FROM submissions s
            $where
            GROUP BY s.department_applied";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $avg_days = $stmt->fetchAll();

    ob_end_clean();
    json_response(true, [
        'data' => [
            'monthly'    => $monthly,
            'by_status'  => $by_status,
            'acceptance' => $acceptance,
            'avg_days'   => $avg_days,
        ]
    ]);

} catch (Exception $e) {
    error_log("Get analytics error: " . $e->getMessage());
    ob_end_clean();
    json_response(false, null, 'Failed to load analytics');
}

?>
