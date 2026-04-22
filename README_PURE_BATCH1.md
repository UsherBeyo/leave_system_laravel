# PURE LARAVEL BATCH 1 — START HERE

This package is **no longer the Laravel bridge**.
It is a **native Laravel Batch 1** for your Leave Management System.

## What this batch already converts to pure Laravel
- login/authentication
- logout
- shared layout
- header/sidebar
- dashboard entry flow
- role-aware dashboard rendering
- base models and compatibility migrations

## What this batch does not convert yet
These routes currently open placeholder pages and will be converted in the next batches:
- Apply Leave
- Leave Requests
- Calendar
- Reports
- Manage Employees
- Manage Departments
- Holidays
- Accruals
- Leave Types
- Settings
- Employee Profile

## Local setup from the top

### 1) Put the project in XAMPP htdocs
Extract this folder to:

`C:\xampp\htdocs\leave-system-laravel`

### 2) Start XAMPP
Start:
- Apache
- MySQL

### 3) Create the database
In phpMyAdmin, create:

`leave_system_laravel`

### 4) Import your old database first
Import this file first:
- `database/legacy_import/leave_system.sql`

Then import this file:
- `database/legacy_import/leave_attachments_migration.sql`

This keeps your real existing data and behavior base.

### 5) Open terminal in the Laravel project root
Run:

```bat
cd C:\xampp\htdocs\leave-system-laravel
```

### 6) Install/update dependencies if needed
If `vendor` is missing, run:

```bat
composer install
```

If `vendor` already exists, you can still run:

```bat
composer dump-autoload
```

### 7) Make sure `.env` is correct
This package already sets `.env` for local testing, but confirm these values:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=leave_system_laravel
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=file
CACHE_STORE=file
QUEUE_CONNECTION=sync
```

### 8) Run Laravel migrations
After importing the old SQL, run:

```bat
php artisan migrate
```

This adds any missing compatibility tables/columns used by the pure Laravel dashboard.

### 9) Serve the app locally
Run:

```bat
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

### 10) Open the app
Go to:

`http://127.0.0.1:8000/login`

## Login behavior
This Laravel login uses the existing `users` table and the old password hashes.

So you should be able to log in using your existing accounts from the imported database.

## If `php artisan` fails because of mbstring
Enable `mbstring` in your XAMPP PHP config:
- open `php.ini`
- find `;extension=mbstring`
- remove the semicolon
- restart Apache

## Batch 1 verification steps
1. Open `/login`
2. Check that CSS and logos load
3. Log in using an existing imported account
4. Confirm redirect to `/dashboard`
5. Confirm the dashboard changes based on the account role
6. Confirm `/logout` works
7. Confirm sidebar links open placeholder pages instead of bridge PHP

## Notes
- This batch is pure Laravel code.
- It does **not** call the old PHP pages anymore.
- The design is kept close using your current CSS/assets.
