<?php
/**
 * In-app notifications helper — Vuka Portal (Feature #5)
 *
 * Call createNotification(...) wherever a relevant event occurs.
 * Recipients are addressed by their table id + role type:
 *   - students: users.id      with role_type 'student'
 *   - admins:   admin_users.id with role_type 'admin'
 */

/**
 * Insert a notification. Never throws — logs and continues on failure so it
 * can't break the primary action (status update, etc.).
 */
function createNotification(PDO $pdo, int $recipientId, string $roleType,
                            string $title, string $body, ?string $link = null): void {
    try {
        if ($recipientId <= 0) return;
        $roleType = $roleType === 'admin' ? 'admin' : 'student';
        $pdo->prepare("INSERT INTO notifications (recipient_id, role_type, title, body, link)
                       VALUES (?, ?, ?, ?, ?)")
            ->execute([$recipientId, $roleType, mb_substr($title, 0, 120), mb_substr($body, 0, 255), $link]);
    } catch (Exception $e) {
        error_log('createNotification error: ' . $e->getMessage());
    }
}

/**
 * Notify every admin in a department (by department name string), plus all
 * super_admins / hr_coordinators (who oversee all departments). Used for
 * "new application" / "vacancy approved" style alerts.
 */
function notifyDepartmentAdmins(PDO $pdo, string $departmentName,
                                string $title, string $body, ?string $link = null): void {
    try {
        $stmt = $pdo->prepare("
            SELECT a.id
            FROM admin_users a
            LEFT JOIN roles r ON a.role_id = r.id
            WHERE a.status = 'active'
              AND (r.role_name IN ('super_admin','hr_coordinator') OR a.department = ?)
        ");
        $stmt->execute([$departmentName]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            createNotification($pdo, (int)$adminId, 'admin', $title, $body, $link);
        }
    } catch (Exception $e) {
        error_log('notifyDepartmentAdmins error: ' . $e->getMessage());
    }
}
