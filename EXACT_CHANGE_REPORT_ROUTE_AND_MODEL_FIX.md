# Exact Change Report — Route and Model Fix

## Files changed

1. `routes/web.php`
- Changed from the default Laravel welcome-only route to real native Laravel routes for:
  - `/login`
  - `/logout`
  - `/dashboard`
  - `/leave/apply`
  - `/leave/requests`
  - `/api/calc-days`
- Added pure Laravel redirects from old PHP-style URLs to native Laravel routes:
  - `/views/login.php` -> `/login`
  - `/views/dashboard.php` -> `/dashboard`
  - `/views/apply_leave.php` -> `/leave/apply`
  - `/views/leave_requests.php` -> `/leave/requests`
  - `/views/calendar.php` -> `/calendar`
  - `/views/reports.php` -> `/reports`
- Added placeholder native Laravel routes so these no longer 404:
  - `/calendar`
  - `/reports`
  - `/employee-profile`
  - `/change-password`
  - `/manage-employees`
  - `/manage-departments`
  - `/holidays`
  - `/manage-accruals`
  - `/manage-leave-types`
  - `/signatories-settings`
  - `/register`

2. `app/Models/User.php`
- Replaced the default Laravel user model fields with legacy-compatible fields.
- Added `employee()` relation required by dashboard, login landing, and layouts.
- Set `UPDATED_AT = null` to match the current users table structure.
- Added legacy casts for `is_active` and `created_at`.

3. `app/Models/BudgetHistory.php`
- Added compatibility for both `leave_id` and `leave_request_id` so approval logging does not break on the uploaded SQL schema.

4. `app/Services/LeaveWorkflowService.php`
- Changed budget-history logging so it writes to `leave_request_id` when that column exists, and only falls back to `leave_id` when needed.

5. `resources/views/placeholders/page.blade.php`
- Simplified the placeholder page so it renders cleanly in the current layout instead of depending on missing UI classes.

6. `resources/views/partials/sidebar.blade.php`
- Fixed route name references from old names (`leave-requests`, `apply-leave`) to the actual native Laravel route names (`leave.requests`, `leave.apply`).

7. `resources/views/layouts/app.blade.php`
- Expanded Apply Leave visibility to `employee`, `manager`, and `department_head` to match the current flow implied by the existing sidebar partial.

## What was checked
- `routes/web.php`
- `app/Models/User.php`
- `app/Models/BudgetHistory.php`
- `app/Services/LeaveWorkflowService.php`
- `resources/views/layouts/app.blade.php`
- `resources/views/partials/sidebar.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/leaves/apply.blade.php`
- `resources/views/leaves/requests.blade.php`
- uploaded SQL schema for `users`, `employees`, `leave_requests`, `leave_types`, `departments`, `department_head_assignments`, `leave_attachments`, `budget_history`

## What was not changed
- No business rule logic for leave policies was altered.
- No controller approval branching was changed.
- No CSS redesign was done.
- No database values were changed.
- No legacy PHP files were re-enabled.
