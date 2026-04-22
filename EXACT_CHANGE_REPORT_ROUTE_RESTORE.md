# EXACT CHANGE REPORT - ROUTE RESTORE FIX

Changed file:
- `routes/web.php`

What changed:
- Added missing controller imports:
  - `EmployeeProfileController`
  - `PrintLeaveFormController`
  - `ReportsController`
  - `SignatorySettingsController`
- Restored missing named routes that existing views/controllers already call:
  - `reports`
  - `employee-profile`
  - `leave.print`
  - `leave.print.signatories`
  - `signatories-settings`
  - `signatories-settings.update`
- Kept existing login/dashboard/apply/leave-requests routes intact.
- Left placeholder routes for unfinished pages unchanged.

Why it was necessary:
- `resources/views/leaves/requests.blade.php` calls `route('leave.print', ['leave' => $row->id])`.
- `PrintLeaveFormController` redirects to `route('leave.print', ...)` after saving signatories.
- `resources/views/settings/signatories.blade.php` posts to `route('signatories-settings.update')`.
- Those named routes were missing in the uploaded ZIP, causing the crash.

Validation performed:
- `php -l routes/web.php` passed.
- `php artisan route:list` ran successfully after the patch.
- Verified that `leave.print` and `leave.print.signatories` are now registered.

What was NOT changed:
- No controllers
- No views
- No database schema
- No workflow logic
- No CSS/layout
