Pure Laravel login CSS fix

Files changed:
1. resources/views/layouts/app.blade.php
   - Changed <body> to <body class="@yield('body_class')">.
   - Why: the original styles.css has login-page-specific CSS overrides. Without a body class, the guest login page inherited the app grid shell and looked broken.

2. resources/views/auth/login.blade.php
   - Replaced the simplified inline-style login markup with a Blade version of the reference capstone login structure.
   - Preserved Laravel-native behavior:
     - POST route: login.perform
     - @csrf
     - old('email')
     - validation/error display from Laravel
   - Added the privacy modal, password toggle, and mascot cursor script using native Blade + JS.
   - Why: the old simplified Blade did not match the reference CSS structure/classes, so the existing styles.css could not render the intended design.

What was not changed:
- no controller logic
- no routes
- no database logic
- no leave rulings
- no dashboard logic
- no asset files in public/assets/css/styles.css
