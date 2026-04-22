EXACT CHANGE REPORT — UI RESPONSIVE FIX

Changed files:
1. resources/views/calendar/index.blade.php
2. resources/views/profile/show.blade.php
3. resources/views/layouts/app.blade.php

What changed:
- Calendar side panel now opens below the fixed header so the header no longer overlaps the panel title and close button.
- Calendar side panel z-index increased and mobile sizing adjusted.
- Employee Profile page-specific large avatar class renamed from `profile-avatar` to `profile-hero-avatar` so it no longer affects the small header avatar.
- Employee Profile card spacing, grids, and table wrappers were tightened for cleaner layout and less overlap.
- Global responsive button wrapping and action-row behavior improved in the shared layout so page action buttons stay visible at different zoom levels.
- Header avatar size is now hard-capped inside the header to stop random enlargement.

What was NOT changed:
- No controllers
- No models
- No routes
- No database schema
- No workflow logic
- No approval logic
- No calendar backend/event queries
- No profile backend/history queries
