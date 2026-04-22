# Exact Change Report — Row Height and Print Leave Type Fix

## Files changed
1. `resources/views/leaves/requests.blade.php`
2. `resources/views/print/leave-form.blade.php`

## What changed

### 1) `resources/views/leaves/requests.blade.php`
Changed the compact accordion row styling only.

From:
- tighter horizontal grid sizing
- smaller avatar and tighter padding
- subtext forced into a single line with ellipsis

To:
- wider first column and better column spacing
- taller row with more vertical padding
- slightly larger avatar
- subtext allowed to wrap naturally instead of colliding with adjacent columns
- added a medium-screen layout adjustment so the row stays neat before mobile collapse

Why:
- the request row content was visually colliding/overlapping when names and details were longer

### 2) `resources/views/print/leave-form.blade.php`
Expanded the leave-type normalization aliases used by the print form.

From:
- limited normalization for only a few leave-type names
- `Mandatory / Force Leave` could fall through to `Others`

To:
- normalized aliases for:
  - vacation / vacational / vacational leave / annual
  - sick / sick leave
  - force / force leave / mandatory / mandatory leave / mandatory force leave / mandatory/force leave / mandatory/forced leave
  - maternity / paternity
  - special privilege / solo parent / study
  - VAWC variations
  - rehabilitation privilege
  - special leave benefits for women
  - special emergency / calamity leave
  - adoption leave
  - monetization of leave credits
  - terminal leave

Why:
- the print form should check the exact built-in leave type row instead of incorrectly falling into `Others`

## What was not changed
- no routes
- no controllers
- no models
- no database schema
- no deduction logic
- no signatories logic
- no accordion expand/collapse behavior

## Verification
1. Open `leave/requests` and confirm the compact summary rows are taller and no longer overlap.
2. Open a request with a longer employee name/email and confirm the row remains readable.
3. Print a finalized **Mandatory / Force Leave** request and confirm the checkbox is on **Mandatory / Forced Leave**, not `Others`.
4. Also verify other built-in leave types print on their own proper checkbox rows.
