# EXACT CHANGE REPORT — BATCH 9 NOTIFICATIONS

Changed files:
- app/Services/NotificationService.php
- app/Providers/AppServiceProvider.php
- resources/views/partials/header.blade.php
- resources/views/partials/sidebar.blade.php
- resources/views/leaves/requests.blade.php

What changed:
1. Added a real Laravel notification backend service modeled on the reference helper logic.
   - role-aware sidebar badge counts
   - role-aware header notification items
   - same notification titles/messages/tone mapping as the reference flow
   - notification links now go to Laravel routes instead of legacy PHP pages

2. Added shared notification data to header and sidebar through a Laravel view composer.
   - header gets recent notifications, count, and latest timestamp
   - sidebar gets leave request badge counts

3. Rebuilt the header notification bell/dropdown in pure Laravel.
   - same bell/dropdown structure as the reference
   - same localStorage seen/unseen badge behavior
   - same recent-items list and empty state message

4. Restored the Leave Requests sidebar badge.
   - shows pending approval counts by role like the reference

5. Added notification deep-link behavior to Leave Requests.
   - notification links can pass open_detail
   - matching accordion row auto-opens when clicked from a notification

What was not changed:
- no database schema
- no leave workflow deduction logic
- no apply leave rules
- no print form logic
- no reports logic
- no calendar logic
