# Exact Change Report — Pure Laravel Batch 2

Base used: the latest uploaded `leave-system-laravel.zip`, which was still a fresh Laravel skeleton.

This package therefore includes **Batch 1 + Batch 2 together** on top of that fresh base.

## What changed

### Replaced / updated
- `bootstrap/app.php`
- `routes/web.php`
- `database/migrations/0001_01_01_000000_create_users_table.php`

### Added controllers
- `app/Http/Controllers/AuthController.php`
- `app/Http/Controllers/DashboardController.php`
- `app/Http/Controllers/LeaveApplicationController.php`
- `app/Http/Controllers/LeaveRequestController.php`

### Added middleware
- `app/Http/Middleware/RoleMiddleware.php`

### Added / replaced models
- `app/Models/User.php`
- `app/Models/Employee.php`
- `app/Models/Department.php`
- `app/Models/DepartmentHeadAssignment.php`
- `app/Models/LeaveType.php`
- `app/Models/LeaveRequest.php`
- `app/Models/LeaveAttachment.php`
- `app/Models/BudgetHistory.php`

### Added services
- `app/Services/LeavePolicyService.php`
- `app/Services/LeaveCalculatorService.php`
- `app/Services/LeaveWorkflowService.php`

### Added views
- `resources/views/layouts/app.blade.php`
- `resources/views/auth/login.blade.php`
- `resources/views/dashboard/index.blade.php`
- `resources/views/leaves/apply.blade.php`
- `resources/views/leaves/requests.blade.php`

### Added compatibility migration
- `database/migrations/2026_04_16_000100_legacy_schema_compatibility.php`

### Added legacy SQL imports for setup
- `database/legacy_import/leave_system.sql`
- `database/legacy_import/leave_attachments_migration.sql`

### Copied frontend assets from old PHP capstone for visual parity
- `public/assets/**`
- `public/pictures/**`
- `public/uploads/**`

## What changed from what to what
- Fresh Laravel welcome route was replaced with real Laravel auth + dashboard + leave routes.
- Fresh Laravel default `User` model was replaced with a model that matches the old `users` table (`email`, `password`, `role`, `is_active`, `activation_token`, `created_at`).
- The app no longer calls old PHP pages. The login, dashboard, leave apply flow, leave-day calculator, and approval queue are now native Laravel code.
- A compatibility migration was added so the old SQL dump can be imported first, then Laravel adds the newer columns/tables your current PHP code expects.

## Native Laravel features now working in this batch
- login/logout using Laravel auth against the existing `users` table
- dashboard route and role-aware dashboard summary
- employee leave application form
- leave day calculation using holidays + weekends exclusion
- leave type rule presets from the old PHP repository
- supporting document requirements
- force leave “force balance only” option
- leave request queue for department head / personnel / admin / manager roles
- department head forward approval
- personnel final approval
- reject / return / mark printed actions
- attachment upload storage under `public/uploads/leave_attachments/YYYY/MM`

## What was not changed yet
- reports/export pages
- employee profile page
- manage employees/departments/holidays/accruals pages
- printed leave form page
- calendar page
- signatories settings page
- statistics page

## Validation performed
- `php -l` syntax checks on all changed PHP files
- `php artisan route:list` succeeded and registered the new native Laravel routes
