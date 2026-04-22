# Run After Route Fix

1. Replace your current project folder with this patched copy.
2. Open terminal in the project root.
3. Run:

```bat
php artisan optimize:clear
php artisan migrate
php artisan serve
```

4. Open these URLs in order:

- `http://127.0.0.1:8000/login`
- `http://127.0.0.1:8000/dashboard`
- `http://127.0.0.1:8000/leave/apply`
- `http://127.0.0.1:8000/leave/requests`

5. Old PHP-style URLs should now redirect instead of 404:

- `http://127.0.0.1:8000/views/login.php`
- `http://127.0.0.1:8000/views/dashboard.php`
- `http://127.0.0.1:8000/views/apply_leave.php`
- `http://127.0.0.1:8000/views/leave_requests.php`

If any new error appears, send the exact message or a screenshot.
