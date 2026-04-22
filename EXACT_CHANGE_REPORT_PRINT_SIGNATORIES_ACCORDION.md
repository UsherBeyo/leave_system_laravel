Files changed
- app/Models/LeaveRequest.php
- app/Http/Controllers/LeaveRequestController.php
- app/Services/LeaveWorkflowService.php
- app/Http/Controllers/PrintLeaveFormController.php
- app/Http/Controllers/SignatorySettingsController.php
- routes/web.php
- resources/views/leaves/requests.blade.php
- resources/views/print/leave-form.blade.php
- resources/views/settings/signatories.blade.php

What changed
1. Leave requests page
- Restored the compact lengthwise request row layout.
- Each request is now a collapsed accordion row by default.
- Clicking the row expands the detailed card below it.
- Kept the full request details modal.
- Added back the Personnel/HR/Admin signatory customization modal for approved/finalized requests.
- Kept attachment preview/open actions.

2. Historical balance visualization
- Pending requests still show Current Balance -> After Approval.
- Finalized/approved requests now show Before Approval -> Recorded Final.
- Finalized values no longer use the employee's live current balance as the left-side reference.
- Historical values are derived from leave_request_forms when available, with fallback to stored request snapshots.

3. Exact print form layout
- Replaced the simplified Laravel print form with a Blade version modeled directly on the old capstone print form structure.
- Reused the existing print_leave_form.css file from the reference assets.
- Kept the same section ordering, wording, checkbox layout, certification table, recommendation block, approval block, and signatory placement.
- The page still uses Laravel data sources.

4. Signatories feature
- Added pure Laravel Signatories Settings page.
- Added save route for default signatories.
- Added per-request Save & Print signatory modal on approved/finalized leave requests.
- Added save route that writes the request-specific signatories into leave_request_forms before opening the print form.

Connected files checked but left untouched
- app/Http/Controllers/EmployeeProfileController.php
- app/Services/LeavePolicyService.php
- public/assets/css/print_leave_form.css
- resources/views/layouts/app.blade.php
- resources/views/partials/sidebar.blade.php

What was not changed
- No leave rule logic outside the historical preview calculation adjustment.
- No database schema change.
- No dashboard changes.
- No apply leave page change.
- No reports page change.
- No calendar page change.
- No employee profile page change.
- No unrelated management CRUD changes.

How to verify
1. Run:
   - php artisan optimize:clear
   - php artisan view:clear
   - php artisan serve
2. Open /leave/requests.
3. Confirm each request appears as a compact horizontal row.
4. Click a row and confirm the large detail card expands underneath.
5. Open a pending request and confirm balance cards show Current Balance -> After Approval.
6. Open a finalized request and confirm balance cards show Before Approval -> Recorded Final.
7. For a finalized request, click Customize Signatories & Print.
8. Edit the names/positions and click Save & Print.
9. Confirm the opened print page uses the legacy-style exact form layout rather than the simplified Laravel page.
10. Open /signatories-settings, edit defaults, save, then reopen a finalized request print modal and verify the defaults appear there.
