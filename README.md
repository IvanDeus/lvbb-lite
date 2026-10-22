# LVBB-lite: Laravel Bulletin Board

A deliberately small bulletin board built with Laravel. No starter kit, no Bootstrap, no Vite, no Tailwind: the CSS is inline in the layout.

![PHP](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-13.38-red)

## Features

- **Posts** — title + body, newest first, with the index excerpting the body to 240 characters, the
  ellipsis counted.
- **Comments** — listed under each post, created by logged-in users.
- **Author-only editing** — you can edit or delete your own posts.
- **Staff override** — staff users can edit or delete *anyone's* post.
- **`/admin`** — a hand-rolled list of posts and comments with delete buttons, staff only.
- **Read-only profiles in the admin** — `/admin/users` lists every writer's avatar, name, e-mail and
  last action. The list is a table with no form, no button and no link in it.
- **Anonymous read access** — browsing needs no account; writing does.
- **Self-registration** — username, e-mail, password (min 6 chars), auto-login afterwards.
- **Avatars** — mandatory for every user: one of the four presets in `public/avatars`, or your own
  JPG/PNG/WebP, centre-cropped to 100&times;100 by GD as it is saved. `/profile` changes it later and
  shows the current one at that size.
- **Change password** — `/profile` takes a new password too, against the current one, and leaves you
  signed in through your own change.
- **A 404 that knows where it is** — a post id with nothing behind it renders the board's own page
  and carries the visitor back to the index two seconds later, with status 404 intact.
- **A page per member** — every avatar on the board links to `/user/{name}`, which shows the picture
  at its full 100&times;100. The e-mail address there is visible to staff only.
- **A members list** — `/members` is a table of every writer — face, name, whether they are an
  `admin` or a `member`, and when they last did something here — with whoever wrote most recently at
  the top. Staff only, and the users figure in the footer is the only way there. Every row but the
  admin's own carries a button that switches that account between the two types.
- **Footer counts** — a small panel above the credit line on every page, holding three numbers:
  members, replies, and topics. A topic is a post here.

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
> your shell. A `.env.local` would then hide `APP_KEY` and every request would fail with "No
> application encryption key has been specified". `DATABASE_URL` belongs in `.env`, next to the key.

`ConfigurationUrlParser` splits the URL into host / port / database / user / password and merges
its query parameters into the connection config, so `sslmode` and `channel_binding` arrive from
the URL itself; `DB_SSLMODE` / `DB_CHANNEL_BINDING` are the fallbacks for the split-variable form.

#### 1a. Getting the DB URL from the Neon console

The Neon CLI signs in through a browser OAuth callback on `127.0.0.1`, which never arrives on a
headless server — `neon login` times out there. Take the string from the web console instead:

1. Sign in at <https://console.neon.tech>.
2. Open your project → **Connection Details**.
3. Copy the **pooled** endpoint (the one ending in `-pooler…`).
4. Paste it into `.env` as `DATABASE_URL=`; append `&channel_binding=require` if it is missing.

#### 1b. Setting up a local PostgreSQL database

Neon is not a requirement to run the board. Postgres is the only supported engine either way, so a
local cluster is a straight stand-in and every command in
[Setting up a database](#setting-up-a-database) works against it unchanged.

1.  **Install PostgreSQL** — 16 or newer is what the board is written against — and PHP's driver with
    it:

    ```bash
    apt install postgresql php8.3-pgsql
    php -m | grep -i pgsql          # proves the driver landed
    ```

2.  **Create the role and the database**, as the `postgres` superuser — `sudo -u postgres psql`:

    ```sql
    CREATE ROLE lvbb_user LOGIN PASSWORD 'your_password';
    CREATE DATABASE lvbb_db OWNER lvbb_user;
    ```

    The role owns the database it is handed to `php artisan migrate`, because the migrations create,
    alter and drop tables. A `GRANT` over somebody else's schema is where a run stops halfway with a
    permission error, having left the earlier tables behind.

3.  **Point `.env` at it**, in place of the console string:

    ```bash
    DATABASE_URL=postgresql://lvbb_user:your_password@127.0.0.1:5432/lvbb_db?sslmode=disable&channel_binding=disable
    ```

    Both query parameters are needed against a cluster with `ssl = off`. `config/database.php`
    defaults `sslmode` and `channel_binding` to `require`, and the URL's query is what overrides
    those defaults; a non-TLS connection that insists on channel binding fails where the server
    answers the authentication request, which reads as a broken server rather than as a bad setting.
    A server that does speak TLS — the Debian package ships `ssl = on` with a self-signed certificate
    — can keep `sslmode=require&channel_binding=require` instead, as long as the role's password is
    stored as `scram-sha-256`, which has been `password_encryption`'s default since PostgreSQL 14. An
    `md5` hash there means a channel-binding failure, not a bad password. Run
    `php artisan config:clear` after the change if the config is ever cached.

4.  **Migrate and seed**:

    ```bash
    php artisan migrate
    php artisan db:seed
    ```

`php artisan serve --host=127.0.0.1 --port=7112` then answers on the local cluster, and
`php artisan test` runs against it too — the suite rolls back rather than migrating, so it needs the
same schema the board does. Switching back to Neon is one line in `.env` and a fresh
`php artisan migrate`, which builds an empty board: the rows that live on the other server stay
there.

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
3. **`dont_drop` guards somebody else's tables.** `config/database.php` lists 12 tables belonging to
   the board this project was ported from under `dont_drop`, so `migrate:fresh` and `db:wipe` skip
   them instead of deleting a schema they do not own. The current database holds none of them, but
   neither command is ever the right thing to run against a live board anyway.

## Setting up a database

The connection string is either the pooled Neon endpoint of
[1a](#1a-getting-the-db-url-from-the-neon-console) or a local cluster from
[1b](#1b-setting-up-a-local-postgresql-database). Everything below runs the same on both.

```bash
# 1. create the Laravel tables (users, sessions, posts, comments, cache, jobs, migrations)
php artisan migrate

# 2. then, on an empty database, the two test accounts
php artisan db:seed
```

`db:seed` skips an account whose username is already there, so re-running it on a board that has been
used is a no-op rather than a second `admin`.

## Quick start

```bash
cp .env.example .env      # set DATABASE_URL (1a or 1b), then: php artisan key:generate
php artisan migrate
php artisan serve --host=127.0.0.1 --port=7112
```

Open <http://127.0.0.1:7112/>. Use `--host=0.0.0.0` to answer on every interface instead — which is
how the instance this port was verified against runs — and remember that binds the seeded accounts to
whatever network the host is on.

## Test accounts

| Username | Password  | Type    | Can                                                        |
| -------- | --------- | ------- | ---------------------------------------------------------- |
| `admin`  | `pass123` | staff   | Everything, `/admin` including `/admin/users`, e-mails on member pages |
| `user`   | `zxc123`  | regular | Own posts only, no `/admin`, no e-mails on member pages     |

These are seeded for manual testing only (`php artisan db:seed`, which skips accounts that
already exist). They are weak by design — do not run this instance somewhere reachable from the
internet.

## Routes

Route names follow their paths, and the POST twin of a GET route gets a `_store` suffix. Two shapes in
the list are worth knowing: `/profile` splits the avatar (`POST /profile`) from the password
(`POST /profile/password`), so a view never asks which button was pressed; and `/admin/users` is a
page this app writes itself, because nothing here generates an admin for it.

| Method     | Path                        | Name                  | Controller action     | Notes                    |
| ---------- | --------------------------- | --------------------- | --------------------- | ------------------------ |
| `GET`      | `/`                         | `post_list`           | `PostController@index` | public                   |
| `GET`      | `/post/{post}`              | `post_detail`         | `@show`                | public, own 404 page     |
| `POST`     | `/post/{post}`              | `comment_store`       | `@storeComment`        | login required           |
| `GET`      | `/post/new`                 | `post_create`         | `@create`              | login required           |
| `POST`     | `/post/new`                 | `post_store`          | `@store`               | login required           |
| `GET`      | `/post/{post}/edit`         | `post_update`         | `@edit`                | author or staff          |
| `POST`     | `/post/{post}/edit`         | `post_update_store`   | `@update`              | author or staff          |
| `GET`      | `/post/{post}/delete`       | `post_delete`         | `@confirmDelete`       | author or staff          |
| `POST`     | `/post/{post}/delete`       | `post_delete_store`   | `@destroy`             | author or staff          |
| `GET`      | `/user/{username}`          | `user_detail`         | `UserController@show` | public, e-mail staff-only |
| `GET`      | `/members`                  | `member_list`         | `UserController@index` | staff, linked from the footer |
| `POST`     | `/members/{member}/role`    | `member_role_store`   | `@updateRole`          | staff, never the admin's own row |
| `GET`      | `/login`                    | `login`               | `AuthController@showLogin` |                        |
| `POST`     | `/login`                    | `login_store`         | `@login`               |                          |
| `GET`      | `/signup`                   | `signup`              | `@showSignup`          |                          |
| `POST`     | `/signup`                   | `signup_store`        | `@signup`              | avatar required          |
| `POST`     | `/logout`                   | `logout`              | `@logout`              | POST only                |
| `GET`      | `/profile`                  | `profile`             | `ProfileController@show` | login required         |
| `POST`     | `/profile`                  | `profile_store`       | `@update`              | login required           |
| `POST`     | `/profile/password`         | `password_store`      | `@updatePassword`      | login required           |
| `GET`      | `/admin`                    | `admin_posts`         | `AdminController@posts` | staff                  |
| `GET`      | `/admin/comments`           | `admin_comments`      | `@comments`            | staff                    |
| `GET`      | `/admin/users`              | `admin_users`         | `@users`               | staff, read-only         |
| `GET`      | `/admin/post/{post}`        | `admin_post`          | `@post`                | staff                    |
| `POST`     | `/admin/post/{post}/delete` | `admin_post_delete`   | `@destroyPost`         | staff                    |
| `POST`     | `/admin/comment/{c}/delete` | `admin_comment_delete` | `@destroyComment`     | staff                    |
| `GET`      | `/up`                       | —                     | framework health check |                          |

Comments are POSTed to the post's own URL, so `@show` and `@storeComment` share one path;
`/post/new` is declared before `/post/{post}` so the literal segment wins. `{post}` and `{comment}`
are constrained with `whereNumber` — an id is an integer or nothing, and `/post/abc` never reaches a
controller.

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
logged-in non-staff user and redirects a guest to `/login`. `/members` sits behind that same pair, and
the footer figure that links to it is rendered as a link for staff alone.
`POST /members/{member}/role`, which switches a writer between the two types, sits behind it too, with
one refusal added: the row of the admin who is clicking answers `403` as well, because no path from
that page may end the right to use it.

`comment_store` sits behind the `auth` middleware rather than trusting a template to keep the form
away from a guest, so an anonymous POST to a post's URL is a redirect to `/login` and not a write.

Two rules about names are worth stating. The login lookup is **case-sensitive**
(`user` ≠ `USER`), while the signup check is **case-insensitive** — a name that differs from an
existing one only in letter case is refused with "This username is already taken." And `users.email`
carries no unique constraint: the initial migration added one by reflex, and
`2026_10_21_010000_drop_email_unique_from_users_table` takes it back off, because two members sharing
an address is a thing this board allows.

## The pages that came last

### A page for each member

`/user/{username}` shows one writer's avatar at the 100&times;100 it was made for, beside their name.
Every avatar on the board — post author and comment author, on the index and on a thread — is wrapped
in a link to that page, which is why the view needs no query beyond the lookup itself:
`User::where('username', $username)->firstOrFail()`. The e-mail line renders only when
`$request->user()?->is_staff`, so a guest and a fellow writer see the same thing on that page: a face
and a name.

`{username}` carries no constraint, and none is needed — the segment takes anything but a slash, and
the check is a lookup rather than a route pattern. An unknown name answers the framework's 404; the
board has no missing-member page, only a missing-*post* one.

### Looking at profiles in the admin

`/admin/users` is a read-only window onto the board's people: avatar, name, e-mail and last action, in
username order, behind the same `staff` middleware as the rest of `/admin` — a guest goes to `/login`, a
writer gets `403`.

`admin/users.blade.php` is a `<table>` with no form, no button and no anchor in it, so there is
nothing to disable, and nothing to work at: avatar, name, e-mail and the staff flag all live on the
`users` row, so one query reads the page. The fourth column is the roll-call's own, reached through
the same `User::withLastActivity()` scope, and it is still only a value to read.

There is no search box — two text fields across five accounts is not a list you can lose your place
in. Nothing here is edited either: accounts are made by the seeder, `/profile` changes an avatar and a
password, and the one thing an admin changes about somebody else is their type, which happens on the
roll-call below.

### A roll-call of the board

`/members` is the staff-only list of every writer — face, name, `admin` or `member`, and when they last
did something here — with whoever did something most recently at the top. The ordering is one scope on
the model, `User::withLastActivity()`, so the roll-call and `/admin/users` read one definition of it:

```php
$query->select('users.*')
    ->selectRaw('COALESCE(GREATEST(MAX(posts.created_at), MAX(comments.created_at)), MAX(users.created_at)) AS last_activity')
    ->leftJoin('posts', 'posts.author_id', '=', 'users.id')
    ->leftJoin('comments', 'comments.author_id', '=', 'users.id')
    ->groupBy('users.id');
```

Two Postgres rules make that one query enough. `GREATEST` returns NULL only when *every* argument is,
so a writer with posts and no comments is still placed by their newest post — and that is also why the
join date belongs in the `COALESCE` rather than as a fourth argument: folded in there, it would floor
every account at its own signup and a year-old topic would never fall below someone who joined last
week. And `GROUP BY users.id` groups by the primary key, which is what lets every other `users` column
through the `SELECT *` beside it. The alias is a legal `ORDER BY` key, so the roll-call sorts on it with
`->orderByDesc('last_activity')` — the same value the `Last action` column prints, which is why the two
can never disagree. `/admin/users` calls the scope too, but keeps its username order.

`last_activity` is never a column, only an alias, so it arrives as a string and `->format()` on it would
be a fatal call-on-a-string. `casts()` therefore declares it a `datetime`, which also keeps both tables
on the board's one timestamp convention. A row read without the scope has no such key at all, and
Laravel returns null for a missing attribute before any cast runs, so the declaration cannot break a
plain `User::find`.

The refusals are not new code: the route carries `['auth', 'staff']`, the same pair the `/admin` group
uses, so a guest goes to `/login` and a writer who is not staff gets `403`. `users/index.blade.php` is
a table whose rows link out to the member pages and, since the column arrived, end in one button. The
way in is the users figure in the footer, which `partials/stats.blade.php` wraps in an `<a>` only when
`auth()->user()?->is_staff` — the wording every other visitor reads is unchanged, character for
character.

One property of the columns is worth knowing: they are `timestamp(0)`, so two accounts created in the
same second tie, and Postgres breaks the tie as it likes.

### Switching a member's type

Every row of that table but the visitor's own ends in a button — `make admin` on a member, `make
member` on an admin — posting to `POST /members/{member}/role`. The name ends in `_store` because there
is no GET twin, the case `password_store` already set, and the two members routes now sit in one
`['auth', 'staff']` group. It is *not* under the `/admin` prefix: `/admin/users` is the page whose whole
contract is that it writes nothing.

`is_staff` is the only field that moves, and `forceFill` is what lets it. The attribute sits outside
`#[Fillable]` on purpose — an account may not make itself staff by POSTing a field — which means
`fill()` would silently drop it and the button would look broken. `UserController::updateRole` writes
the one attribute directly, the way `ProfileController` and `AuthController` already had to.

The acting admin's own row is designed against self-lockout: the template draws no button there, and
`abort_if((int) $member->id === (int) $request->user()->id, 403)` refuses a forged POST naming it.
Those two casts are load-bearing rather than tidy — with emulated prepares
on PgBouncer every id read back from Postgres is a *string*, while a model the factory just inserted has
an int one, so a bare `===` would hand the admin their own button. `PostController` casts for
the same reason. The route parameter and the method parameter must both stay named `$member`, or
implicit binding matches nothing and the `save()` becomes an INSERT.

Nobody is told to log in again, and no `is_superuser` is involved: this app has no such column, so the
two types are the whole of it. The answer is a redirect back to the roll-call with a status line —
`user is now an admin.` — which `layouts/app.blade.php` renders above the table, next to the row that
now shows the other word.

### Changing a password

`/profile` holds two forms with two endpoints: `POST /profile` for the avatar,
`POST /profile/password` for the password. A view never asks which button was pressed, so a rejected
password cannot paint errors over the picker next to it, and saving an avatar never has to know the
current password.

`PasswordRequest` does the whole check: `Hash::check` against the stored hash for `old_password`,
`min:8` and `same:new_password1` for the pair, then an `after()` hook for the two checks Laravel has
no rule for — all-numeric, and too similar to this account's own username or address. The messages are
written out by hand, the typographic apostrophe in "didn’t match" included.

The similarity check had to be written (`app/Validation/PasswordSimilarity.php`), because nothing in
Laravel does it: each attribute is split on non-word characters plus the whole value, a piece the
password dwarfs is skipped (ten times its length, and under 35% of the password), and what is left is
rejected at a common-character frequency of 0.7 or more.

There is no blocklist of known-leaked passwords to consult — Laravel ships none, and copying twenty
thousand words of them into a toy board is a thing this repo should not grow. A password drawn from
such a list therefore passes here, which is why the field hint promises only what is enforced.

Two details of the write. `forceFill` is used so the `hashed` cast runs, which means the new password
lands as bcrypt. And no session-refresh step is needed to stay signed in: the session row is keyed to
the user's **id**, so a new hash does not invalidate the cookie that just arrived — an app that keyed
the session to a digest of the password would log out anyone who changed it. The result is announced
through `session('status')` — the flashed line `layouts/app.blade.php` renders as `.messages`.

### When a post is gone

`/post/7` with nothing behind it answers with `resources/views/posts/missing.blade.php` — "That topic
does not exist, or it has been deleted" — and a `<meta http-equiv="refresh" content="2;url=…">` in the
head carries the visitor to the index two seconds later, with a plain link for anyone who wants it
sooner. `@section('head')` is a `@yield` the layout gained for exactly this, placed after the inline
`<style>`.

Two choices are worth knowing about. The controller **returns** that page with
`response()->view('posts.missing', [], 404)` rather than throwing `NotFoundHttpException`, because with
`APP_DEBUG=true` Laravel renders its own technical page over the exception handler on exactly the
machine most likely to be running this repo. And the response still **carries status 404**: the
redirect is a courtesy to the person reading the page, not a lie to the crawler, the monitoring check
or `curl`.

`show()` takes the path segment as a string and calls `Post::find((int) $post)`, and PHP saturates an
over-long digit string at `PHP_INT_MAX` — so `/post/99999999999999999999` is a missing post rather
than a Postgres integer-out-of-range 500. That cast plus `whereNumber` is what makes an id an integer
or nothing.

### The three footer numbers

Every page closes with a small bordered panel above the credit line: `5 users · 4 comments ·
3 topics`. **A topic is a post** — this board has no separate thread or category model — so the
three figures are members, replies, and topics.

`app/View/Composers/BoardCounts.php` supplies them, bound to `layouts.app` with `View::composer()` in
`AppServiceProvider::boot()`. That is how every board view and the login and signup pages get the same
footer without each of them passing three counts, and without the next view added forgetting to: Blade
compiles `@extends` into a `make()` on the layout, so a composer on the layout fires for everything
that extends it. The one trap is that a class-based composer is invoked as `compose()`, not `handle()`
— name it `handle` and every page in the layout 500s.

`partials/stats.blade.php` does the wording, with `Str::plural($word, $count)` keeping the panel honest
about one and about many. They are counted on every request rather than cached: three indexed `COUNT`s
over tables this small are cheaper than a cache that can report a member who has since left.

## Project layout

```
.
├── app/
│   ├── Console/Commands/ImportDjangoData.php   # board:import-django
│   ├── Database/Connectors/PostgresConnector.php  # channel_binding into the DSN
│   ├── Hashing/DjangoHasher.php                # pbkdf2_sha256/sha1 + bcrypt upgrade
│   ├── Http/
│   │   ├── Controllers/      # PostController, AuthController, AdminController,
│   │   │                     # ProfileController, UserController
│   │   ├── Middleware/       # EnsureUserIsStaff (alias: staff)
│   │   └── Requests/         # PostRequest, CommentRequest, RegisterRequest, ProfileRequest,
│   │                         # PasswordRequest, and Concerns/GathersAvatar, the shared avatar rules
│   ├── Models/               # Post, Comment, User
│   ├── Services/             # AvatarResizer (GD crop + resample), AvatarStore (write + cleanup)
│   ├── Validation/PasswordSimilarity.php  # the similarity rule inside PasswordRequest
│   └── View/Composers/BoardCounts.php     # the three footer numbers, on every page
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
│   ├── layouts/app.blade.php # header, nav, inline CSS, flashes, footer
│   ├── posts/                # index, show, form, confirm_delete, missing
│   ├── users/                # show (one member, avatar at full size), index (the staff roll-call)
│   ├── auth/                 # login, signup
│   ├── admin/                # posts, post, comments, users (read-only)
│   ├── partials/             # avatar-picker (signup + profile), stats (footer)
│   └── profile.blade.php     # avatar picker and password form, posting separately
├── routes/web.php
└── tests/Feature/            # posts, auth, admin, hashing, import, avatars, member page,
                              # members list, footer counts, missing post, password change,
                              # the type switch
```

`Post` and `Comment` set `const UPDATED_AT = null` and carry `created_at` alone, `comments()`
orders by `created_at`, and `password` / `remember_token` stay hidden on the `User` model. `is_staff`
stays out of `#[Fillable]` for the same reason it stays out of a form: nothing a visitor posts may make
them staff, so the role switch writes it with `forceFill` after its own staff check.

Three things about the `users` columns are worth naming. There is no `name`, `email_verified_at`, or
`password_reset_tokens` — the board has no use for them and there is no password reset here. `avatar`
is `NOT NULL` and holds exactly two shapes: one of the four preset basenames, or
`uploads/{id}.{jpeg,png,webp}`. And `User::presetForId()` — `id % 4` over the four presets — rotates a
row onto a picture by primary key, which the backfill in `2026_10_22_000000_add_avatar_to_users_table`
spells the same way in SQL: change one, change the other.

## Tests

```bash
php artisan test        # 104 feature tests
vendor/bin/pint --test  # style
```

`tests/TestCase.php` applies `DatabaseTransactions`, so each test rolls back. **Never use
`RefreshDatabase` in this project** — the suite runs against the same database as the live board, so
migrating or wiping would destroy real data. The `phpunit.xml` env block keeps sessions, cache and mail
in-memory; the database connection still comes from `.env`.

## Production notes

Nothing here is production-shaped, on purpose: `APP_DEBUG=true`, the `log` mailer, seeded weak
accounts, and `artisan serve` — a development server, which here listens on `0.0.0.0:7112` so another
machine can look at the board. The things that would actually bite:

- Set `APP_KEY` freshly (`php artisan key:generate`); a key that has ever sat in a published repo is
  a public key, whatever the file says about it.
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
- Password reset and e-mail verification.
- A blocklist of known-leaked passwords. Laravel ships none, and copying a file of about twenty
  thousand of them into a toy board is a thing this repo should not grow; `PasswordRequest` checks
  length, all-digits and similarity to the account's own username or address, and nothing else.
- A search box on `/admin/users`. Five accounts do not need one.
- Rate limiting or any spam control on comments.
- Front-end build tooling — `package.json` and Vite are gone; there is nothing to compile.

---

2026 [ ivan deus ]
