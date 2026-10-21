# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release: the whole application is a port of `djbb-lite` (Django 6.1) to Laravel 13.

### Added

- **Laravel 13 port of the board** — `Post` / `Comment` / `User` models, `PostController`,
  `AuthController`, `AdminController`, three `FormRequest` classes mirroring Django's forms, and
  Blade views that reuse the
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
- **Avatars for every user** — `users.avatar` is `NOT NULL`: either one of the four committed
  100x100 presets in `public/avatars`, or an `uploads/{id}.{jpeg,png,webp}` file produced by
  `app/Services/AvatarResizer.php` (GD centre-crop to a square, resample to exactly 100x100,
  re-encoded in the source format with `imagealphablending` off and `imagesavealpha` on so PNG
  transparency survives). `AvatarStore` writes it and deletes the file the row stopped pointing at,
  with `User::AVATAR_UPLOAD` guarding which paths may be named at all. Chosen on signup and
  changeable at `/profile` (`ProfileRequest` + `ProfileController`), with the picker markup shared
  by `partials/avatar-picker.blade.php`; avatars render in the header, on the index, under every
  comment and in the three admin tables. Uploaded files live in the docroot, so there is no
  `storage:link` step.
- **Feature tests** — 51 tests over posts, comments, permissions, auth, hashing, admin, avatars and
  the importer, run against the live Neon database inside a transaction per test.

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
- **The board runs on its own Neon project now** (2026-10-21). All six migrations were applied to an
  empty schema and `users` / `posts` / `comments` were copied over with their primary keys, hashes
  and timestamps intact. The Django tables stayed in the database that was retired, so
  `board:import-django` reports them missing on this connection and `ImportDjangoDataTest` skips.

### Notes

- The Laravel tables were first written into a schema shared with djbb-lite's. `config/database.php`
  lists the 12 Django tables under `dont_drop` so `migrate:fresh` cannot take them with it; no
  destructive database command is ever appropriate against a live board.
- `2026_10_22_000000_add_avatar_to_users_table` is the first migration to alter a column on the live
  shared schema: it adds `users.avatar` nullable, backfills `id % 4` across the four presets, then
  re-declares it with `->change()` so Postgres sets `NOT NULL`. Laravel 13 does that natively — no
  `doctrine/dbal` — and the three statements share one transaction, which is exactly what the
  emulated prepares exist for. `users` is Laravel's own table; Django reads `auth_user`, so the
  Django side is untouched.
- `timestamp()` columns are second precision, so imported `created_at` values may round by up to
  one second relative to Django's `timestamptz(6)`.
- Verified against Neon Postgres 18.6 on the pooled endpoint with the same `DATABASE_URL` and
  `APP_KEY`/`SECRET_KEY` the Django app uses: `migrate`, `board:import-django` (4 users, 3 posts,
  3 comments), a full HTTP walkthrough of every route (including pbkdf2 login and its bcrypt
  upgrade, non-author redirects, staff overrides, and guest/`/admin` handling), and
  `php artisan test`.
