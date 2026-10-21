# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release: the whole application is a port of `djbb-lite` (Django 6.1) to Laravel 13.

### Added

- **Laravel 13 port of the board** — `Post` / `Comment` / `User` models, `PostController`,
  `AuthController`, `AdminController`, three `FormRequest` classes, and Blade views that reuse the
  Django templates' markup, inline CSS and wording. URLs, route names, validation messages and
  redirects match `djbb-lite` (19 routes).
- **Ownership behaves like Django** — `Post::canBeManagedBy()` gates edit/delete, and failing it
  redirects to the post instead of answering 403. `/admin` still 403s non-staff through the
  `staff` middleware alias in `bootstrap/app.php`.
- **Hand-rolled `/admin`** — post list with comment counts, per-post comment list, and comment list,
  each with a delete button. Deliberately replaces Django's contrib admin (no Filament, no package).
- **Django password compatibility** — `app/Hashing/DjangoHasher.php` registered as the `django`
  hash driver verifies `pbkdf2_sha256$iterations$salt$base64` and the older `pbkdf2_sha1` with
  `hash_pbkdf2` + `hash_equals`, keeps them out of the Eloquent `hashed` cast via
  `verifyConfiguration()`, and upgrades to bcrypt on the first successful login.
- **`board:import-django`** — copies `auth_user`, `board_post`, `board_comment` into the Laravel
  tables, preserving primary keys and `date_joined` → `created_at`, re-syncing sequences with
  `setval`, and skipping rows that already exist so it can be re-run safely.
- **`channel_binding` support for Postgres** — `app/Database/Connectors/PostgresConnector.php`
  appends the parameter to the DSN (Laravel would drop it) and is bound as `db.connector.pgsql`.
- **Feature tests** — 32 tests over posts, comments, permissions, auth, hashing, admin and the
  importer, run against the live Neon database inside a transaction per test.

### Changed

- **Postgres is the only database.** `config/database.php` declares a single `pgsql` connection,
  reads `DATABASE_URL` (Neon pooled endpoint) through Laravel's `ConfigurationUrlParser`, and
  throws at boot when no connection string is present. All SQLite configuration is gone.
- **Prepared statements are emulated client-side** (`PDO::ATTR_EMULATE_PREPARES => true`), because
  Neon's pooled endpoint runs PgBouncer in transaction mode and breaks Laravel's native prepares
  mid-transaction with `SQLSTATE[25P02]`.
- **Signup keeps Django's asymmetry** — login lookups are case-sensitive while the username check
  on signup is case-insensitive (`lower(username) = lower(?)`, not `ilike`, so `%` and `_` in a
  username stay literal).
- **`users.email` lost its unique index** — the initial schema added one by reflex; Django never
  enforced unique e-mails, so `2026_10_21_010000_drop_email_unique_from_users_table` removes it and
  the form stops rejecting a shared address.
- Front-end build tooling removed (`package.json`, `vite.config.js`, `resources/css`, `resources/js`);
  the CSS was inline in the Django template and stays inline.

### Notes

- The Laravel tables live in the same schema as djbb-lite's. `config/database.php` lists the 12
  Django tables under `dont_drop` so `migrate:fresh` cannot take them with it; no destructive
  database command is ever appropriate against this connection.
- `timestamp()` columns are second precision, so imported `created_at` values may round by up to
  one second relative to Django's `timestamptz(6)`.
- Verified against Neon Postgres 18.6 on the pooled endpoint with the same `DATABASE_URL` and
  `APP_KEY`/`SECRET_KEY` the Django app uses: `migrate`, `board:import-django` (4 users, 3 posts,
  3 comments), a full HTTP walkthrough of every route (including pbkdf2 login and its bcrypt
  upgrade, non-author redirects, staff overrides, and guest/`/admin` handling), and
  `php artisan test`.
