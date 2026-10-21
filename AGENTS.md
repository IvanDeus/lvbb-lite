# lvbb-lite

A Laravel 13 port of `../djbb-lite` (Django). Small bulletin board: three models, three
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

## Layout that differs from a stock skeleton

- `app/Database/Connectors/PostgresConnector.php` — adds `channel_binding` to the DSN; bound as
  `db.connector.pgsql` in `AppServiceProvider::register()`.
- `app/Hashing/DjangoHasher.php` + `config/hashing.php` — verifies Django's `pbkdf2_sha256$…`
  strings imported from `auth_user`, reports them as rehashable, and lets the Eloquent `hashed`
  cast pass them through. First successful login upgrades to bcrypt.
- `app/Console/Commands/ImportDjangoData.php` (`php artisan board:import-django`) — copies users,
  posts and comments from the Django tables, keeping primary keys; idempotent.
- `resources/views/` renders the same URLs and wording as the Django templates. Keep error strings
  verbatim ("This username is already taken.", "Wrong username or password.").

## Behaviour to preserve

- `Post::canBeManagedBy()` is the only permission rule. Failing it **redirects** to the post
  instead of returning 403; `/admin` does return 403 to non-staff (`staff` middleware alias).
- Login lookup is case-sensitive, signup username check is case-insensitive, e-mails are not
  unique — all three match Django.
- Route paths and names mirror `../djbb-lite/config/urls.py`; `/post/new` is declared before
  `/post/{post}`, and comments POST to the post's own URL.

## Commands

```bash
php artisan serve --host=127.0.0.1 --port=7112   # dev server
php artisan test                                 # feature tests against Neon
vendor/bin/pint                                  # fix style (pint --test to check)
php artisan migrate                              # apply Laravel migrations
```

Requires PHP 8.3+ with `pdo_pgsql` (`php -m | grep pgsql`).
