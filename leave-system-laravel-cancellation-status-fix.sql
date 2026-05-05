-- Batch 2 cancellation approval status fix
-- Required because the current leave_requests.status column only allows:
-- pending, approved, rejected.
-- The Leave Cancellation approval flow sets approved cancellations to status = cancelled.

ALTER TABLE `leave_requests`
MODIFY `status` ENUM('pending','approved','rejected','cancelled') DEFAULT 'pending';

SET @next_batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

INSERT INTO `migrations` (`migration`, `batch`)
SELECT '2026_05_05_000500_allow_cancelled_leave_request_status', @next_batch
WHERE NOT EXISTS (
    SELECT 1 FROM `migrations`
    WHERE `migration` = '2026_05_05_000500_allow_cancelled_leave_request_status'
);
