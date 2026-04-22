Exact files changed in this patched Laravel ZIP:

1. routes/web.php
- Changed from default Laravel welcome route
- To legacy bridge routes for /, /views/*.php, /controllers/*.php, /api/calc_days.php, /scripts/*.php

2. app/Http/Controllers/LegacyBridgeController.php
- Added new controller
- Purpose: dispatch Laravel routes into the matching legacy PHP files

3. app/Support/LegacyBridge.php
- Added new support class
- Purpose: execute the original PHP file from legacy_app/capstone while preserving request context

4. legacy_app/capstone/**
- Added the user's PHP capstone source into Laravel project
- Kept original files and structure intact

5. legacy_app/capstone/config/database.php
- Changed database name from leave_system to leave_system_laravel
- Host remains localhost
- Username remains root
- Password remains blank

6. public/assets/**
7. public/pictures/**
8. public/uploads/**
- Added original static files so the legacy pages render the same design locally

What was not changed:
- No business rules were edited
- No controller logic inside the legacy PHP app was rewritten
- No design files were restyled
- No database schema was rewritten
- No leave rulings were changed
