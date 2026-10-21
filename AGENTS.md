# lvbb-lite

A Laravel 13 port of `../djbb-lite` (Django). Small bulletin board: three models, five
controllers, Blade views with inline CSS. No starter kit, no Node build step, no Tailwind.

## Non-negotiables

- **Postgres only.** `config/database.php` defines exactly one connection (`pgsql`) and throws at
  boot without a `DATABASE_URL`. Never reintroduce SQLite, and never add a `DB_CONNECTION` switch.
- **This database holds live board data.** Never run `migrate:fresh`, `migrate:reset`, or
  `db:wipe`. Since 2026-10-21 the connection points at a Neon project carrying only Laravel's
  tables — djbb-lite stayed on the instance that was replaced — so `board:import-django` reports the
  Django tables missing and `ImportDjangoDataTest` skips itself. The 12 tables stay listed under
  `dont_drop` as a backstop for any database where they do coexist.
- **Tests roll back, they do not migrate.** `tests/TestCase.php` uses `DatabaseTransactions`.
  Do not switch it to `RefreshDatabase`.
- **Feature tests must survive a populated database.** They run against the live board, so never assert
  an absolute count, a "nothing here yet" message, or a URL with a host in it — a test request's host
  is `http://localhost` while the running server's is not. Assert the *change* a request made, render
  a partial directly to check its wording, and build HTML needles from `route(...)`.
- **Keep `PDO::ATTR_EMULATE_PREPARES => true`** in the pgsql connection. Neon's pooled endpoint is
  PgBouncer in transaction mode; with native prepared statements the second statement inside a
  transaction fails with `SQLSTATE[25P02]` and migrations break.
- **`DATABASE_URL` lives in `.env`, and `.env.local` must not exist.** Laravel loads
  `.env.{APP_ENV}` *instead of* `.env`, and `artisan serve` inherits `APP_ENV=local`, so a
  `.env.local` copied from djbb-lite hides `APP_KEY` and every request 500s.
- **`users.avatar` is `NOT NULL` and holds only two shapes** — one of the four preset basenames in
  `User::AVATAR_PRESETS`, or `uploads/{id}.{jpeg,png,webp}`. Anything else must be rejected at the
  form; `User::AVATAR_UPLOAD` is what gates deletion, so a stored value can never name a preset, a
  traversal path, or another user's file. Every insert needs the column (factory, seeder, importer).
- **Avatar tests must `Storage::fake('avatars')`.** That disk is rooted at `public/avatars`, the
  docroot, so a test that skips the fake writes real files into the served folder.
- **The footer counts come from the layout composer.** `View::composer('layouts.app', BoardCounts::class)`
  in `AppServiceProvider::boot()` is what puts the `users · comments · topics` panel above the credit
  line on every page; a new view extends the layout and gets it for free. Do not pass the numbers from
  controllers, and do not cache them. A class-based composer is called as **`compose()`**, not
  `handle()` — get that wrong and every page in the layout 500s.

## Layout that differs from a stock skeleton

- `app/Database/Connectors/PostgresConnector.php` — adds `channel_binding` to the DSN; bound as
  `db.connector.pgsql` in `AppServiceProvider::register()`.
- `app/Hashing/DjangoHasher.php` + `config/hashing.php` — verifies Django's `pbkdf2_sha256$…`
  strings imported from `auth_user`, reports them as rehashable, and lets the Eloquent `hashed`
  cast pass them through. First successful login upgrades to bcrypt.
- `app/Console/Commands/ImportDjangoData.php` (`php artisan board:import-django`) — copies users,
  posts and comments from the Django tables, keeping primary keys; idempotent. `copyRows()` takes a
  `$computed` map for columns Django does not have (`users.avatar`).
- `app/Services/AvatarResizer.php` — hand-rolled GD, no image package: centre-crop to a square,
  resample to 100x100, re-encode as the source type. `imagealphablending(false)` plus
  `imagesavealpha(true)` on the destination is what keeps PNG transparency.
- `app/Services/AvatarStore.php` — writes through `Storage::disk('avatars')` (`config/filesystems.php`,
  rooted at `public/avatars`, no `storage:link`) and removes the file the row stopped pointing at.
  Handles must stay disk-relative: Flysystem prefixes an absolute path onto the disk root.
- `app/Validation/PasswordSimilarity.php` — Django's `UserAttributeSimilarityValidator`, arithmetic and
  all: each of `username` and `email` split on non-word characters plus the whole value, a piece the
  password dwarfs ignored, the rest rejected at a common-character frequency of 0.7 or more. Do not
  simplify it to `str_contains` — that rejects passwords Django accepts.
- `app/Http/Requests/PasswordRequest.php` — three of Django's four password validators, the messages
  verbatim. `CommonPasswordValidator` is deliberately unported (no blocklist in Laravel, none to add);
  `min:8`, `ctype_digit` and the similarity check are what the form enforces and the page hint says no
  more than that.
- `resources/views/` renders the same URLs and wording as the Django templates. Keep error strings
  verbatim ("This username is already taken.", "Wrong username or password.").

## Behaviour to preserve

- `Post::canBeManagedBy()` is the only permission rule. Failing it **redirects** to the post
  instead of returning 403; `/admin` does return 403 to non-staff (`staff` middleware alias).
- Login lookup is case-sensitive, signup username check is case-insensitive, e-mails are not
  unique — all three match Django.
- Route paths and names mirror `../djbb-lite/config/urls.py`; `/post/new` is declared before
  `/post/{post}`, and comments POST to the post's own URL. Three paths have no Django counterpart:
  `/profile` and its POST, `POST /profile/password` (Django posts both profile forms to one URL and
  reads the pressed button; separate routes do the same job without that step), and `GET /admin/users`
  (Django's contrib admin generates it). `/user/{username}` and `/members` match Django again.
- `PostController::show` **returns** `posts/missing` with status 404 instead of `findOrFail`.
  `APP_DEBUG=true` makes Laravel answer a `NotFoundHttpException` with its own technical page, which is
  the page the board is trying not to show, and a 200 would be a lie to `curl`. Keep the `(int)` cast:
  it saturates a 20-digit id at `PHP_INT_MAX`, so oversized ids stay missing posts rather than
  Postgres out-of-range errors. `/user/{username}` is the opposite case on purpose — `firstOrFail()`,
  because there is no missing-member template.
- The e-mail line on a member page renders from `$request->user()?->is_staff` and nothing else. Avatars
  link there from the index and from a thread; that is the only place a board reader can reach a
  profile that is not their own.
- `GET /members` is the staff roll-call, gated by `['auth', 'staff']`: a guest goes to the login page
  and a logged-in writer gets 403. `UserController::index` keeps Django's
  `COALESCE(GREATEST(MAX, MAX), MAX(users.created_at))` — the join date sits in the `COALESCE`, not
  inside `GREATEST`, or an account created after its own posts is placed by its signup and activity
  stops deciding the order. `select('users.*')` next to `groupBy('users.id')` is what lets the
  non-keyed columns through Postgres. Only a staff footer links the page; the partial prints the plain
  figure for everyone else.
- A password change writes through `forceFill` so the `hashed` cast runs and the result is bcrypt, and
  needs no `update_session_auth_hash` equivalent: Laravel's session holds the user's id, not a digest
  of the hash. Do not add one, and do not merge the two `/profile` POSTs.
- An avatar is mandatory but never forced twice: signup requires a preset pick, `/profile` accepts an
  empty submission and keeps whatever is stored, because the whitelist only names presets and an
  uploaded avatar would otherwise be impossible to keep.
- `User::presetForId()` (`id % 4` over the four presets) is used by the backfill in
  `2026_10_22_000000_add_avatar_to_users_table`, whose SQL spells the same rotation, and by
  `board:import-django`. Change one and the other two have to move with it.

## Commands

```bash
php artisan serve --host=127.0.0.1 --port=7112   # dev server (0.0.0.0 to answer on every interface)
php artisan test                                 # 93 feature tests against Neon
vendor/bin/pint                                  # fix style (pint --test to check)
php artisan migrate                              # apply Laravel migrations
```

Requires PHP 8.3+ with `pdo_pgsql` (`php -m | grep pgsql`) and `gd` for the avatar resize
(`php -m | grep -i gd`).
