Batch: Session conflict fix for Laravel bridge

Why the error happened:
- Laravel's web middleware already starts a PHP session.
- Several legacy capstone files still called `session_start();` directly.
- In Laravel debug mode, that warning was promoted to an exception.

Exact files changed:
1. `legacy_app/capstone/index.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

2. `legacy_app/capstone/controllers/AdminController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

3. `legacy_app/capstone/controllers/AuthController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

4. `legacy_app/capstone/controllers/DepartmentController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

5. `legacy_app/capstone/controllers/HolidayController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

6. `legacy_app/capstone/controllers/LeaveController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

7. `legacy_app/capstone/controllers/LeaveTypeController.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

8. `legacy_app/capstone/controllers/logout.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

9. `legacy_app/capstone/views/login.php`
   - from: `session_start();`
   - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

10. `legacy_app/capstone/views/manage_leave_types.php`
    - from guarded block with plain `session_start();`
    - to guarded block with the same structure but safe inside Laravel

11. `legacy_app/capstone/views/print_leave_form.php`
    - from guarded block with plain `session_start();`
    - to guarded block with the same structure but safe inside Laravel

12. `legacy_app/capstone/views/error.php`
    - from guarded block with plain `session_start();`
    - to guarded block with the same structure but safe inside Laravel

13. `legacy_app/capstone/views/layout/header.php`
    - from: `if (!isset($_SESSION)) session_start();`
    - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

14. `legacy_app/capstone/views/partials/sidebar.php`
    - from: `if (!isset($_SESSION)) session_start();`
    - to: `if (session_status() === PHP_SESSION_NONE) session_start();`

What was not changed:
- no leave rules
- no business logic
- no page flow
- no routes
- no database schema
- no styles or UI layout
- no approvals/reports/printing logic
