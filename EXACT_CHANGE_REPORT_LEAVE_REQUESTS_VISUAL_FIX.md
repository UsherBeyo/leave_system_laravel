# EXACT CHANGE REPORT — LEAVE REQUESTS VISUAL FIX

## Scope requested
Only improve the Leave Requests page design and add a balance visualization showing the employee's current balances beside the projected or recorded final balances after approval.

## Files changed

### 1) `app/Services/LeaveWorkflowService.php`
Added a new method:
- `previewApprovalImpact(LeaveRequest $leave)`

What it does:
- reads the leave request, employee balances, leave type, and request details
- calculates the default approval impact using the same leave-rule logic already used by the workflow
- returns:
  - current balances
  - projected or recorded after-approval balances
  - deduction amounts per bucket
  - days with pay / without pay / deduct days
  - highlighted affected balance buckets
  - workflow notes for the UI

Why it was necessary:
- the Leave Requests page needed balance visualization based on the actual current Laravel workflow logic instead of hardcoded UI values

### 2) `app/Http/Controllers/LeaveRequestController.php`
Changed the `index()` method.

From:
- only passing `leavePolicies`

To:
- also passing `approvalImpacts`

Why it was necessary:
- the Blade page needs precomputed approval-impact data for each listed request

### 3) `resources/views/leaves/requests.blade.php`
Reworked the page layout for the Leave Requests screen.

What changed:
- redesigned the request cards into cleaner structured sections
- added a top section with employee/request summary
- added a side status/review summary panel
- added three balance cards:
  - current balance
  - projected final balance / recorded final balance
  - deduction highlight
- added explanation text for projected vs finalized requests
- cleaned the approval / reject / return / mark printed sections into card-style action panels
- kept the full request details modal
- kept the uploaded attachment preview/open actions
- kept filters, tabs, pagination, and workflow buttons

Why it was necessary:
- the previous design was visually messy and did not show the approval balance effect clearly

## Connected files checked but not changed
- `app/Models/LeaveRequest.php`
- `app/Models/Employee.php`
- `app/Services/LeavePolicyService.php`
- `resources/views/layouts/app.blade.php`
- `public/assets/css/styles.css`

## What was NOT changed
- no routes
- no database schema
- no leave application page
- no dashboard logic
- no reports/profile/calendar/print pages
- no approval action logic beyond reading the existing workflow rules for visualization

## Verification steps
1. Replace the project with this patched ZIP.
2. Run:
   - `php artisan optimize:clear`
   - `php artisan view:clear`
   - `php artisan serve`
3. Open:
   - `http://127.0.0.1:8000/leave/requests`
4. What you should see:
   - cleaner request cards
   - current balance cards
   - projected or recorded final balance cards beside them
   - highlighted affected balance buckets
   - action areas grouped more cleanly
5. Click `View Full Request Details`.
6. In the modal you should still see:
   - request summary
   - employee info
   - comments
   - supporting document flags
   - uploaded attachments with Preview / Open File
7. For pending requests:
   - the balance cards should show a projected final balance based on the current workflow rules
8. For finalized requests:
   - the balance cards should show the recorded final balance snapshot saved when the request was approved

## Required dependency changes only
None beyond the controller-service-view connection needed for the requested visualization.
