# Exact Change Report - Batch 10 Custom 404

## Files changed
- `resources/views/errors/404.blade.php` ← new

## What changed
Added a Laravel-native custom 404 page modeled directly on the reference system's `views/error.php`.

## What changed from what to what
- **Before:** Non-existing manually typed URLs used Laravel's default 404 handling page.
- **After:** Non-existing manually typed URLs now render a custom Leave System 404 page using the same visual structure and wording style as the reference system.

## Features carried over from the reference
- centered error card
- floating decorative orbs
- requested path chip
- auth-aware primary action:
  - guest → `Go to Login`
  - logged-in user → `Back to Dashboard`
- secondary `Go Back` button using browser history with a fallback to the main destination
- uses the existing system CSS and favicon
- same parallax mouse movement effect on the card/orbs

## What was not changed
- no routes
- no controllers
- no database schema
- no workflow logic
- no other pages
