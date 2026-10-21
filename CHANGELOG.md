# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased

First release: the whole application is a port of `djbb-lite` (Django 6.1) to Laravel 13.

### Added

- **A members list, sorted by last activity** — `/members`, the same one plain table `djbb-lite`
  shows its staff: face, name, and `admin` or `member`, whoever wrote most recently at the top.
  `UserController::index` reads the ordering in one grouped query —
  `COALESCE(GREATEST(MAX(posts.created_at), MAX(comments.created_at)), MAX(users.created_at))`,
  which is Django's `Coalesce(Greatest(Max, Max), F('date_joined'))` spelled out: `GREATEST` returns
  NULL only when every argument is, so the join date belongs in the `COALESCE` and not among the
  three, or a writer whose only topic is a year old would never fall below a newcomer.
  `GROUP BY users.id` is what lets `users.*` through beside it, and Postgres accepts the select alias
  as the `ORDER BY` key. The route carries `['auth', 'staff']`, the pair `/admin` already uses, so
  the split refusal — a guest to `/login`, a writer to `403` — cost no new code. `partials/stats.blade.php`
  wraps the users figure in an `<a>` only when `auth()->user()?->is_staff`, which leaves the wording
  byte for byte what every other visitor was already reading.
- **The profile page shows the current avatar at its full size** — the signed-in user's own picture
  was the 24×24 header size, inline before their name; it is now the 100×100 the file actually is,
  in the `avatar-full` circle the member page uses, with `username · e-mail` on its own line below.
  Nothing is scaled up to get there: the four presets are committed at 100×100 and every upload is
  resized to it on the way in.
- **Board counts in the footer** — the same bordered panel `djbb-lite` puts above the credit line on
  every page, with this board's numbers in it: `5 users · 4 comments · 3 topics`. A topic is a post
  here too, so the trio is members,
  replies and topics, counted per request rather than cached. Django's context processor becomes
  `app/View/Composers/BoardCounts.php`, bound to `layouts.app` with `View::composer()` in
  `AppServiceProvider::boot()`: Blade renders `@extends` through the view factory, so one binding
  reaches every page the board answers with — the login and signup pages included — and a view added
  later needs no line of its own. `partials/stats.blade.php` prints the numbers, using
  `Str::plural($word, $count)` where Django had `|pluralize`.
- **A page per member** — `/user/{username}` shows one writer's avatar at its full 100×100 beside
  their name, and every avatar on the board (post author and comment author, on the index and on a
  thread) now links to it. The e-mail line renders only when
  `$request->user()?->is_staff`, so guests and fellow writers see a face and a name.
  `UserController::show` reads `User::where('username', …)->firstOrFail()`, which is
  `get_object_or_404` again: an unknown name answers the framework's 404, not a board page.
- **A 404 that speaks board** — `/post/{id}` with nothing behind it renders
  `resources/views/posts/missing.blade.php` ("That topic does not exist, or it has been deleted")
  with a two-second `<meta http-equiv="refresh">` back to the index and a link for anyone who wants it
  sooner, while the response still carries status 404. `PostController::show` therefore drops implicit
  model binding and returns `response()->view(…, 404)` — the signature widened to `View|Response`.
  Django raised `Http404` and pointed `handler404` at this page; returning it instead is what keeps
  `APP_DEBUG=true` from answering a stack trace on the machine most likely to be running this repo.
  `find((int) $post)` keeps Django's `<int:pk>` behaviour too: an over-long digit string saturates to
  `PHP_INT_MAX`, so a 20-digit id is a missing post rather than a Postgres out-of-range error.
- **Read-only profiles in `/admin`** — `/admin/users` lists every writer's avatar, name and e-mail,
  behind the same `staff` middleware as the rest of `/admin`. Django needed
  `ProfileAdmin` to refuse add, change and delete to force its change page into view mode, and had to
  declare every field in `readonly_fields` for a method column to appear at all; this admin was
  hand-rolled, so the page is a plain table with no form, no buttons and nothing to disable. There is
  no search box either — Django's `search_fields` covered two text fields, and this instance has five
  accounts to search. Accounts are edited nowhere in this app: the seeder and
  `board:import-django` make them, `/profile` changes an avatar and a password.
- **Change password on `/profile`** — `PasswordRequest` checks `old_password` against the stored hash
  with `Hash::check` and runs Django's other validators over the new one: 8 characters minimum, not
  entirely numeric, not too similar to this account's username or address. The last of those is
  `app/Validation/PasswordSimilarity.php`, a faithful re-use of
  `UserAttributeSimilarityValidator`'s arithmetic — word-split pieces plus the whole value, skipped
  when the password dwarfs them, rejected at a 0.7 common-character frequency ratio. The result
  flashes through `session('status')`, which `layouts/app.blade.php` now renders.
  Two shapes differ from Django on purpose. The form posts to its own route
  (`POST /profile/password`) instead of sharing `/profile` and letting the pressed button decide which
  of two bound forms to validate; and nothing has to keep the session alive, because Laravel's session
  holds the user's id where Django keyed the cookie to the password hash and needed
  `update_session_auth_hash` to survive a self-change. The new password is written through the
  `hashed` cast, so it lands as bcrypt and finishes what the first login of an imported pbkdf2 hash
  started. `CommonPasswordValidator` is the one Django check with no counterpart here: Laravel ships no
  blocklist to consult, and a copy of Django's twenty thousand words is not a thing this board should
  grow.

- **Laravel 13 port of the board** — `Post` / `Comment` / `User` models, `PostController`,
  `AuthController`, `AdminController`, `FormRequest` classes mirroring Django's forms, and
  Blade views that reuse the
  Django templates' markup, inline CSS and wording. URLs, route names, validation messages and
  redirects match `djbb-lite` (24 routes), except four that Django has no page for: `/profile` and
  its POST, `POST /profile/password`, and `GET /admin/users`.
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
- **Feature tests** — 93 tests over posts, comments, permissions, auth, hashing, admin, avatars, the
  importer, the member page, the members list, the footer counts, the missing-post page and password
  changes, run against the live Neon database inside a transaction per test. The two importer cases
  skip themselves while `auth_user` is absent from the connected database.

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
- The five features above are a port of djbb-lite's later commits, and one of that app's changelog
  entries was deliberately left behind: its `Deploying to Neon` walkthrough describes `neon.ts` and
  `neon deploy` applying that policy file to a branch. This repo has no `neon.ts` and no Node
  tooling to build one with, so the closest thing it needs — pulling the pooled endpoint out of the
  console — is already in "Setting up a database".
- A class-based `View::composer()` is invoked as `compose()` in Laravel 13, not `handle()`:
  `ManagesEvents::classEventMethodForPrefix()` maps `composing*` to `compose`. Naming it `handle`
  fails every page in the layout with `Call to undefined method` — which is how the footer composer
  was found, since it fires on the index.
- The ported pages were verified three ways: `php artisan test`, HTTP walkthroughs on
  `0.0.0.0:7112` with a cookie jar per identity (guest, `user`, `admin`), and headless-Chrome
  screenshots of the footer panel, a member page as both a guest and a staff visitor, `/profile`
  with the picker and the password form, the missing-post page, and `/admin/users`.
- Verified against Neon Postgres 18.6 on the pooled endpoint with the same `DATABASE_URL` and
  `APP_KEY`/`SECRET_KEY` the Django app uses: `migrate`, `board:import-django` (4 users, 3 posts,
  3 comments), a full HTTP walkthrough of every route (including pbkdf2 login and its bcrypt
  upgrade, non-author redirects, staff overrides, and guest/`/admin` handling), and
  `php artisan test`.
