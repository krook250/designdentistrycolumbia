-- 002: staff management.
-- must_change_password: set on invite/reset; the person picks their own password at next sign-in.
-- password_changed_at: stamped on every password change; sessions started before it are signed out.

ALTER TABLE users ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password_hash;

ALTER TABLE users ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER must_change_password;

ALTER TABLE users ADD COLUMN IF NOT EXISTS created_by INT UNSIGNED NULL AFTER created_at;
