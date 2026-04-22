Changed only:
- resources/views/employees/index.blade.php

What changed:
- Replaced the wide employee table with a responsive employee card/list layout.
- Kept the same data points and same actions: Profile, Leave Card, Edit.
- Kept the same search, count summary, pagination, modals, edit/create JS, and backend routes.
- Kept image preview click behavior.

Why this was necessary:
- The previous table layout had too many columns competing for width.
- At normal desktop widths and different browser zoom levels, the right-side action buttons were clipped.
- A card/grid layout is more resilient and keeps actions visible without changing backend logic.

What was not changed:
- No controllers
- No models
- No routes
- No database or schema
- No employee CRUD logic
- No leave logic
- No notifications or other pages
