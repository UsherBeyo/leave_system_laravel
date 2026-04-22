# RUN AFTER CSS ALIGNMENT FIX

1. Extract this ZIP over your local Laravel project folder.
2. Open terminal in the project root.
3. Run:

```bat
php artisan optimize:clear
php artisan view:clear
php artisan serve
```

4. Hard refresh the browser with `Ctrl + F5`.
5. Test:
- http://127.0.0.1:8000/login
- http://127.0.0.1:8000/dashboard
- http://127.0.0.1:8000/leave/apply
- http://127.0.0.1:8000/leave/requests

If the browser still shows the old layout, close the tab and open a new one after `view:clear`.
