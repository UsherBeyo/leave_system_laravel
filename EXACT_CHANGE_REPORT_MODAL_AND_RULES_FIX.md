EXACT CHANGE REPORT - MODAL AND RULES FIX

Changed files:
1. app/Services/LeavePolicyService.php
   - Expanded leave-type rules_text so the Apply Leave page shows more of the same rule guidance found in the reference capstone system.
   - Added/expanded rules for maternity, paternity, special privilege, solo parent, study, VAWC, rehabilitation, special leave benefits for women, calamity, monetization, terminal, and adoption leave.

2. app/Services/LeaveWorkflowService.php
   - Changed required-document validation from allowing checkbox-only selection to requiring actual uploaded file attachment(s) when the leave rule requires supporting documents.
   - Changed attachment storage call so only valid uploaded files are saved.

3. app/Http/Controllers/LeaveRequestController.php
   - Added LeavePolicyService injection.
   - Passed per-request leave policy presets to the Blade view so request details can show human-readable supporting document labels inside the modal.

4. resources/views/leaves/requests.blade.php
   - Replaced inline-only request summary with a modal-based full request details view.
   - Added a "View Full Request Details" button on each leave request card.
   - Added modal sections for Request Summary, Workflow Status, Employee Information, Balance Snapshot, Leave-Specific Details, Comments & Notes, Supporting Documents & Flags, and Uploaded Attachments.
   - Added attachment preview modal for PDF/image files and kept Open File action.
   - Kept existing approval/reject/return/mark-printed action forms in place.

5. resources/views/leaves/apply.blade.php
   - Added id to Filing Date input so client-side notice checking can compare filing date against leave start date.
   - Added attachment file list display below the upload field.
   - Updated helper text to say that checkbox selection alone is not enough when actual uploaded files are required.
   - Added client-side rule warnings for minimum filing notice and missing uploaded attachments according to the selected leave type.

What changed from what to what:
- Leave Requests page: from inline summary + plain attachment links -> modal-based detailed review with attachment preview/open actions.
- Apply Leave rules: from partial UI reminders + server validation that could accept checkbox-only docs -> stronger UI reminders + server validation requiring actual uploaded file(s) when the leave type requires them.

Why each change was necessary:
- The user requested the same behavior style as the reference system for leave request review, especially a modal summary and visibility of uploaded documents.
- The user requested that leave rules from the reference system, including 5-day filing notice behavior and supporting-document expectations, be implemented more faithfully in the Laravel version.

Connected files checked:
- routes/web.php (checked, not changed)
- app/Http/Controllers/LeaveApplicationController.php (checked, not changed)
- app/Models/LeaveRequest.php (checked, not changed)
- app/Models/LeaveAttachment.php (checked, not changed)
- app/Services/LeaveCalculatorService.php (checked, not changed)
- reference files in capstone.zip: views/apply_leave.php, views/leave_requests.php, models/Leave.php

What was NOT changed:
- No route paths were changed.
- No database schema or migrations were changed.
- No dashboard logic was changed.
- No approval workflow logic was changed beyond attachment requirement enforcement during application.
- No reports, employee profile, calendar, or print form files were changed.

Verification steps:
1. Run php artisan optimize:clear
2. Run php artisan serve
3. Open /leave/apply and choose a leave type with minimum notice (Vacation Leave, Force Leave, Special Privilege Leave, Solo Parent Leave).
4. Set Filing Date too close to Start Date.
5. Confirm the red warning message appears before submission.
6. Choose a leave type requiring attachments, do not upload files, and submit.
7. Confirm submission is blocked with an error requiring uploaded document files.
8. Upload the required files and submit again.
9. Open /leave/requests.
10. Click View Full Request Details on a request.
11. Confirm the modal opens and shows request summary, workflow details, leave-specific details, comments/notes, support flags, and uploaded attachments.
12. For image/pdf attachments, click Preview and confirm the preview modal opens.
13. Click Open File and confirm the uploaded file opens in a new tab.
