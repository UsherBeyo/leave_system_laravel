# Batch 7 - Run and Test Guide

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

## Test 1 - Employee Profile page
Open:
- `http://127.0.0.1:8000/employee-profile`
- or a specific employee: `http://127.0.0.1:8000/employee-profile?employee=ID`

Expected:
- normal profile page still loads
- export buttons now include CSV and XLS options

## Test 2 - Add Leave History Entry
Login as `admin`, `hr`, or `personnel`.

On Employee Profile:
- click **Add Leave History Entry**

Try these cases:
1. Regular leave type
2. `Vacational Accrual Earned`
3. `Undertime`

Expected:
- history entry saves
- row appears in leave history or budget history depending on type
- no current-balance change for history-only entries except where the reference behavior explicitly applies

## Test 3 - Record Undertime
Login as `admin`, `hr`, or `personnel`.

On Employee Profile:
- click **Record Undertime**
- enter date, hours, minutes
- submit

Expected:
- employee annual/vacational balance decreases
- budget history row is added
- leave balance log row is added

## Test 4 - XLS exports
On Employee Profile:
- Export History XLS
- Export Leave Card XLS

On Reports:
- Export XLS for balance/usage
- Export Leave Card XLS for selected employee

Expected:
- downloadable Excel-compatible file opens in Excel

## Test 5 - Old PHP-style URL redirect
Open:
- `http://127.0.0.1:8000/views/employee_profile.php?id=1`

Expected:
- redirects to the Laravel employee profile route
