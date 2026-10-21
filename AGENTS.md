# lvbb-lite

A Laravel 13 port of `../djbb-lite` (Django). Small bulletin board: three models, four
controllers, Blade views with inline CSS. No starter kit, no Node build step, no Tailwind.

## Non-negotiables

- **Postgres only.** `config/database.php` defines exactly one connection (`pgsql`) and throws at
  boot without a `DATABASE_URL`. Never reintroduce SQLite, and never add a `DB_CONNECTION` switch.
- **The database is shared with the live djbb-lite app.** Never run `migrate:fresh`, `migrate:reset`,
  or `db:wipe`. The Django tables are listed under `dont_drop` as a backstop, not as permission.
- **Tests roll back, they do not migrate.** `tests/TestCase.php` uses `DatabaseTransactions`.
  Do not switch it to `RefreshDatabase`.
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
- `resources/views/` renders the same URLs and wording as the Django templates. Keep error strings
  verbatim ("This username is already taken.", "Wrong username or password.").

## Behaviour to preserve

- `Post::canBeManagedBy()` is the only permission rule. Failing it **redirects** to the post
  instead of returning 403; `/admin` does return 403 to non-staff (`staff` middleware alias).
- Login lookup is case-sensitive, signup username check is case-insensitive, e-mails are not
  unique — all three match Django.
- Route paths and names mirror `../djbb-lite/config/urls.py`; `/post/new` is declared before
  `/post/{post}`, and comments POST to the post's own URL. `/profile` is the one pair of routes with
  no Django counterpart.
- An avatar is mandatory but never forced twice: signup requires a preset pick, `/profile` accepts an
  empty submission and keeps whatever is stored, because the whitelist only names presets and an
  uploaded avatar would otherwise be impossible to keep.
- `User::presetForId()` (`id % 4` over the four presets) is used by the backfill in
  `2026_10_22_000000_add_avatar_to_users_table`, whose SQL spells the same rotation, and by
  `board:import-django`. Change one and the other two have to move with it.

## Commands

```bash
php artisan serve --host=127.0.0.1 --port=7112   # dev server
php artisan test                                 # feature tests against Neon
vendor/bin/pint                                  # fix style (pint --test to check)
php artisan migrate                              # apply Laravel migrations
```

Requires PHP 8.3+ with `pdo_pgsql` (`php -m | grep pgsql`) and `gd` for the avatar resize
(`php -m | grep -i gd`).
