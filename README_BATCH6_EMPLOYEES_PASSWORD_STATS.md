# Batch 6 — Run and test guide

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

Open:
- `http://127.0.0.1:8000/manage-employees`
- `http://127.0.0.1:8000/change-password`
- `http://127.0.0.1:8000/statistics`
- `http://127.0.0.1:8000/manage-departments`
- `http://127.0.0.1:8000/manage-leave-types`
- `http://127.0.0.1:8000/manage-accruals`

## What to test

### 1. Manage Employees
- Page loads without 404.
- Search works.
- `+ New Employee` opens modal.
- Creating an employee creates both user + employee row.
- Edit button opens modal.
- Saving edit updates email/role/balances/profile fields.
- Profile button opens employee profile.
- Leave Card button downloads CSV.
- Old URL redirects:
  - `/views/manage_employees.php`
  - `/views/edit_employee.php?id=...`

### 2. Change Password
- Page loads.
- Wrong current password shows error.
- Correct current password updates password and redirects to dashboard.
- Old URL redirect works:
  - `/views/change_password.php`

### 3. Statistics
- Page loads for admin.
- Summary cards show totals.
- Department table shows counts.
- Role table shows counts.
- Old URL redirect works:
  - `/views/statistics.php`

### 4. Departments / Leave Types / Accruals
- `manage-departments` now opens as real Laravel page.
- `manage-leave-types` now opens as real Laravel page.
- `manage-accruals` now opens as real Laravel page.
- Old PHP-style URLs redirect to the new routes.
