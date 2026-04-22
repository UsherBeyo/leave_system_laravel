Session fix applied:
- Default Laravel session driver forced to file in config/session.php
- Default cache store forced to file in config/cache.php
- Default queue connection forced to sync in config/queue.php
- .env kept on file/sync/file
- Added sessions table migration as fallback

After replacing your project folder, run:
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
php artisan migrate
php artisan serve
