# EXACT CHANGE REPORT — CSS ALIGNMENT FIX

## Goal
Fix the broken CSS/layout for the currently converted native Laravel pages by aligning their structure with the original capstone app shell and design classes.

## Files changed

### 1) `resources/views/layouts/app.blade.php`
**Changed from:** a custom inline Laravel-only shell using `layout-shell`, `main-panel`, a blue sidebar, and `app-header-native`.

**Changed to:** the original capstone-style shell structure:
- includes `partials.header`
- includes `partials.sidebar`
- wraps page content in `<main class="app-main"><div class="page-shell">...</div></main>`
- keeps flash messages
- keeps unauthenticated pages outside the app shell
- removes the shell styles that were fighting the original `assets/css/styles.css`
- keeps only compatibility styles needed by the currently converted Laravel pages (`metric-grid`, `metric-card`, `section-card`, `request-card`, `form-grid`, `field`, `tab-links`, `request-actions`, `clean-table`)

**Why necessary:** the previous layout structure did not match the original CSS assumptions, so the dashboard and other pages rendered in the wrong positions.

### 2) `resources/views/partials/sidebar.blade.php`
**Changed from:** simplified Laravel sidebar markup using `app-sidebar` and a reduced visual structure.

**Changed to:** capstone-aligned sidebar markup using the original class structure:
- `<aside class="sidebar">`
- `sidebar-nav`
- `sidebar-section`
- `sidebar-section-label`
- `sidebar-link`
- original icon style preserved
- Laravel routes kept for all links

**Why necessary:** the original CSS styles the sidebar using the capstone sidebar class system. The previous markup did not fully match that system.

### 3) `resources/views/partials/header.blade.php`
**Changed from:** simplified header markup.

**Changed to:** capstone-aligned header structure using:
- `app-header`
- `header-content-wrapper`
- `header-left`
- `header-brand-area`
- `header-right-container`
- `profile-section`
- `profile-info`
- `profile-button`
- `profile-menu`

**Why necessary:** the original stylesheet expects this header/profile structure for spacing and alignment.

### 4) `resources/views/dashboard/index.blade.php`
**Changed from:** a compact Laravel-only dashboard body that depended on the broken shell.

**Changed to:** a capstone-aligned page flow using:
- `partials.page-header`
- `ui-card`
- `metric-grid`
- `content-grid`
- `table-wrap`
- `clean-table`
- same dashboard data and same role-driven behavior already present in Laravel

**Why necessary:** the page needed a proper page header and content shell to sit correctly under the fixed header/sidebar system.

### 5) `resources/views/leaves/apply.blade.php`
**Changed from:** a functional but structurally plain Laravel form page.

**Changed to:** a capstone-aligned page using:
- `partials.page-header`
- `ui-card`
- `metric-grid`
- `form-grid`
- `field`
- `rule-box`
- same current Laravel leave form fields and same leave-rule JS

**Why necessary:** the form needed to inherit the original page spacing and card styling consistently.

### 6) `resources/views/leaves/requests.blade.php`
**Changed from:** a functional Laravel requests page with minimal structure.

**Changed to:** a capstone-aligned requests page using:
- `partials.page-header`
- `tab-links`
- `ui-card`
- `request-card`
- `form-grid`
- `request-actions`
- same current Laravel workflow buttons and request data

**Why necessary:** the filter bar, tab row, and request cards needed consistent styling under the original shell.

### 7) `resources/views/placeholders/page.blade.php`
**Changed from:** a plain placeholder card.

**Changed to:** a capstone-aligned placeholder page with:
- `partials.page-header`
- `ui-card`
- same placeholder purpose

**Why necessary:** placeholder pages were also part of the currently visible Laravel routes and needed consistent styling.

## Connected files checked
- `public/assets/css/styles.css`
- `resources/views/auth/login.blade.php`
- `resources/views/layouts/auth.blade.php`
- `resources/views/partials/page-header.blade.php`
- `routes/web.php`
- original reference files from capstone ZIP:
  - `views/layout/header.php`
  - `views/partials/sidebar.php`
  - `views/dashboard.php`
  - `views/apply_leave.php`
  - `views/leave_requests.php`
  - `assets/css/styles.css`

## Checked but left untouched
- controllers
- models
- routes
- database config
- migrations
- leave rules
- approval workflow logic
- calculation API
- login logic
- logout logic
- uploaded assets/css file contents

## What was NOT changed
- no backend workflow logic
- no database schema
- no leave calculations
- no approval rulings
- no route map changes
- no auth logic changes
- no report logic changes
- no calendar logic changes

## Verification steps
1. Replace your project with this patched ZIP.
2. In terminal:
   - `php artisan optimize:clear`
   - `php artisan view:clear`
   - `php artisan serve`
3. Hard refresh the browser with `Ctrl + F5`.
4. Test these pages:
   - `/login`
   - `/dashboard`
   - `/leave/apply`
   - `/leave/requests`
   - `/calendar`
   - `/reports`
   - `/manage-employees`
   - `/manage-departments`
   - `/holidays`
   - `/manage-accruals`
   - `/manage-leave-types`
   - `/signatories-settings`
5. Confirm:
   - header is fixed at the top
   - sidebar is white and aligned like the reference app
   - page content sits to the right of the sidebar
   - dashboard content no longer overlaps the header/sidebar
   - apply leave and leave requests cards/forms render with spacing and borders
   - placeholder pages follow the same shell

## Required dependency changes only
None beyond the Blade/layout structure listed above.
