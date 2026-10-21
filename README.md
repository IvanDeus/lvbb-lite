# LVBB-lite: Laravel Bulletin Board

The djbb-lite board, ported to Laravel. Read it as a working reference for Eloquent models,
thin controllers, `FormRequest` validation, Blade views, and session auth — with nothing extra
in the way. No starter kit, no Bootstrap, no Vite, no Tailwind: the CSS is inline in the layout,
exactly as it was in `base.html`.

**Postgres is the only supported database.** There is no SQLite fallback anywhere in the config.

![PHP](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-13.38-red)

## Features

- **Posts** — title + body, newest first, body excerpted on the index like `truncatechars:240`.
- **Comments** — listed under each post, created by logged-in users.
- **Author-only editing** — you can edit or delete your own posts.
- **Staff override** — staff users can edit or delete *anyone's* post.
- **`/admin`** — a hand-rolled list of posts and comments with delete buttons, staff only.
- **Anonymous read access** — browsing needs no account; writing does.
- **Self-registration** — username, e-mail, password (min 6 chars), auto-login afterwards.
- **Avatars** — mandatory for every user: one of the four presets in `public/avatars`, or your own
  JPG/PNG/WebP, centre-cropped to 100&times;100 by GD as it is saved. `/profile` changes it later.

## Requirements

- PHP 8.3+ with `pdo_pgsql` (`apt install php8.3-pgsql`)
- PHP's GD extension for the avatar resize (`php -m | grep -i gd`); JPEG, PNG and WebP all need to
  be compiled in, which the stock `php8.3-gd` package does
- Composer

```bash
composer install
```

### Upload limits

`upload_max_filesize` is 2M on this PHP, which is what `max:2048` in the avatar rules mirrors. A
bigger file never reaches the validator: PHP drops it, and the form answers
"The avatar file failed to upload." A body over `post_max_size` (8M) arrives without the CSRF token,
so the request fails as a 419 page rather than a field error — if avatars ever need to accept bigger
files, raise both directives and keep the `max:` rule in step with `upload_max_filesize`.

### Environment

Copy `.env.example` to `.env` and fill in two values:

```bash
APP_KEY=                     # php artisan key:generate
DATABASE_URL=postgresql://user:password@ep-xxx-pooler.c-1.aws.neon.tech/neondb?sslmode=require&channel_binding=require
```

`config/database.php` throws at boot if neither `DATABASE_URL` nor `DB_HOST` is set, so a
missing connection string is an immediate error instead of a mysterious 500.

> **Do not create `.env.local` here.** Laravel loads `.env.{APP_ENV}` *instead of* `.env` when
> `APP_ENV` is in the process environment — and `artisan serve` inherits `APP_ENV=local` from
> your shell. A `.env.local` copied over from djbb-lite would hide `APP_KEY` and every request
> would fail with "No application encryption key has been specified". `DATABASE_URL` belongs in
> `.env`, next to the key.

`ConfigurationUrlParser` splits the URL into host / port / database / user / password and merges
its query parameters into the connection config, so `sslmode` and `channel_binding` arrive from
the URL itself; `DB_SSLMODE` / `DB_CHANNEL_BINDING` are the fallbacks for the split-variable form.

#### Getting the DB URL from the Neon console

The Neon CLI signs in through a browser OAuth callback on `127.0.0.1`, which never arrives on a
headless server — `neon login` times out there. Take the string from the web console instead:

1. Sign in at <https://console.neon.tech>.
2. Open your project → **Connection Details**.
3. Copy the **pooled** endpoint (the one ending in `-pooler…`).
4. Paste it into `.env` as `DATABASE_URL=`; append `&channel_binding=require` if it is missing.

### Three Postgres details worth knowing

1. **The pooled endpoint runs PgBouncer in transaction mode**, which does not keep PDO's
   server-side prepared statements on the same backend across a transaction: the first statement
   reports success and the next fails with `SQLSTATE[25P02] current transaction is aborted`.
   `config/database.php` therefore passes `PDO::ATTR_EMULATE_PREPARES => true`, so Laravel sends
   plain SQL. This is why `php artisan migrate` fails against the pooled endpoint the moment the
   option is removed.
2. **`channel_binding` is not a Laravel connection key.** The stock `PostgresConnector` would drop
   it silently, leaving SCRAM channel binding off. `app/Database/Connectors/PostgresConnector.php`
   adds it to the DSN and is bound in `AppServiceProvider` under `db.connector.pgsql`.
3. **This database is shared with djbb-lite.** `config/database.php` lists the 12 Django tables
   under `dont_drop`, so `migrate:fresh` and `db:wipe` skip them instead of deleting someone
   else's board. Neither command is ever the right thing to run here anyway.

## Setting up a database

```bash
# 1. create the Laravel tables (users, sessions, posts, comments, cache, jobs, migrations)
php artisan migrate

# 2. bring in the existing Django rows, keeping primary keys
php artisan board:import-django

# 3. or, on an empty branch, seed the two test accounts
php artisan db:seed
```

`board:import-django` copies `auth_user` → `users`, `board_post` → `posts`,
`board_comment` → `comments`, carries the Django primary keys over so `author_id` / `post_id`
still line up, re-syncs the sequences with `setval`, and skips ids that already exist — so it is
safe to re-run and never overwrites edits made in Laravel.

## Quick start

```bash
cp .env.example .env      # set DATABASE_URL, then: php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=7112
```

Open <http://127.0.0.1:7112/>.

## Test accounts

| Username | Password  | Type    | Can                                               |
| -------- | --------- | ------- | ------------------------------------------------- |
| `admin`  | `pass123` | staff   | Everything, plus `/admin` and other users' posts  |
| `user`   | `zxc123`  | regular | Own posts only, no `/admin`                       |

These are seeded for manual testing only (`php artisan db:seed`, which skips accounts that
already exist). They are weak by design — do not run this instance somewhere reachable from the
internet.

## Routes

Paths and route names match djbb-lite; the POST twin of a GET route gets a `_store` suffix.
`/profile` is the only pair with no Django equivalent — the port needed somewhere to change an
avatar, and the board has no other page for a user's own data.

| Method     | Path                        | Name                  | Controller action     | Notes                    |
| ---------- | --------------------------- | --------------------- | --------------------- | ------------------------ |
| `GET`      | `/`                         | `post_list`           | `PostController@index` | public                   |
| `GET`      | `/post/{post}`              | `post_detail`         | `@show`                | public                 |
| `POST`     | `/post/{post}`              | `comment_store`       | `@storeComment`        | login required           |
| `GET`      | `/post/new`                 | `post_create`         | `@create`              | login required           |
| `POST`     | `/post/new`                 | `post_store`          | `@store`               | login required           |
| `GET`      | `/post/{post}/edit`         | `post_update`         | `@edit`                | author or staff          |
| `POST`     | `/post/{post}/edit`         | `post_update_store`   | `@update`              | author or staff          |
| `GET`      | `/post/{post}/delete`       | `post_delete`         | `@confirmDelete`       | author or staff          |
| `POST`     | `/post/{post}/delete`       | `post_delete_store`   | `@destroy`             | author or staff          |
| `GET`      | `/login`                    | `login`               | `AuthController@showLogin` |                        |
| `POST`     | `/login`                    | `login_store`         | `@login`               |                          |
| `GET`      | `/signup`                   | `signup`              | `@showSignup`          |                          |
| `POST`     | `/signup`                   | `signup_store`        | `@signup`              | avatar required          |
| `POST`     | `/logout`                   | `logout`              | `@logout`              | POST only, as in Django  |
| `GET`      | `/profile`                  | `profile`             | `ProfileController@show` | login required         |
| `POST`     | `/profile`                  | `profile_store`       | `@update`              | login required           |
| `GET`      | `/admin`                    | `admin_posts`         | `AdminController@posts` | staff                  |
| `GET`      | `/admin/comments`           | `admin_comments`      | `@comments`            | staff                    |
| `GET`      | `/admin/post/{post}`        | `admin_post`          | `@post`                | staff                    |
| `POST`     | `/admin/post/{post}/delete` | `admin_post_delete`   | `@destroyPost`         | staff                    |
| `POST`     | `/admin/comment/{c}/delete` | `admin_comment_delete` | `@destroyComment`     | staff                    |
| `GET`      | `/up`                       | —                     | framework health check |                          |

Comments are POSTed to the post's own URL, as Django did (`post_detail` handled both verbs);
`/post/new` is declared before `/post/{post}` so the literal segment wins. `{post}` and `{comment}`
are constrained with `whereNumber` to keep Django's `<int:pk>` matching.

## Permissions model

One method decides everything about a post:

```php
// app/Models/Post.php
public function canBeManagedBy(?User $user): bool
{
    return $user !== null && ($user->is_staff || $this->author_id === $user->id);
}
```

A user who fails the check is **redirected to the post rather than shown a 403**, so the edit and
delete URLs do not leak which post IDs exist versus which are simply yours. `/admin` is the
opposite case: `EnsureUserIsStaff` (aliased as `staff` in `bootstrap/app.php`) answers `403` to a
logged-in non-staff user and redirects a guest to `/login` — the same as Django's admin.

Django's `post_detail` accepted comment POSTs from anonymous visitors and relied on the template
to hide the form; here `comment_store` sits behind the `auth` middleware, which is the same
outcome without the trap.

## Living with the Django app

The two apps share one schema, each with its own tables, and both are live at the same time.

- **Passwords.** Imported rows carry `pbkdf2_sha256$iterations$salt$base64` verbatim.
  `app/Hashing/DjangoHasher.php` is registered as the `django` hash driver (`config/hashing.php`
  defaults to it) and extends `BcryptHasher`: it verifies a Django string with `hash_pbkdf2` +
  `hash_equals`, reports `needsRehash()` for it, and lets `verifyConfiguration()` pass it through
  the Eloquent `hashed` cast instead of double-hashing it. The first successful login replaces it
  with bcrypt.
  That upgrade is invisible to Django as long as `bcrypt` is installed and `BCryptSHA256PasswordHasher`
  stays in `PASSWORD_HASHERS` (it is the default first entry) — Django will simply re-hash it back
  on the next login. Accounts that never log in keep their pbkdf2 string.
- **Login is case-sensitive** (`user` ≠ `USER`), matching Django's `get_by_natural_key`. Signup is
  **case-insensitive**: a name that differs only in letter case from an existing one is rejected
  with "This username is already taken.", like `UserCreationForm`.
- **E-mails are not unique**, because Django never enforced that. The initial migration added the
  constraint by reflex; `2026_10_21_010000_drop_email_unique_from_users_table` takes it back off.
- **Timestamps lose sub-second precision.** Laravel's `timestamp()` columns are `timestamp(0)`
  while Django's are `timestamptz(6)`. Nothing in either UI shows more than minutes, but imported
  rows can differ by up to one second.
- `users` has no `name`, `email_verified_at`, or `password_reset_tokens` column — the port does not
  use them, and there is no password reset in either app.
- **`users.avatar` is Laravel-only.** `auth_user` has no avatar field, so `board:import-django`
  derives one from the primary key (`User::presetForId()`, the same `id % 4` rotation the migration
  used). Django keeps reading `auth_user` untouched; the two apps never write the same column.

## Project layout

```
.
├── app/
│   ├── Console/Commands/ImportDjangoData.php   # board:import-django
│   ├── Database/Connectors/PostgresConnector.php  # channel_binding into the DSN
│   ├── Hashing/DjangoHasher.php                # pbkdf2_sha256/sha1 + bcrypt upgrade
│   ├── Http/
│   │   ├── Controllers/      # PostController, AuthController, AdminController, ProfileController
│   │   ├── Middleware/       # EnsureUserIsStaff (alias: staff)
│   │   └── Requests/         # PostRequest, CommentRequest, RegisterRequest, ProfileRequest
│   │                         # and Concerns/GathersAvatar, the shared avatar rules
│   ├── Models/               # Post, Comment, User
│   └── Services/             # AvatarResizer (GD crop + resample), AvatarStore (write + cleanup)
├── bootstrap/app.php         # routing, staff alias, JSON exception rules
├── config/
│   ├── database.php          # pgsql only, url parsing, dont_drop, emulated prepares
│   ├── filesystems.php       # avatars disk, rooted at public/avatars
│   └── hashing.php           # driver=django
├── database/
│   ├── factories/            # User, Post, Comment
│   ├── migrations/           # users/sessions, posts/comments, drop email unique, avatar to users
│   └── seeders/              # admin + user test accounts
├── public/avatars/           # four 100x100 presets, plus uploads/ (gitignored)
├── resources/views/
│   ├── layouts/app.blade.php # header, nav, inline CSS
│   ├── posts/                # index, show, form, confirm_delete
│   ├── auth/                 # login, signup
│   ├── admin/                # posts, post, comments
│   ├── partials/             # avatar-picker, shared by signup and profile
│   └── profile.blade.php
├── routes/web.php
└── tests/Feature/            # posts, auth, admin, hashing, import, avatars
```

`Post` and `Comment` set `const UPDATED_AT = null` (Django had only `created_at`), `comments()`
orders by `created_at`, and `password` / `remember_token` stay hidden on the `User` model.

## Tests

```bash
php artisan test        # 51 feature tests
vendor/bin/pint --test  # style
```

`tests/TestCase.php` applies `DatabaseTransactions`, so each test rolls back. **Never use
`RefreshDatabase` in this project** — the suite runs against the same Neon database as the app and
that database holds djbb-lite's tables, so migrating or wiping would destroy real data. The
`phpunit.xml` env block keeps sessions, cache and mail in-memory; the database connection still
comes from `.env`.

## Production notes

Nothing here is production-shaped, on purpose: `APP_DEBUG=true`, the `log` mailer, seeded weak
accounts, and `artisan serve` bound to loopback. The two things that would actually bite:

- Set `APP_KEY` freshly (`php artisan key:generate`); the key that shipped in djbb-lite's repo is
  public and is reused here only because this is the same toy instance.
- Put a real server in front (`php artisan serve` is a development tool), keep `sslmode=require`
  and `channel_binding=require` in the connection string, and re-check `SESSION_COOKIE_SECURE` /
  `APP_URL` behind TLS.
- Give the web server user write access to `public/avatars/uploads` and nothing else in the docroot.
  Uploaded avatars are served as static files from there, so they are public by design — which is
  why only 100x100 re-encodes of the three accepted image types ever reach that folder, never an
  original or anything else.

## Not included

- Pagination, edit history, nested replies, markdown, attachments. The avatar upload is the one file
  the board accepts, and it is resized to 100x100 rather than stored as sent.
- Password reset / e-mail verification (djbb-lite never had them either).
- Rate limiting or any spam control on comments.
- Front-end build tooling — `package.json` and Vite are gone; there is nothing to compile.

---

2026 [ ivan deus ]
