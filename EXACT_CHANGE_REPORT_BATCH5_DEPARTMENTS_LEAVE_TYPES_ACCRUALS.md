# EXACT CHANGE REPORT — Batch 5: Departments + Leave Types + Accruals

## Base used
- Base repository: the user's latest working **pure Laravel checkpoint** before this batch.
- Reference reviewed: `capstone.zip` for feature behavior of:
  - `views/manage_departments.php`
  - `controllers/DepartmentController.php`
  - `views/manage_leave_types.php`
  - `controllers/LeaveTypeController.php`
  - `views/manage_accruals.php`

## Files changed

### New controllers
- `app/Http/Controllers/DepartmentManagementController.php`
- `app/Http/Controllers/LeaveTypeManagementController.php`
- `app/Http/Controllers/AccrualManagementController.php`

### New models
- `app/Models/Accrual.php`
- `app/Models/AccrualHistory.php`

### Updated models
- `app/Models/Department.php`
- `app/Models/LeaveType.php`

### Updated routes
- `routes/web.php`

### Updated shared navigation
- `resources/views/partials/sidebar.blade.php`

### New Blade views
- `resources/views/departments/index.blade.php`
- `resources/views/leave-types/index.blade.php`
- `resources/views/leave-types/partials/form.blade.php`
- `resources/views/accruals/index.blade.php`

## What changed from what to what

### 1. Manage Departments
**Before**
- `/manage-departments` was a placeholder page.

**After**
- `/manage-departments` is now a real Laravel page.
- Added:
  - searchable department list
  - pagination
  - create department modal
  - edit department modal
  - delete department action
  - delete guard when department is still in use by employees or department-head assignments
- When department name is updated, matching employee department text is also synchronized.

### 2. Manage Leave Types
**Before**
- `/manage-leave-types` was a placeholder page.

**After**
- `/manage-leave-types` is now a real Laravel page.
- Added:
  - searchable list
  - pagination
  - create modal
  - edit modal
  - delete action
- Added configuration coverage for both reference-page features and current DB-backed rule fields such as:
  - deduct balance
  - requires approval
  - auto approve
  - balance bucket
  - deduct behavior
  - min notice / advance
  - max days / max per year
  - document requirement flags
  - rules text / law text / special rules text

### 3. Manage Accruals
**Before**
- `/manage-accruals` was a placeholder page.

**After**
- `/manage-accruals` is now a real Laravel page.
- Added:
  - manual accrual form for a selected employee
  - bulk accrual modal for all employees
  - three-step confirmation flow for bulk accrual
  - accrual history search and pagination
  - updates both annual and sick balances
  - does not touch force balance
  - writes history rows into `accrual_history`
  - also writes fallback rows into `accruals`

### 4. Routes
**Before**
- these pages had placeholder routes.

**After**
- real Laravel routes were added for:
  - `manage-departments`
  - `manage-departments.store`
  - `manage-departments.update`
  - `manage-departments.destroy`
  - `manage-leave-types`
  - `manage-leave-types.store`
  - `manage-leave-types.update`
  - `manage-leave-types.destroy`
  - `manage-accruals`
  - `manage-accruals.manual`
  - `manage-accruals.bulk`
- old PHP-style redirects were added for:
  - `/views/manage_departments.php`
  - `/views/manage_leave_types.php`
  - `/views/manage_accruals.php`

### 5. Sidebar
**Before**
- sidebar still pointed to placeholders.

**After**
- sidebar now points to real pages.
- `Leave Types` is visible to `admin` and `hr` to match reference access intent.
- `Accruals` remains admin-only.
- `Departments` remains admin-only.

## Connected files checked but left untouched
- `app/Http/Controllers/CalendarController.php`
- `app/Http/Controllers/HolidayController.php`
- `app/Http/Controllers/ReportsController.php`
- `app/Http/Controllers/EmployeeProfileController.php`
- `app/Http/Controllers/PrintLeaveFormController.php`
- `app/Http/Controllers/LeaveApplicationController.php`
- `app/Http/Controllers/LeaveRequestController.php`
- `resources/views/leaves/requests.blade.php`
- `resources/views/print/leave-form.blade.php`
- `resources/views/reports/index.blade.php`
- `resources/views/calendar/index.blade.php`
- `resources/views/settings/signatories.blade.php`

## What was not changed
- no DB schema changes
- no leave request workflow changes
- no deduction workflow changes
- no print-form logic changes
- no calendar logic changes
- no reports logic changes
- no employee profile logic changes
- no dashboard logic changes

## Verification performed before packaging
- `php -l` passed for all new/changed PHP class files
- `php artisan route:list` passed
- confirmed route registration for:
  - manage-departments
  - manage-leave-types
  - manage-accruals

## Environment limitation encountered
- `php artisan view:cache` could not complete in the container because container PHP lacks `DOMDocument`.
- This was an environment limitation in the container, not a syntax failure in the project.
