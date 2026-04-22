# Pure Laravel Batch 2 — Local Run Guide

This package is a real Laravel codebase, not the old bridge.

## What this batch includes
- native Laravel login/logout
- native Laravel dashboard
- native Laravel leave application form
- native Laravel leave request approval queue
- compatibility migration for the legacy database

## 1) Put this folder in XAMPP htdocs
Extract to:

`C:\xampp\htdocs\leave-system-laravel`

## 2) Start XAMPP
Start:
- Apache
- MySQL

## 3) Create the database
In phpMyAdmin, create:

`leave_system_laravel`

## 4) Import the legacy SQL first
Import these in order:
1. `database/legacy_import/leave_system.sql`
2. `database/legacy_import/leave_attachments_migration.sql`

## 5) Configure `.env`
Set these values:

```env
APP_NAME="Leave System Laravel"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

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

## 6) Open terminal in the project folder
Run:

```bat
cd C:\xampp\htdocs\leave-system-laravel
composer dump-autoload
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate
php artisan serve
```

## 7) Open the app
Go to:

`http://127.0.0.1:8000/login`

## 8) How to test
### Login
Use a user from your imported DB that has `is_active = 1`.

### Employee flow
- log in as employee
- open Dashboard
- click Apply Leave
- choose a leave type
- set start/end dates
- submit

### Approval flow
Your old SQL dump mainly contains `admin` and `employee` roles.
To test the department head/personnel queue fully, update some users in phpMyAdmin after migration, for example:
- set one user `role = department_head`
- set one user `role = personnel`
- make sure the employee rows and `department_head_assignments` table match the correct department

## 9) Routes in this batch
- `/login`
- `/dashboard`
- `/leave/apply`
- `/leave/requests`
- `/api/calc-days`

## 10) Important notes
- This is now pure Laravel code for the modules included in this batch.
- It no longer calls the old PHP pages.
- Reports, print form, admin CRUD pages, calendar, and profile pages still need the next batch.
