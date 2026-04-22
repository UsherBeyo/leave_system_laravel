# BATCH 9 — Notifications

Run:

```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

Test:
- /dashboard
- /leave/requests
- click the bell in the header
- click a notification item and confirm the correct page opens
- confirm the matching leave request accordion auto-opens
- confirm the Leave Requests sidebar badge appears for roles that should see it

Expected behavior by role:
- employee: sees status updates for their own leave requests
- department head: sees requests needing approval and returned/rejected updates for their departments
- manager: sees team request pending/rejected items
- personnel/hr: sees pending final review plus returned/rejected/approved items
- admin: sees all pending counts and recent workflow activity
