1. Put this folder in:
   C:\xampp\htdocs\leave-system-laravel

2. Start XAMPP:
   - Apache
   - MySQL

3. Create or confirm this database exists in phpMyAdmin:
   leave_system_laravel

4. Import these SQL files in this order:
   - legacy_app/capstone/leave_system (1).sql
   - legacy_app/capstone/leave_attachments_migration.sql

5. Open Command Prompt in:
   C:\xampp\htdocs\leave-system-laravel

6. Run:
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   php artisan view:clear
   php artisan serve

7. Open:
   http://127.0.0.1:8000

8. First test pages:
   - http://127.0.0.1:8000/
   - http://127.0.0.1:8000/views/login.php
   - http://127.0.0.1:8000/views/dashboard.php

9. If login fails, check legacy_app/capstone/config/database.php and make sure the MySQL database name is really leave_system_laravel.

10. This build is a Laravel bridge for exact local parity first.
    It is not yet the final native Laravel rewrite.
