-- Vuka Phase 1 migration 001 (forward-only security migration)
-- Apply through: php scripts/migrate.php
-- The migration widens verification_code to fit password_hash() output and
-- disables the original local seed account without deleting its history.
ALTER TABLE users MODIFY verification_code VARCHAR(255) NULL;

UPDATE admin_users
SET status = 'inactive'
WHERE email = 'admin@vuka.local'
  AND role_id = (SELECT id FROM roles WHERE role_name = 'super_admin' LIMIT 1)
  AND status = 'active';
