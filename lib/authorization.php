<?php
/** Centralized, server-side authorization decisions for Phase 2. */
final class Authorization
{
    public static function requirePermission(array $session, string $permission): void
    {
        $role = (string)($session['role_name'] ?? '');
        $permissions = [
            'admin.accounts.manage' => ['super_admin'],
            'admin.accounts.activate' => ['super_admin'],
            'admin.accounts.deactivate' => ['super_admin'],
            'applications.view' => ['super_admin', 'hr_coordinator', 'department_supervisor', 'student'],
            'applications.review' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'applications.decide' => ['super_admin', 'hr_coordinator'],
            'applications.update_status' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'applications.delete' => ['super_admin', 'hr_coordinator'],
            'applications.view_department' => ['department_supervisor'],
            'applications.view_all' => ['super_admin', 'hr_coordinator'],
            'evaluations.create' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'evaluations.view' => ['super_admin', 'hr_coordinator', 'department_supervisor', 'student'],
            'evaluations.update' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'placements.manage' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'interviews.manage' => ['super_admin', 'hr_coordinator', 'department_supervisor'],
            'reports.view_department' => ['department_supervisor'],
            'reports.view_all' => ['super_admin', 'hr_coordinator'],
            'audit.view_department' => ['department_supervisor'],
            'audit.view_all' => ['super_admin', 'hr_coordinator'],
            'profile.view_own' => ['super_admin', 'hr_coordinator', 'department_supervisor', 'student'],
            'profile.update_own' => ['super_admin', 'hr_coordinator', 'department_supervisor', 'student'],
            'analytics.system' => ['super_admin', 'hr_coordinator'],
        ];
        if (!in_array($role, $permissions[$permission] ?? [], true)) {
            json_response(false, null, 'Forbidden: insufficient permissions', 403);
        }
    }

    public static function scopedDepartment(array $session): ?string
    {
        $role = (string)($session['role_name'] ?? '');
        if (in_array($role, ['super_admin', 'hr_coordinator'], true)) return 'ALL';
        if ($role !== 'department_supervisor') return null;
        $department = trim((string)($session['department_name'] ?? $session['department'] ?? ''));
        return $department === '' ? null : $department;
    }

    public static function requireDepartmentScope(array $session): string
    {
        $scope = self::scopedDepartment($session);
        if ($scope === null) json_response(false, null, 'Department scope is unavailable', 403);
        return $scope;
    }

    public static function requireApplicantOwnership(array $session, int $userId): void
    {
        if (($session['user_type'] ?? '') !== 'student' || (int)($session['user_id'] ?? 0) !== $userId) {
            json_response(false, null, 'Forbidden', 403);
        }
    }
}
