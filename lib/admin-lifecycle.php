<?php
/** Authoritative administrator status transition service. */
final class AdminLifecycle
{
    public static function change(PDO $pdo, array $session, int $adminId, string $newStatus, string $reason = ''): array
    {
        if (($session['role_name'] ?? '') !== 'super_admin') throw new RuntimeException('Forbidden', 403);
        if ($adminId <= 0 || $adminId === (int)($session['admin_id'] ?? 0)) throw new RuntimeException('Cannot modify your own account', 403);
        if (!in_array($newStatus, ['active', 'inactive'], true)) throw new RuntimeException('Invalid account status transition', 422);
        $started = false;
        try {
            if (!$pdo->inTransaction()) { $pdo->beginTransaction(); $started = true; }
            $lock = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
            $stmt = $pdo->prepare("SELECT a.id, a.pf_number, a.full_name, a.status, r.role_name, d.name AS department_name
                FROM admin_users a JOIN roles r ON r.id = a.role_id LEFT JOIN departments d ON d.id = a.department_id
                WHERE a.id = ?{$lock}");
            $stmt->execute([$adminId]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$target) throw new RuntimeException('Admin account not found', 404);
            if (!in_array($target['role_name'], ['hr_coordinator', 'department_supervisor'], true)) throw new RuntimeException('Only eligible administrator accounts can be changed', 403);
            $valid = ($target['status'] === 'pending_activation' && $newStatus === 'active') || ($target['status'] === 'active' && $newStatus === 'inactive') || ($target['status'] === 'inactive' && $newStatus === 'active');
            if (!$valid) throw new RuntimeException('Invalid account status transition', 409);
            $pdo->prepare('UPDATE admin_users SET status = ? WHERE id = ?')->execute([$newStatus, $adminId]);
            if ($newStatus === 'inactive') $pdo->prepare('UPDATE sessions SET is_valid = 0 WHERE admin_id = ?')->execute([$adminId]);
            $historyStatus = $newStatus === 'active' ? 'activated' : 'deactivated';
            $pdo->prepare('UPDATE admin_creation_history SET activation_status = ? WHERE created_admin_id = ?')->execute([$historyStatus, $adminId]);
            $audit = $GLOBALS['auditLogger'];
            $action = $newStatus === 'active' ? 'activate_admin' : 'deactivate_admin';
            if (!$audit->log($session, $action, 'admin_user', $adminId, ['status' => $target['status']], ['status' => $newStatus], $adminId, $reason === '' ? null : $reason)) throw new RuntimeException('Security audit could not be recorded', 500);
            if ($started) $pdo->commit();
            $target['status'] = $newStatus;
            return $target;
        } catch (Throwable $e) {
            if ($started && $pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
    }
}
