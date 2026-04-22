# Batch 10 - Custom 404

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

## Test
Open a non-existing URL, for example:
- `http://127.0.0.1:8000/this-page-does-not-exist`
- `http://127.0.0.1:8000/views/not_real.php`

## What you should see
- custom Leave System error page instead of Laravel's default 404 page
- requested path shown in the chip
- `Back to Dashboard` when logged in
- `Go to Login` when logged out
- `Go Back` button
