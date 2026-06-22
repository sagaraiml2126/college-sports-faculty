# College Sports Faculty Portal

A PHP + MySQL web application for **YSPM's Yashoda Technical Campus, Satara —
Faculty of Sports**. Manages student registrations, sport selections,
achievements, jersey requests, and inter-college team rosters across
multiple departments (Engineering, Polytechnic, Pharmacy, D.Pharm,
Architecture, Management).

> **Stack:** PHP 7.4+ (8.x recommended) · MySQL 5.7+ / MariaDB 10.3+ · plain HTML/CSS · no framework
> **Default users:** see [INSTALL.md §2.8](INSTALL.md#28-sign-in) — change these in production.

## What's in this repo

| Path | What it is |
|---|---|
| `index.php` | Public homepage (hero, ticker, notices, achievements from DB) |
| `faculty-login.php` / `faculty-select.php` | Auth + department picker |
| `student-*.php` | Student-facing registration, login, dashboard, profile |
| `student-search.php` / `student-profile.php` | Faculty-scoped student CRUD |
| `forgot-password.php` / `forgot_process.php` / `reset_password.php` | Password reset |
| `admin/` | Super-admin pages (dashboard, faculty/student management, exports) |
| `api/` | JSON endpoints (department requirements, document status) |
| `includes/` | `bootstrap.php` (entry), `db.php` (mysqli helpers), `helpers.php`, `csrf.php`, `auth.php`, `upload.php`, `seed_check.php` |
| `sql/` | `schema.sql`, `seed.sql`, all `migration-v*.sql`, `seed.ready.sql` (generated) |
| `css/`, `images/`, `uploads/` | Static assets and writable user content |
| `scripts/init_database.php` | One-shot CLI initializer (used by Railway) |
| `.htaccess` | Denies direct access to `/includes` and `/sql` |

## Installation

See **[INSTALL.md](INSTALL.md)** for the full guide. Quick start for XAMPP:

```bash
# 1. Copy project into web root
cp -r . C:\xampp\htdocs\college-sports-faculty\

# 2. Start Apache + MySQL in the XAMPP control panel

# 3. Create the schema and generate seed hashes
"C:\xampp\mysql\bin\mysql.exe" -u root < sql/schema.sql
php sql/generate-hashes.php
"C:\xampp\mysql\bin\mysql.exe" -u root csf_portal < sql/seed.ready.sql

# 4. Apply any post-seed migrations (idempotent, safe to re-run)
for f in sql/migration-v*.sql; do
  "C:\xampp\mysql\bin\mysql.exe" -u root csf_portal < "$f"
done

# 5. Visit http://localhost/college-sports-faculty/
```

Default login: `admin` / `Admin@123` (super-admin), or
`eng_faculty` / `poly_faculty` / `pharm_faculty` with password `Faculty@123`.
**Change all four before going to production.**

## Deployment

Three supported targets, all documented in [INSTALL.md](INSTALL.md):

- **XAMPP / local dev** — section 2
- **cPanel / shared hosting** — section 3
- **Railway** — see [RAILWAY.md](RAILWAY.md); the `scripts/init_database.php` is
  the auto-init entrypoint.

For cPanel, the deployment bundle lives at
`build/college-sports-faculty-godaddy/` (kept locally; not in this repo
because it's a generated artifact, not source).

## Database

Single database `csf_portal`. Tables:

- `departments`, `faculty`, `faculty_departments` — auth + dept scoping
- `students` — student records (one row per student per department)
- `student_documents`, `dept_document_requirements` — file uploads
- `dept_game_catalog`, `student_selected_games` — game picker (polytechnic + D.Pharm)
- `notices`, `achievements` — homepage content
- `hero_settings`, `college_settings` — site config
- `login_attempts`, `password_resets` — security
- `provisional_entries`, `final_teams`, `jersey_forms`, `jersey_requests` — team + jersey flows
- `contact_messages` — contact form

Schema lives at `sql/schema.sql`; incremental changes are in
`sql/migration-v*.sql` and must be applied in order on existing installs.

## Security

- **CSRF** on every form (see `includes/csrf.php`)
- **Prepared statements** everywhere (mysqli helpers in `includes/db.php`)
- **bcrypt** password hashing (cost 12)
- **Lockout** after 5 failed logins per 15 minutes (`login_attempts` table)
- **Open-redirect protection** on all `redirect()` calls
- **File-upload validation**: MIME sniff, size cap, safe filename, bucket whitelist
- **Seed-user drift self-check** on every admin/faculty request — surfaces
  re-imports of `seed.ready.sql` that would clobber rotated passwords
  (see [INSTALL.md §2.9](INSTALL.md#29-seed-user-drift-self-check-built-in))

## License

Internal tool for YSPM's Yashoda Technical Campus Sports Faculty. No warranty.
