<?php
/** Shared application authorization, transition validation, and review persistence. */

final class ApplicationWorkflowException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

final class ApplicationWorkflow
{
    private PDO $pdo;

    private const TRANSITIONS = [
        'applied' => ['pending', 'accepted', 'rejected', 'withdrawn'],
        'pending' => ['accepted', 'rejected', 'withdrawn'],
        'accepted' => ['deployed'],
        'deployed' => ['ongoing'],
        'ongoing' => ['completed'],
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function normalizeStatus(string $status): string
    {
        return [
            'pending_review' => 'pending',
            'approved' => 'accepted',
        ][$status] ?? $status;
    }

    /**
     * Retrieve every requested record in the authenticated admin's SQL scope.
     * Missing and inaccessible IDs intentionally share a 404 response.
     */
    public function lockScopedSubmissions(array $session, array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids || count($ids) > 100 || min($ids) <= 0) {
            throw new ApplicationWorkflowException('Provide between 1 and 100 valid submission IDs', 422);
        }
        $role = (string)($session['role_name'] ?? '');
        if (!in_array($role, ['super_admin', 'hr_coordinator', 'department_supervisor'], true)) {
            throw new ApplicationWorkflowException('Not authorized to review applications', 403);
        }

        $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT id, user_id, full_name, email, department_applied, status,
                       intern_pf_number, application_type, assigned_role, assigned_station
                FROM submissions WHERE id IN ({$placeholders})";
        $params = $ids;
        if ($scope !== 'ALL') {
            if (!is_string($scope) || $scope === '') {
                throw new ApplicationWorkflowException('Administrator department scope is unavailable', 403);
            }
            $sql .= ' AND department_applied = ?';
            $params[] = $scope;
        }
        $sql .= ' ORDER BY id';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[(int)$row['id']] = $row;
        if (count($rows) !== count($ids)) {
            throw new ApplicationWorkflowException('One or more submissions were not found or are outside your department', 404);
        }
        return $rows;
    }

    public function lockApplicantSubmission(array $session, int $id): array
    {
        if (($session['user_type'] ?? '') !== 'student' || (int)($session['user_id'] ?? 0) <= 0) {
            throw new ApplicationWorkflowException('Applicant authentication is required', 403);
        }
        $sql = 'SELECT id, user_id, full_name, email, department_applied, status,
                       intern_pf_number, application_type, assigned_role, assigned_station
                FROM submissions WHERE id = ? AND user_id = ?';
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') $sql .= ' FOR UPDATE';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$id, (int)$session['user_id']]);
        $submission = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$submission) throw new ApplicationWorkflowException('Application not found', 404);
        return $submission;
    }

    public function withdrawForApplicant(array $session, array $submission): void
    {
        if (!$this->pdo->inTransaction()) throw new LogicException('Workflow changes require an active transaction');
        $this->validateTransition('student', (string)$submission['status'], 'withdrawn');
        $stmt = $this->pdo->prepare("UPDATE submissions SET status = 'withdrawn', last_updated_at = NOW() WHERE id = ? AND user_id = ?");
        $stmt->execute([(int)$submission['id'], (int)$session['user_id']]);
        if ($stmt->rowCount() !== 1) throw new ApplicationWorkflowException('Application changed; reload and retry', 409);
        $history = $this->pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)');
        $history->execute([(int)$submission['id'], 'SELF', '', 'withdrawn', 'Withdrawn by applicant']);
    }

    public function validateTransition(string $role, string $current, string $next): void
    {
        if (($next === 'withdrawn') !== ($role === 'student')) {
            throw new ApplicationWorkflowException('Only an applicant may withdraw an application', 403);
        }
        if (!in_array($next, self::TRANSITIONS[$current] ?? [], true)) {
            throw new ApplicationWorkflowException('This status transition is not permitted', 409);
        }
        if ($role === 'department_supervisor'
            && !in_array($current . '>' . $next, ['accepted>deployed', 'deployed>ongoing', 'ongoing>completed'], true)) {
            throw new ApplicationWorkflowException('This role cannot perform the requested status transition', 403);
        }
        if ($role === 'student' && !in_array($current . '>' . $next, ['applied>withdrawn', 'pending>withdrawn'], true)) {
            throw new ApplicationWorkflowException('Applicants can only withdraw an application awaiting review', 403);
        }
        if (!in_array($role, ['student', 'department_supervisor', 'hr_coordinator', 'super_admin'], true)) {
            throw new ApplicationWorkflowException('This role cannot perform the requested status transition', 403);
        }
    }

    public function validateReviewFields(string $status, string $reason, string $notes, ?string $assignedRole, ?string $assignedStation): void
    {
        if ($status !== '' && !in_array($status, ['pending', 'accepted', 'rejected', 'deployed', 'ongoing', 'completed'], true)) {
            throw new ApplicationWorkflowException('Invalid application status', 422);
        }
        if (mb_strlen($reason) > 1000 || mb_strlen($notes) > 4000
            || ($assignedRole !== null && mb_strlen($assignedRole) > 150)
            || ($assignedStation !== null && mb_strlen($assignedStation) > 150)) {
            throw new ApplicationWorkflowException('One or more review fields exceed the allowed length', 422);
        }
        if ($status === 'rejected' && trim($reason) === '') {
            throw new ApplicationWorkflowException('Rejection reason is required', 422);
        }
        if ($status === 'deployed' && (trim((string)$assignedRole) === '' || trim((string)$assignedStation) === '')) {
            throw new ApplicationWorkflowException('Assigned role and workstation are required for deployment', 422);
        }
    }

    /** Persist state and its history row in the caller's transaction. */
    public function applyTransition(array $session, array $submission, string $status, string $reason = '', string $notes = '', ?string $assignedRole = null, ?string $assignedStation = null): int
    {
        if (!$this->pdo->inTransaction()) throw new LogicException('Workflow changes require an active transaction');
        $role = (string)($session['role_name'] ?? '');
        $this->validateTransition($role, (string)$submission['status'], $status);
        $this->validateReviewFields($status, $reason, $notes, $assignedRole, $assignedStation);

        $internPf = $submission['intern_pf_number'] ?? null;
        if ($status === 'accepted' && empty($internPf) && ($submission['application_type'] ?? '') === 'internship') {
            $internPf = date('ymdHis') . sprintf('%02d', random_int(0, 99));
        }
        $sql = 'UPDATE submissions SET status = ?, rejection_reason = ?, intern_pf_number = ?,
                assigned_role = COALESCE(?, assigned_role), assigned_station = COALESCE(?, assigned_station),
                last_updated_by = ?, last_updated_at = NOW() WHERE id = ?';
        $scope = $GLOBALS['sessionManager']->getScopedDepartment($session);
        $params = [$status, $status === 'rejected' ? $reason : null, $internPf, $assignedRole, $assignedStation,
            (string)($session['pf_number'] ?? ''), (int)$submission['id']];
        if ($scope !== 'ALL') {
            $sql .= ' AND department_applied = ?';
            $params[] = $scope;
        }
        $update = $this->pdo->prepare($sql);
        $update->execute($params);
        if ($update->rowCount() !== 1) throw new ApplicationWorkflowException('Submission changed during review; reload and retry', 409);

        $historyNotes = trim($notes);
        if ($historyNotes === '') {
            $historyNotes = $status === 'rejected' ? 'Rejected: ' . $reason : 'Status changed to: ' . $status;
        }
        $history = $this->pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)');
        $history->execute([(int)$submission['id'], (string)($session['pf_number'] ?? ''),
            (string)($session['department'] ?? ''), $status, $historyNotes]);
        return (int)$this->pdo->lastInsertId();
    }

    public function recordReviewNote(array $session, array $submission, string $notes): void
    {
        if (!$this->pdo->inTransaction()) throw new LogicException('Workflow changes require an active transaction');
        if (trim($notes) === '' || mb_strlen($notes) > 4000) {
            throw new ApplicationWorkflowException('Review notes must contain 1 to 4000 characters', 422);
        }
        $history = $this->pdo->prepare('INSERT INTO review_history (submission_id, reviewer_pf, reviewer_department, status, notes) VALUES (?, ?, ?, ?, ?)');
        $history->execute([(int)$submission['id'], (string)($session['pf_number'] ?? ''),
            (string)($session['department'] ?? ''), 'note', trim($notes)]);
    }
}
