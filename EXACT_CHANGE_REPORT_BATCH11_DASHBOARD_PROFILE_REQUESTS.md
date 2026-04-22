# EXACT CHANGE REPORT — Batch 11: Dashboard parity + Employee Profile self-service + Leave Requests search parity

## Files changed
1. `app/Http/Controllers/DashboardController.php`
2. `resources/views/dashboard/index.blade.php`
3. `app/Http/Controllers/EmployeeProfileController.php`
4. `resources/views/profile/show.blade.php`
5. `app/Http/Controllers/LeaveRequestController.php`
6. `resources/views/leaves/requests.blade.php`
7. `routes/web.php`

## What changed

### 1) `app/Http/Controllers/DashboardController.php`
- Changed from hand-built partial dashboard arrays to using `DashboardDataService` as the source of truth.
- This was required so the Laravel dashboard can render the richer role-specific sections already modeled after the reference backend.

### 2) `resources/views/dashboard/index.blade.php`
- Replaced the simpler dashboard page with a deeper role-aware Blade version.
- Added employee balance doughnut charts.
- Added department-head pending/upcoming tables.
- Added personnel pending/print-queue sections.
- Added manager/HR pending table + department chart.
- Added admin department/role charts + recent users table.
- Kept existing route/button behavior intact.

### 3) `app/Http/Controllers/EmployeeProfileController.php`
- Added `updatePhoto()`.
- Added `updateBalances()`.
- Added `updatePassword()`.
- Added `logBalanceAdjustment()` helper.
- These changes were required to bring profile-page actions closer to the reference flow instead of forcing the user out to other pages.

### 4) `resources/views/profile/show.blade.php`
- Added profile-page action buttons for:
  - Change Photo
  - Change Password
  - Update Balances
- Added profile photo modal.
- Added password modal.
- Added update balances modal.
- Preserved existing history and undertime modals.
- Preserved exports, leave card, and history tables.

### 5) `app/Http/Controllers/LeaveRequestController.php`
- Added current-tab search filter `q`.
- Search now checks employee name, email, department, position, leave type, comments, workflow fields, and dates.
- Passed `search` into the Blade view.

### 6) `resources/views/leaves/requests.blade.php`
- Added a `Search This Section` filter input.
- Added debounce auto-submit for the search field.
- Preserved the existing accordion row and detail-card design.
- Added `Open Full Leave Card` shortcut in Employee Shortcuts.
- Made tab links preserve the current filter query instead of resetting all filters when switching tabs.

### 7) `routes/web.php`
- Added these new native Laravel routes:
  - `employee-profile.photo.update`
  - `employee-profile.password.update`
  - `employee-profile.balances.update`
- No old routes were removed.

## Connected files checked but not changed
- `app/Services/DashboardDataService.php`
- `app/Services/LeaveLedgerService.php`
- `resources/views/reports/index.blade.php`
- `resources/views/print/leave-form.blade.php`
- `resources/views/partials/header.blade.php`
- `resources/views/partials/sidebar.blade.php`

## What was NOT changed
- No leave workflow rules changed.
- No balance deduction logic for approvals changed.
- No print-form logic changed.
- No calendar logic changed.
- No reports export backend changed.
- No notification logic changed.
- No database schema changed.

## Validation performed before packaging
- `php -l` passed on all changed PHP controller files.
- `php -l` passed on changed route file.
- `php artisan route:list` passed.
- Confirmed registration for:
  - `dashboard`
  - `leave.requests`
  - `employee-profile.photo.update`
  - `employee-profile.password.update`
  - `employee-profile.balances.update`

## Known container limitation
- `php artisan view:cache` could not complete in this container because the container PHP is missing `DOMDocument`.
- Route validation and PHP syntax checks passed.
