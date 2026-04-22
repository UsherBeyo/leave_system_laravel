EXACT CHANGE REPORT — ROW SPACING AND PAGINATION FIX

Files changed:
1. app/Models/LeaveRequest.php
2. app/Http/Controllers/LeaveRequestController.php
3. app/Services/LeaveWorkflowService.php
4. resources/views/leaves/requests.blade.php
5. resources/views/vendor/pagination/clean.blade.php

What changed:
- Restored the leave requests accordion row layout and supporting data flow on the active pure Laravel page.
- Increased the accordion summary row height and padding so long employee details no longer feel cramped.
- Widened the primary, status, and expand columns so status badges and the Expand button do not squeeze vertically.
- Switched the requests page pagination to a custom Laravel pagination view that uses text-based Prev/Next links instead of oversized default SVG icons.

What was not changed:
- No routes were changed.
- No database schema was changed.
- No print-form checkbox mapping was changed.
- No approval logic was changed beyond restoring the request-page data it already expected.
