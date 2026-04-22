# EXACT CHANGE REPORT — ATTACHMENT TEMP-FILE FIX + SCHEMA-ALIGNED DEDUCTION LOGS

## Files changed
1. `app/Services/LeaveWorkflowService.php`
2. `app/Models/LeaveBalanceLog.php` (new)
3. `app/Http/Controllers/LeaveApplicationController.php`

## What changed

### 1) `app/Services/LeaveWorkflowService.php`
Changed the attachment storage flow so file metadata is captured **before** the uploaded temp file is moved.

#### From
- called `$file->move(...)`
- then later called `$file->getSize()` and used other file metadata after the move

#### To
- validates extension and MIME against the uploaded temp file first
- captures:
  - sanitized original name
  - size
  - MIME type
- ensures the destination directory exists
- moves the file only after metadata is already captured
- saves the attachment row using the captured metadata

#### Why
Your error:
- `SplFileInfo::getSize(): stat failed for C:\xampp\tmp\phpXXXX.tmp`

happens because the temp file no longer exists at the old location after `move(...)`, but the code was still trying to read its size afterward.

#### Additional schema-aligned change in same file
Added `leave_balance_logs` writing during personnel final approval so the Laravel workflow now records balance deductions in the table your uploaded schema already has.

This now writes reasons aligned with the old system pattern:
- `deduction`
- `deduction_force_leave_only`
- `deduction_annual_force_leave`
- `deduction_force_leave`

### 2) `app/Models/LeaveBalanceLog.php`
Added a real Eloquent model for the existing `leave_balance_logs` table from your uploaded schema.

#### Why
Your uploaded DB schema includes `leave_balance_logs`, but the Laravel project did not yet have a model for it.

### 3) `app/Http/Controllers/LeaveApplicationController.php`
Changed the leave application catch block.

#### From
- caught only `RuntimeException`

#### To
- catches `Throwable`

#### Why
This prevents raw upload-related PHP exceptions from escaping the form flow and lets the user get the error back on the page as a normal flash message.

## What was checked against the uploaded DB schema
Checked these schema parts specifically:
- `leave_attachments`
- `leave_requests`
- `employees`
- `budget_history`
- `leave_balance_logs`
- `leave_request_forms`

## What was not changed
- no routes changed
- no CSS changed
- no dashboard changed
- no reports/calendar/profile/print changes
- no DB schema changed
- no migration files changed

## Verification summary
- `php -l app/Services/LeaveWorkflowService.php` ✅
- `php -l app/Models/LeaveBalanceLog.php` ✅
- `php -l app/Http/Controllers/LeaveApplicationController.php` ✅
- `php artisan route:list` ✅
