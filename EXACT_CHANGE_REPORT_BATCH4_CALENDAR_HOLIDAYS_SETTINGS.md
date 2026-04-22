# EXACT CHANGE REPORT — BATCH 4 CALENDAR + HOLIDAYS + SETTINGS

## Files changed

### Added
- `app/Models/Holiday.php`
- `app/Http/Controllers/CalendarController.php`
- `app/Http/Controllers/HolidayController.php`
- `resources/views/calendar/index.blade.php`
- `resources/views/holidays/index.blade.php`

### Updated
- `routes/web.php`
- `app/Http/Controllers/SignatorySettingsController.php`
- `resources/views/settings/signatories.blade.php`

## What changed

### 1) Calendar route
- Changed `/calendar` from a placeholder route to a real controller route.
- Added old-PHP redirect support for:
  - `/views/calendar.php`

### 2) Holidays route
- Changed `/holidays` from a placeholder route to a real controller route.
- Added CRUD routes:
  - `GET /holidays`
  - `POST /holidays`
  - `PUT /holidays/{holiday}`
  - `DELETE /holidays/{holiday}`
- Added old-PHP redirect support for:
  - `/views/holidays.php`

### 3) Signatories settings legacy redirect
- Added redirect:
  - `/views/signatories_settings.php` -> `/signatories-settings`

### 4) Calendar features added
- Month grid with Monday–Sunday layout
- Prev / Today / Next navigation in page header
- Month/year jump form
- Colored daily chips for:
  - Holiday
  - Approved Leave
  - Pending Leave
- Upcoming Leaves modal
- Upcoming Events modal
- Monthly snapshot modal for admin/personnel/hr
- Right-side day detail panel when clicking a date
- Role-aware event visibility:
  - admin/personnel/hr = full calendar
  - department_head/manager = department-scoped
  - others = own leave only

### 5) Holidays features added
- Search box
- Add holiday form
- Inline update per holiday row
- Delete holiday action
- Pagination using the current clean pagination view
- Same holiday types used in the reference system

### 6) Signatories settings polish
- Auto-seeds missing default rows for:
  - `certification`
  - `final_approver`
- Added summary cards and clearer helper text
- Kept same save flow and same table-edit behavior

## Connected files checked but not changed
- `resources/views/partials/sidebar.blade.php`
- `resources/views/layouts/app.blade.php`
- `app/Models/LeaveRequest.php`
- `app/Models/Employee.php`
- `app/Http/Controllers/LeaveRequestController.php`
- `app/Http/Controllers/PrintLeaveFormController.php`

## What was not changed
- No leave-request workflow logic
- No apply-leave logic
- No deduction logic
- No reports logic
- No print-form layout
- No database schema changes
- No unrelated CSS/layout pages

## Validation performed
- `php -l` passed on all new/changed PHP class files
- `php artisan route:list` passed
- Confirmed calendar and holidays routes are registered

## Environment note
- `php artisan view:cache` could not be completed inside this container because the container PHP is missing the `DOMDocument` extension.
- That is an environment limitation of this container, not a syntax failure in the project files.
