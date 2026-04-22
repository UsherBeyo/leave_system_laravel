# Batch 11 Run and Test Guide

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

## Test 1 — Dashboard parity
Open:
- `http://127.0.0.1:8000/dashboard`

What you should see:
- Employee: doughnut charts + recent requests + pending snapshot
- Department Head: pending review + upcoming team leaves
- Personnel: pending final review + print queue
- Manager / HR: pending table + department chart
- Admin: department chart + role chart + recent users

## Test 2 — Employee Profile self-service
Open:
- `http://127.0.0.1:8000/employee-profile`

What you should see:
- Change Photo button on self profile
- Change Password button on self profile
- Update Balances button for admin / hr / personnel
- Existing Add Leave History Entry and Record Undertime still present

### Change Photo test
- Open photo modal
- Choose an image
- Preview should change
- Save
- Profile avatar should update

### Change Password test
- Open password modal
- Wrong current password should fail
- Correct current password should save

### Update Balances test
- Open balances modal
- Change one or more balances
- Save
- Current balance cards should update
- Budget history should receive adjustment rows

## Test 3 — Leave Requests search parity
Open:
- `http://127.0.0.1:8000/leave/requests`

What you should see:
- New `Search This Section` field
- Typing should auto-submit after a short pause
- Search should filter only the current tab / current scoped section
- Existing accordion cards should remain intact
- `Open Full Leave Card` should appear under Employee Shortcuts

## Test 4 — Tab filter persistence
- Apply month / year / search filters
- Switch tabs
- Filters should remain in the URL instead of resetting immediately
