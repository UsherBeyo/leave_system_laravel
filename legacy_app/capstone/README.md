## Leave Management Enhancements

This project now supports a more complete leave policy:

* **Multiple leave types** – defined in a new `leave_types` table with configurable rules (deduct balance, auto-approve, max per year, etc.). Built-in types include Vacation, Sick, Emergency and Special; admins can add/edit types in the UI.
* **Type-aware balance tracking** – balances for annual/sick/force (and a legacy generic column) are stored on the employee record and each leave request now carries snapshots for auditing.
* **Auto-approval rules** – the system evaluates criteria such as short sick leaves, emergency leave, or the `auto_approve` flag on a type and approves requests instantly. Negative balances are automatically rejected.
* **Conflict detection** – overlapping requests (approved or pending) are blocked before insertion to prevent double-booking.
* **Holiday & weekend exclusion** – leave calculations ignore weekends and entries in the `holidays` table when computing deductible days.
* **Monthly accrual** – employees earn **1.25 days of annual leave per month**. Run `php scripts/accrue.php` (via cron) to apply; accrual history is recorded in `accrual_history`.
* **Force leave quota** – every month each employee receives **5 force leave days** which are tracked separately; unused days are reset on accrual.
* **Audit tables** – `accrual_history` and `leave_balance_logs` record every change so HR has a full trail.
* **Email notifications** – leave events trigger SMTP messages via `services/Mail.php`.
* **Dashboard analytics** – shows most absent employee and monthly trends; managers/HR see charts powered by Chart.js.
* **Export** – CSV/Excel/PDF export options are available from the reports page (requires PhpSpreadsheet/TCPDF to enable).
### Database migration

A helper script has been added at `scripts/migration.php` that will add the new columns and copy any existing `leave_balance` values into `annual_balance`.

Use this script to also create tables such as `holidays` needed for the calendar feature.

#### Adding additional roles

The `users` table stores a `role` field; existing roles are `admin`, `manager`, and `employee`.  You can insert other roles (e.g. `hr`) with a simple SQL statement:

```sql
INSERT INTO users (email, password, role, is_active) \
VALUES ('hr@company.com', '<hash>', 'hr', 1);
```

`<hash>` should be generated with `password_hash('yourpassword', PASSWORD_DEFAULT)` (you can run a small PHP snippet).  After adding the user, create a corresponding row in `employees`:

```sql
INSERT INTO employees (user_id, first_name, last_name, department, manager_id, annual_balance, sick_balance, force_balance)
VALUES (LAST_INSERT_ID(), 'HR','User','Human Resources', NULL, 0, 0, 5);
```

Managers and HR now have access to approval screens and the calendar.

```sh
php scripts/migration.php
```

Run this once after pulling the changes.

### Cron job / monthly update

Schedule the following command to run on the first day of each month:

```sh
php /path/to/capstone/scripts/accrue.php
```

It increments annual balances and resets the force leave quota. The script also prints a warning if any employees still had leftover force days.

### UI changes

* Dashboard now displays all three balances when employees log in.
* Employees can view their request history and change password from their dashboard.
* Apply‑for‑leave form shows current balances and allows choosing a type (force enforced).
* Managers and admins can approve/reject requests; admins have a dedicated listing with inline editing.
* Admin panel allows selecting role when creating a user, editing employee details/balances, and displays the three balances in the employee list.
* Holiday management page permits creating named holidays; these and approved leaves are drawn on a basic calendar view.
* A calendar page shows approved leaves and holidays month‑by‑month; navigation lets you move between months.
* Statistics page gives counts of employees by department and role, plus total headcount.
* Change‑password form for all logged‑in users.

### Other notes

* `LeaveController` now handles leave submission as well as approval.
* The `Leave` model encapsulates type‑specific balance logic.
* Session now stores `emp_id` for the currently logged in employee.

---

Please refer to the code comments for further details.
