# PURE LARAVEL BATCH 3 — RUN AND TEST

## 1. Replace the project files
Replace your local Laravel project with the contents of this patched ZIP.

## 2. Run locally
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

Open:
- `http://127.0.0.1:8000/login`

## 3. Test Reports
Open:
- `http://127.0.0.1:8000/reports`

What you should see:
- summary cards for total employees, pending requests, approved requests, average vacational balance
- report type filter
- department filter

Test these:
- Summary
- Leave Balance
- Leave Usage
- Leave Card

For Leave Card:
- choose an employee
- click Apply Filter
- ledger rows should appear

## 4. Test Employee Profile
Open from:
- header profile menu for your own account
- Reports -> Profile button on an employee row
- Leave Requests -> Profile button

What you should see:
- employee info header
- leave balances section
- leave history table
- budget history table
- leave card table

## 5. Test Print Leave Form
From Leave Requests:
- open an approved/finalized request
- click `Print Form`

What you should see:
- print preview page
- Print button
- employee information
- leave details
- certification / recommendation section
- supporting documents list
- signatories section

## 6. Legacy compatibility checks
These should redirect into the Laravel pages:
- `http://127.0.0.1:8000/views/employee_profile.php?id=1`
- `http://127.0.0.1:8000/views/print_leave_form.php?id=1`

## 7. What is still placeholder after this batch
- calendar
- change password
- manage employees
- manage departments
- holidays
- accruals
- leave types
- signatories settings
