Batch 12 restore uses the uploaded latest pure Laravel ZIP as the base and restores missing reference-backed management/utilities pages without changing existing leave workflow, print-form, dashboard, profile, request accordion, or notification logic.

Changed files:
- routes/web.php
- resources/views/partials/sidebar.blade.php
- app/Http/Controllers/CalendarController.php
- app/Http/Controllers/HolidayController.php
- app/Http/Controllers/DepartmentManagementController.php
- app/Http/Controllers/LeaveTypeManagementController.php
- app/Http/Controllers/AccrualManagementController.php
- app/Http/Controllers/EmployeeManagementController.php
- app/Http/Controllers/ChangePasswordController.php
- app/Http/Controllers/StatisticsController.php
- app/Models/Holiday.php
- app/Models/Accrual.php
- app/Models/AccrualHistory.php
- app/Models/Department.php
- app/Models/Employee.php
- app/Models/LeaveType.php
- resources/views/calendar/index.blade.php
- resources/views/holidays/index.blade.php
- resources/views/departments/index.blade.php
- resources/views/leave-types/index.blade.php
- resources/views/leave-types/partials/form.blade.php
- resources/views/accruals/index.blade.php
- resources/views/employees/index.blade.php
- resources/views/employees/partials/form.blade.php
- resources/views/settings/change-password.blade.php
- resources/views/statistics/index.blade.php
- resources/views/vendor/pagination/clean.blade.php

What changed from what to what:
- Placeholder-backed routes for calendar, holidays, manage departments, manage leave types, manage accruals, manage employees, change password, and statistics were replaced with real Laravel controllers/routes.
- Old PHP-style URLs for those pages were added as redirects into the real Laravel routes.
- Sidebar now links to the restored Statistics page.
- Department, Employee, and LeaveType models were expanded to support the restored page/controller features.
- Holiday, Accrual, and AccrualHistory models were added to match the reference database tables used by the restored pages.
- Calendar page restored as a real Laravel calendar view/controller.
- Holidays page restored with search, add, inline edit, delete, and pagination.
- Departments page restored with search, create, edit, delete, and in-use delete guard.
- Leave Types page restored with searchable list plus create/edit/delete and rule fields.
- Accruals page restored with manual accrual, bulk accrual, and searchable history.
- Manage Employees page restored with search, create/edit modals, balances, role assignment, profile picture upload, and profile/leave-card shortcuts.
- Change Password page restored as a real settings page.
- Statistics page restored with employee/account/department/role metrics.

What was NOT changed:
- leave application rules
- leave approval workflow logic
- deduction logic
- request accordion UI
- request modal/attachment features
- print form logic
- signatories logic
- notifications logic
- custom 404 page
- shared layout styles except naturally using the already-present layout

What I verified:
- php -l passed on all new/changed controllers/models/routes
- php artisan route:list passed
- Verified registered routes exist for calendar, holidays, manage-departments, manage-leave-types, manage-accruals, manage-employees, change-password, statistics, and the old /views/*.php redirects
