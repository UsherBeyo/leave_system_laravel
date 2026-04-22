# Batch 5 Run and Test Guide

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

Open:
- `http://127.0.0.1:8000/manage-departments`
- `http://127.0.0.1:8000/manage-leave-types`
- `http://127.0.0.1:8000/manage-accruals`

## What to test

### 1. Departments
Open `/manage-departments`.

You should see:
- searchable department table
- `+ New Department` button
- Edit button
- Delete button
- pagination when enough rows exist

Test:
1. create a department
2. edit the department name
3. delete a department not in use
4. try deleting a department in use

Expected:
- in-use delete should be blocked with a flash error
- successful actions should show success flash messages

### 2. Leave Types
Open `/manage-leave-types`.

You should see:
- searchable list
- `+ New Leave Type` button
- create and edit modals
- delete action
- rule toggles and text/rule fields

Test:
1. create a new leave type
2. set deduct/approval/doc flags
3. edit it
4. delete it

Expected:
- list updates correctly
- rules save correctly
- no route crash when opening modals or submitting forms

### 3. Accruals
Open `/manage-accruals`.

You should see:
- manual accrual card
- bulk accrual launcher
- accrual history table with search and pagination

#### Manual accrual test
1. choose an employee
2. amount `1.250`
3. select month
4. submit

Expected:
- employee annual balance increases by `1.250`
- employee sick balance increases by `1.250`
- force balance does not change
- history row appears

#### Bulk accrual test
1. click `Open Bulk Accrual`
2. enter amount and month
3. confirm all 3 confirmation dialogs

Expected:
- all employees get the increase on annual and sick balances
- no force balance change
- history rows are created

## Old URL redirects to test
- `http://127.0.0.1:8000/views/manage_departments.php`
- `http://127.0.0.1:8000/views/manage_leave_types.php`
- `http://127.0.0.1:8000/views/manage_accruals.php`

Expected:
- these should redirect to the new pure Laravel routes
