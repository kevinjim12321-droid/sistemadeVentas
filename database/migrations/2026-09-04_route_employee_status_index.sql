-- Migration: route_employee_status index on phppos_route_runs
-- Date: 2026-09-04
-- Purpose: supports Route::create()'s locked "one open route per employee"
--          check and Route::get_open_route_for_employee() (Rutas Fase 1).
-- Table prefix assumed: phppos_ (verify against your $db['default']['dbprefix']
-- in application/config/database.php before running).
--
-- Safe to skip entirely: Route::create() works correctly without this index
-- (InnoDB still locks correctly via SELECT ... FOR UPDATE); it only makes
-- that lookup and lock more efficient as route_runs grows.
--
-- Run manually (phpMyAdmin / mysql client). Idempotent: safe to run more
-- than once.

-- ============================================================
-- 1) VERIFY: check whether the index already exists
-- ============================================================
SELECT COUNT(1) AS index_exists
FROM information_schema.STATISTICS
WHERE table_schema = DATABASE()
  AND table_name   = 'phppos_route_runs'
  AND index_name   = 'route_employee_status';

-- ============================================================
-- 2) UP: create the index only if it is missing
-- ============================================================
SET @idx_exists := (
	SELECT COUNT(1)
	FROM information_schema.STATISTICS
	WHERE table_schema = DATABASE()
	  AND table_name   = 'phppos_route_runs'
	  AND index_name   = 'route_employee_status'
);
SET @ddl := IF(@idx_exists = 0,
	'ALTER TABLE `phppos_route_runs` ADD INDEX `route_employee_status` (`employee_id`, `status`)',
	'SELECT ''route_employee_status already exists, skipping'' AS notice'
);
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================
-- 3) ROLLBACK: drop the index (run only if you want to undo step 2)
-- ============================================================
-- ALTER TABLE `phppos_route_runs` DROP INDEX `route_employee_status`;
