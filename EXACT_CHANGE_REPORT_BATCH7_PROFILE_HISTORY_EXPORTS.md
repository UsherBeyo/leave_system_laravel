# EXACT CHANGE REPORT - BATCH 7 PROFILE HISTORY EXPORTS

## Scope of this batch
This batch adds the remaining high-impact reference features around **Employee Profile** and **report/profile export parity**.

## Files changed
- `app/Support/LegacyExcelExport.php` **new**
- `app/Http/Controllers/EmployeeProfileController.php`
- `app/Http/Controllers/ReportsController.php`
- `routes/web.php`
- `resources/views/profile/show.blade.php`
- `resources/views/reports/index.blade.php`

## What changed

### 1. Employee Profile historical tools
**From:** profile page was read-only aside from export links.

**To:** profile page now includes reference-style admin actions for:
- Add Leave History Entry
- Record Undertime

These are available for `admin`, `hr`, and `personnel` only.

### 2. Historical leave entry flow
Added a native Laravel POST flow to create history-only entries directly from the profile page.

Included support for:
- regular historical leave entries
- historical accrual entry (`Vacational Accrual Earned`)
- historical undertime entry (history-only)

### 3. Current undertime recording
Added a native Laravel POST flow to record undertime that:
- deducts from the employee's current vacational balance
- writes a matching `budget_history` row
- writes a matching `leave_balance_logs` row

### 4. XLS export parity
Added Excel-compatible export responses for:
- employee leave history
- employee leave card
- reports summary
- leave balance report
- leave usage report
- report leave card view

Implementation uses an HTML-based Excel export response for compatibility without requiring extra packages.

### 5. Old PHP-style employee profile URL redirect
Added:
- `/views/employee_profile.php?id=...`

Redirects to:
- `/employee-profile?employee=...`

## What was not changed
- leave request workflow logic
- apply leave logic
- print form logic
- dashboard logic
- calendar/holidays/settings pages
- DB schema

## What was checked
- PHP syntax check passed for all changed PHP files
- `php artisan route:list --path=employee-profile` passed
- `php artisan route:list --path=reports` passed
- `php artisan route:list --path=views/employee_profile.php` passed
