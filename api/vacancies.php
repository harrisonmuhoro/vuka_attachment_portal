<?php
/** Public vacancy reads and role-authorized administrative vacancy actions. */

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ob_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session-manager.php';
require_once __DIR__ . '/../audit-logger.php';

$method = $_SERVER['REQUEST_METHOD'] ?? '';

try {
    if ($method === 'GET') {
        $presentedSession = get_bearer_token() ?? ($_COOKIE['session_id'] ?? null) ?? ($_SESSION['session_id'] ?? null);
        $session = null;
        if ($presentedSession !== null && $presentedSession !== '') {
            $session = $GLOBALS['sessionManager']->validateSession($presentedSession);
            if (!$session) {
                ob_end_clean();
                json_response(false, null, 'Unauthorized', 401);
            }
        }

        $role = (string)($session['role_name'] ?? '');
        $isAdministrator = in_array($role, ['super_admin', 'hr_coordinator', 'department_supervisor'], true);
        $department = trim((string)($_GET['department'] ?? ''));
        $status = trim((string)($_GET['status'] ?? 'approved'));

        if (!$isAdministrator) {
            // Anonymous users and applicants always receive published, open vacancies only.
            $sql = 'SELECT id, department_name, title, description, skills_required,
                           positions_count, vacancy_type, deadline_at, created_at
                    FROM vacancies WHERE status = ? AND (deadline_at IS NULL OR deadline_at >= NOW())';
            $params = ['approved'];
            if ($department !== '') {
                $sql .= ' AND department_name = ?';
                $params[] = $department;
            }
        } else {
            if (!in_array($status, ['all', 'pending', 'approved', 'rejected', 'closed'], true)) {
                ob_end_clean();
                json_response(false, null, 'Invalid vacancy status filter', 422);
            }
            $sql = 'SELECT v.*, a.full_name AS creator_name FROM vacancies v LEFT JOIN admin_users a ON a.id = v.created_by WHERE 1=1';
            $params = [];
            if ($status !== 'all') {
                $sql .= ' AND v.status = ?';
                $params[] = $status;
            }
            if ($role === 'department_supervisor') {
                $scope = trim((string)($session['department'] ?? ''));
                if ($scope === '') {
                    ob_end_clean();
                    json_response(false, null, 'Department scope is unavailable', 403);
                }
                $sql .= ' AND v.department_name = ?';
                $params[] = $scope;
            } elseif ($department !== '') {
                $sql .= ' AND v.department_name = ?';
                $params[] = $department;
            }
        }
        $sql .= $isAdministrator ? ' ORDER BY v.created_at DESC, v.id DESC' : ' ORDER BY created_at DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $vacancies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ob_end_clean();
        json_response(true, ['vacancies' => $vacancies]);
    }

    if ($method !== 'POST') {
        ob_end_clean();
        json_response(false, null, 'Method not allowed', 405);
    }
    require_json_request();
    $session = requireAnyAdmin();
    $role = (string)$session['role_name'];
    $input = json_decode(file_get_contents('php://input'), true);
    if (!is_array($input)) {
        ob_end_clean();
        json_response(false, null, 'Invalid JSON input', 400);
    }
    $action = trim((string)($input['action'] ?? ''));
    $audit = $GLOBALS['auditLogger'];

    if ($action === 'create') {
        $title = trim((string)($input['title'] ?? ''));
        $description = trim((string)($input['description'] ?? ''));
        $skills = trim((string)($input['skills'] ?? $input['skills_required'] ?? ''));
        $positions = filter_var($input['positions_count'] ?? null, FILTER_VALIDATE_INT);
        $type = (string)($input['vacancy_type'] ?? 'attachment');
        $department = trim((string)($session['department'] ?? ''));
        if (in_array($role, ['hr_coordinator', 'super_admin'], true)) {
            $department = trim((string)($input['department'] ?? ''));
        }
        $deadline = trim((string)($input['deadline_at'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255 || $description === '' || mb_strlen($description) > 10000
            || mb_strlen($skills) > 5000 || $positions === false || $positions < 1 || $positions > 1000
            || !in_array($type, ['attachment', 'internship'], true) || $department === '' || mb_strlen($department) > 100) {
            ob_end_clean();
            json_response(false, null, 'Invalid vacancy details', 422);
        }
        $departmentCheck = $pdo->prepare('SELECT 1 FROM departments WHERE name = ? LIMIT 1');
        $departmentCheck->execute([$department]);
        if (!$departmentCheck->fetchColumn()) {
            ob_end_clean();
            json_response(false, null, 'Unknown department', 422);
        }
        $deadlineLocal = $deadline === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $deadline);
        $deadlineSql = $deadline === '' ? false : DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $deadline);
        $deadlineValid = ($deadlineLocal && $deadlineLocal->format('Y-m-d\TH:i') === $deadline)
            || ($deadlineSql && $deadlineSql->format('Y-m-d H:i:s') === $deadline);
        if ($deadline !== '' && !$deadlineValid) {
            ob_end_clean();
            json_response(false, null, 'Invalid vacancy deadline', 422);
        }
        $deadlineValue = $deadline === '' ? null : str_replace('T', ' ', $deadline);
        $pdo->beginTransaction();
        $insert = $pdo->prepare("INSERT INTO vacancies (department_name,title,description,skills_required,positions_count,vacancy_type,deadline_at,status,created_by) VALUES (?,?,?,?,?,?,?,'pending',?)");
        $insert->execute([$department, $title, $description, $skills, $positions, $type, $deadlineValue, (int)$session['admin_id']]);
        $vacancyId = (int)$pdo->lastInsertId();
        if (!$audit->log($session, 'create_vacancy', 'vacancy', $vacancyId, null,
            ['department' => $department, 'type' => $type, 'status' => 'pending'])) {
            throw new RuntimeException('Audit event could not be recorded');
        }
        $pdo->commit();
        ob_end_clean();
        json_response(true, ['message' => 'Vacancy request submitted for approval', 'vacancy_id' => $vacancyId], null, 201);
    }

    $vacancyId = filter_var($input['vacancy_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$vacancyId || $vacancyId <= 0) {
        ob_end_clean();
        json_response(false, null, 'A valid vacancy ID is required', 422);
    }
    $pdo->beginTransaction();
    $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $select = $pdo->prepare('SELECT id, department_name, status FROM vacancies WHERE id = ?' . $lock);
    $select->execute([(int)$vacancyId]);
    $vacancy = $select->fetch(PDO::FETCH_ASSOC);
    if (!$vacancy) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Vacancy not found', 404);
    }
    $scope = trim((string)($session['department'] ?? ''));
    if ($role === 'department_supervisor' && ($scope === '' || $vacancy['department_name'] !== $scope)) {
        $pdo->rollBack();
        ob_end_clean();
        json_response(false, null, 'Vacancy not found', 404);
    }

    if ($action === 'update_status') {
        if (!in_array($role, ['hr_coordinator', 'super_admin'], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Forbidden', 403);
        }
        $newStatus = trim((string)($input['status'] ?? ''));
        $allowed = ['pending' => ['approved', 'rejected'], 'approved' => ['closed']];
        if (!in_array($newStatus, $allowed[$vacancy['status']] ?? [], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'This vacancy status transition is not permitted', 409);
        }
        $pdo->prepare('UPDATE vacancies SET status = ? WHERE id = ?')->execute([$newStatus, (int)$vacancyId]);
        if (!$audit->log($session, 'vacancy_status_changed', 'vacancy', (int)$vacancyId,
            ['status' => $vacancy['status']], ['status' => $newStatus])) {
            throw new RuntimeException('Audit event could not be recorded');
        }
        $pdo->commit();
        ob_end_clean();
        json_response(true, ['message' => 'Vacancy status updated', 'status' => $newStatus]);
    }

    if ($action === 'end') {
        if (!in_array($role, ['department_supervisor', 'hr_coordinator', 'super_admin'], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Forbidden', 403);
        }
        if (!in_array($vacancy['status'], ['pending', 'approved'], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'This vacancy can no longer be closed', 409);
        }
        $pdo->prepare("UPDATE vacancies SET status = 'closed' WHERE id = ?")->execute([(int)$vacancyId]);
        if (!$audit->log($session, 'vacancy_closed', 'vacancy', (int)$vacancyId,
            ['status' => $vacancy['status']], ['status' => 'closed'])) {
            throw new RuntimeException('Audit event could not be recorded');
        }
        $pdo->commit();
        ob_end_clean();
        json_response(true, ['message' => 'Vacancy marked as closed']);
    }

    if ($action === 'delete') {
        if (!in_array($role, ['department_supervisor', 'super_admin'], true)) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Forbidden', 403);
        }
        if ($vacancy['status'] !== 'pending') {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Only pending vacancies may be deleted', 409);
        }
        $refs = $pdo->prepare('SELECT COUNT(*) FROM submissions WHERE vacancy_id = ?');
        $refs->execute([(int)$vacancyId]);
        if ((int)$refs->fetchColumn() > 0) {
            $pdo->rollBack();
            ob_end_clean();
            json_response(false, null, 'Vacancies with applications cannot be deleted; close them instead', 409);
        }
        if (!$audit->log($session, 'vacancy_deleted', 'vacancy', (int)$vacancyId,
            ['department' => $vacancy['department_name'], 'status' => $vacancy['status']])) {
            throw new RuntimeException('Audit event could not be recorded');
        }
        $pdo->prepare('DELETE FROM vacancies WHERE id = ?')->execute([(int)$vacancyId]);
        $pdo->commit();
        ob_end_clean();
        json_response(true, ['message' => 'Vacancy deleted']);
    }

    $pdo->rollBack();
    ob_end_clean();
    json_response(false, null, 'Unsupported vacancy action', 422);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Vacancy request failed.');
    ob_end_clean();
    json_response(false, null, 'Vacancy request could not be completed', 500);
}
