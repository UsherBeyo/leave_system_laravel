# BATCH 4 — CALENDAR + HOLIDAYS + SETTINGS

## Run
```bat
cd C:\xampp\htdocs\leave-system-laravel
php artisan optimize:clear
php artisan route:clear
php artisan view:clear
php artisan serve
```

## Test Calendar
Open:
- `http://127.0.0.1:8000/calendar`

What you should see:
- month grid calendar
- Prev / Today / Next buttons
- Month and Year jump controls
- colored chips on days with holidays / approved leaves / pending leaves
- right-side detail panel when clicking a date with events
- quick-view buttons on the right side for Upcoming Leaves and Upcoming Events

Also test old URL redirect:
- `http://127.0.0.1:8000/views/calendar.php`

## Test Holidays
Open:
- `http://127.0.0.1:8000/holidays`

What you should see:
- holiday list table
- add holiday form at the top
- update controls inline on each row
- delete button on each row
- pagination at the bottom when enough rows exist

Also test old URL redirect:
- `http://127.0.0.1:8000/views/holidays.php`

### Verify holiday-to-calendar link
1. Add a holiday for a visible date in the current month.
2. Open `/calendar`.
3. The matching date should now show a Holiday chip.
4. Click the date.
5. The detail panel should list that holiday.

## Test Signatories Settings
Open:
- `http://127.0.0.1:8000/signatories-settings`

What you should see:
- 7.A row
- 7.C row
- editable name and position fields
- save button

Also test old URL redirect:
- `http://127.0.0.1:8000/views/signatories_settings.php`

### Verify print integration
1. Change one signatory value and save.
2. Go to a finalized request.
3. Open the print-related action/modal.
4. The updated signatory value should appear as the default.
