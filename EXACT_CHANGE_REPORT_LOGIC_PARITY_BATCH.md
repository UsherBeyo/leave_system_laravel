# Exact Change Report - Logic Parity Batch

## Base used
The uploaded `leave-system-laravel.zip` was used as the base checkpoint.
No unrelated pages or workflow modules were intentionally changed.

## Files changed
- `app/Support/BalanceLedger.php` **new**
- `app/Services/LeavePolicyService.php`
- `app/Services/LeaveWorkflowService.php`
- `app/Http/Controllers/EmployeeManagementController.php`
- `app/Http/Controllers/AccrualManagementController.php`
- `app/Http/Controllers/EmployeeProfileController.php`
- `app/Http/Controllers/ReportsController.php`
- `routes/web.php`
- `resources/views/leaves/apply.blade.php`
- `resources/views/profile/show.blade.php`
- `resources/views/reports/index.blade.php`

## What changed

### 1) Apply Leave document-rule parity
- Kept `reason` **not globally required**.
- Added backend enforcement that when a leave type requires supporting documents:
  - the user must select at least the required number of supporting-document checkboxes
  - the user must upload at least the required number of attachment files
- Added matching client-side warning text for selected document-type count.

### 2) Leave-type rule alignment from reference
- `Special Emergency (Calamity) Leave`
  - changed to deduct from Vacational Balance
  - kept max 5 / year and required document count
  - updated rule text to match reference wording more closely
- `Monetization of Leave Credits`
  - changed to deduct from Vacational Balance
  - added required document count = 1
  - updated rule text to match reference behavior

### 3) Manual balance adjustment ledger logging
- In Manage Employees update flow, manual changes to annual/sick/force balances now write `budget_history` adjustment rows like the reference.

### 4) Accrual ledger logging
- In Manage Accruals, manual and bulk accrual now also write `budget_history` earning rows for:
  - Vacational
  - Sick
- Existing `accrual_history` and `accruals` writes were kept.

### 5) Employee Profile feature restoration
Restored directly on the profile page:
- Change Password modal + save route
- Update Balances modal + save route
- Record Undertime modal + save route
- Add Leave History Entry modal + save route
- Kept existing profile photo update flow

#### Historical entry support restored
- Historical accrual earned (`leave_type_id = 0`)
- Historical undertime (`leave_type_id = -1`)
- Historical approved leave entry for real leave types
- Historical deduction logging for deducting leave types

### 6) Reports access parity
Reports backend now supports:
- admin
- manager
- hr
- personnel
- employee
- department_head

#### Restored restrictions
- employee can only access own leave card
- department head is scoped to own department
- personnel defaults to own leave card when opening leave-card report without choosing an employee

### 7) Reports export parity improvements
Added closer reference behavior:
- CSV export
- XLS export via `LegacyExcelExport`
- Print / PDF-style report view using existing `reports.print` flow

## What was NOT changed
- leave approval workflow logic
- deduction workflow core logic
- notifications
- print leave form logic
- signatories logic
- calendar backend
- holidays/departments/leave types page CRUD behavior aside from the ledger-related items above
- custom 404 behavior

## Validation performed
- `php -l` passed on all changed PHP files
- `php artisan route:list` passed
- Blade compilation + `php -l` passed for:
  - `resources/views/profile/show.blade.php`
  - `resources/views/reports/index.blade.php`
  - `resources/views/leaves/apply.blade.php`
