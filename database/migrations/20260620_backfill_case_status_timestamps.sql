SET NAMES utf8mb4;

UPDATE emergency_cases
SET accepted_at = COALESCE(dispatched_at, created_at)
WHERE status IN ('accepted', 'closed')
  AND accepted_at IS NULL;

UPDATE emergency_cases
SET closed_at = COALESCE(accepted_at, dispatched_at, created_at)
WHERE status = 'closed'
  AND closed_at IS NULL;
