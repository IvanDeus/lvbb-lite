# LVBB-lite: Laravel Bulletin Board

A deliberately small bulletin board built with Laravel. No starter kit, no Bootstrap, no Vite, no Tailwind: the CSS is inline in the layout.

![PHP](https://img.shields.io/badge/php-8.3-blue) ![Laravel](https://img.shields.io/badge/laravel-13.38-red)

## Features

- **Posts** — title + body, newest first, body excerpted on the index like `truncatechars:240`.
- **Comments** — listed under each post, created by logged-in users.
- **Author-only editing** — you can edit or delete your own posts.
- **Staff override** — staff users can edit or delete *anyone's* post.
- **`/admin`** — a hand-rolled list of posts and comments with delete buttons, staff only.
- **Read-only profiles in the admin** — `/admin/users` lists every writer's avatar, name and e-mail.
  The list is a table with no form, no button and no link in it.
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
- **A members list** — `/members` is a table of every writer — face, name, and whether they are an
  `admin` or a `member` — with whoever wrote most recently at the top. Staff only, and the users
  figure in the footer is the only way there.
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
3. **`dont_drop` guards djbb-lite's tables.** `config/database.php` lists the 12 Django tables
   under `dont_drop`, so `migrate:fresh` and `db:wipe` skip them instead of deleting someone
   else's board. The current database has no Django tables at all, but neither command is ever the
   right thing to run against a live board anyway.

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

Paths and route names match djbb-lite; the POST twin of a GET route gets a `_store` suffix.
Three paths have no Django equivalent, all because of how Laravel asks for the same behaviour:
`/profile` and its POST (the board had no page for a user's own data), `POST /profile/password`
(Django posts both profile forms to one URL and lets the button decide), and `/admin/users` (Django's
contrib admin generates that page, this one is written).

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
| `GET`      | `/login`                    | `login`               | `AuthController@showLogin` |                        |
| `POST`     | `/login`                    | `login_store`         | `@login`               |                          |
| `GET`      | `/signup`                   | `signup`              | `@showSignup`          |                          |
| `POST`     | `/signup`                   | `signup_store`        | `@signup`              | avatar required          |
| `POST`     | `/logout`                   | `logout`              | `@logout`              | POST only, as in Django  |
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
logged-in non-staff user and redirects a guest to `/login` — the same as Django's admin. `/members`
sits behind that same pair, and the footer figure that links to it is rendered as a link for staff
alone.

Django's `post_detail` accepted comment POSTs from anonymous visitors and relied on the template
to hide the form; here `comment_store` sits behind the `auth` middleware, which is the same
outcome without the trap.

## The pages that came last

### A page for each member

`/user/{username}` shows one writer's avatar at the 100&times;100 it was made for, beside their name.
Every avatar on the board — post author and comment author, on the index and on a thread — is wrapped
in a link to that page, which is why the view needs no query beyond the lookup itself:
`User::where('username', $username)->firstOrFail()`. The e-mail line renders only when
`$request->user()?->is_staff`, so a guest and a fellow writer see exactly what Django's
`user_detail.html` shows them: a face and a name.

Django's `<str:username>` needed no constraint and neither does `{username}` — the segment takes
anything but a slash, and the check is a lookup rather than a route pattern. An unknown name answers
the framework's 404; the board has no missing-member page, only a missing-*post* one.

### Looking at profiles in the admin

`/admin/users` is a read-only window onto the board's people: avatar, name, e-mail, in username
order, behind the same `staff` middleware as the rest of `/admin` — a guest goes to `/login`, a writer
gets `403`.

`admin/users.blade.php` is a `<table>` with no form, no button and no anchor in it, so there is
nothing to disable. Django had to work for the same result: `ProfileAdmin` refusing add, change and
delete is what puts its change page into view mode, and every field then has to be listed in
`readonly_fields` to appear at all, because the model only holds the avatar string while name and
e-mail are methods reading through the one-to-one — with a `select_related` so the list does not fetch
each user separately. Here avatar, name, e-mail and the staff flag all live on the `users` row, so one
query reads the page and there is no relation to follow.

What was not copied over is the search box: Django's `search_fields` covered two text fields, and this
instance has five accounts. Accounts are edited nowhere in this app — the seeder and
`board:import-django` make them, `/profile` changes an avatar and a password, and Django keeps its own
user admin because it has one.

### A roll-call of the board

`/members` is the staff-only list of every writer — face, name, and `admin` or `member` — with whoever
did something most recently at the top. Django's `member_list` annotates
`Coalesce(Greatest(Max, Max), F('date_joined'))`; `UserController::index` spells the same expression
out for the query builder:

```php
->selectRaw('COALESCE(GREATEST(MAX(posts.created_at), MAX(comments.created_at)), MAX(users.created_at)) AS last_activity')
->leftJoin('posts', 'posts.author_id', '=', 'users.id')
->leftJoin('comments', 'comments.author_id', '=', 'users.id')
->groupBy('users.id')
->orderByDesc('last_activity')
```

Two Postgres rules make that one query enough. `GREATEST` returns NULL only when *every* argument is,
so a writer with posts and no comments is still placed by their newest post — and that is also why the
join date belongs in the `COALESCE` rather than as a fourth argument: folded in there, it would floor
every account at its own signup and a year-old topic would never fall below someone who joined last
week. And `GROUP BY users.id` groups by the primary key, which is what lets every other `users` column
through the `SELECT *` beside it. The alias is a legal `ORDER BY` key, so the expression is written
once.

The refusals are not new code: the route carries `['auth', 'staff']`, the same pair the `/admin` group
uses, so a guest goes to `/login` and a writer who is not staff gets `403`. `users/index.blade.php` is
a table with links out to the member pages and nothing else. The way in is the users figure in the
footer, which `partials/stats.blade.php` wraps in an `<a>` only when `auth()->user()?->is_staff` — the
wording every other visitor reads is unchanged, character for character.

One difference in the data is worth knowing: these columns are `timestamp(0)`, so two accounts created
in the same second tie, and Postgres breaks the tie as it likes. Django's are microsecond and its
order there is total.

### Changing a password

`/profile` holds two forms. Django posts both to one URL and reads the pressed button's name
(`change_password`) to decide which of the two is bound; here each has its own endpoint —
`POST /profile` for the avatar, `POST /profile/password` for the password — so a view never asks which
button was pressed, a rejected password cannot paint errors over the picker next to it, and saving an
avatar never has to know the current password.

`PasswordRequest` is the counterpart of `PasswordChangeForm`: `Hash::check` against the stored hash
for `old_password`, `min:8` and `same:new_password1` for the pair, then an `after()` hook for the two
checks Laravel has no rule for — all-numeric, and too similar to this account's own username or
address. The messages are Django's own strings, including the typographic apostrophe.

The similarity check had to be written (`app/Validation/PasswordSimilarity.php`) because nothing in
Laravel resembles `UserAttributeSimilarityValidator`: each attribute is split on non-word characters
plus the whole value, a piece the password dwarfs is skipped (ten times its length, and under 35% of
the password), and what is left is rejected at a common-character frequency of 0.7 or more.

`CommonPasswordValidator` is the one Django check with no counterpart — Laravel ships no blocklist to
consult, and copying Django's twenty thousand words into a toy board is a thing this repo should not
grow. A password drawn from that list therefore passes here, which is why the field hint promises only
what is enforced.

Two details of the write. `forceFill` is used so the `hashed` cast runs, which means the new password
lands as bcrypt and finishes what the first login of an imported pbkdf2 string started. And nothing
resembles `update_session_auth_hash`: Laravel's session row is keyed to the user's **id**, so a new
hash does not invalidate the cookie that just arrived, whereas Django keys it to the password hash and
would log out anyone who changed their own password without that call. The result is announced through
`session('status')` — the flashed line `layouts/app.blade.php` renders as `.messages`, which is what
`django.contrib.messages` became here.

### When a post is gone

`/post/7` with nothing behind it answers with `resources/views/posts/missing.blade.php` — "That topic
does not exist, or it has been deleted" — and a `<meta http-equiv="refresh" content="2;url=…">` in the
head carries the visitor to the index two seconds later, with a plain link for anyone who wants it
sooner. `@section('head')` is a `@yield` the layout gained for exactly this, placed after the inline
`<style>`.

Two choices are worth knowing about. The controller **returns** that page with
`response()->view('posts.missing', [], 404)` rather than throwing `NotFoundHttpException`, because with
`APP_DEBUG=true` Laravel renders its own technical page over the exception handler on exactly the
machine most likely to be running this repo — the same reason Django's `post_detail` returns instead of
raising `Http404`. And the response still **carries status 404**: the redirect is a courtesy to the
person reading the page, not a lie to the crawler, the monitoring check, or `curl`.

Django got "a pk is an integer or nothing" free from `<int:pk>`. Here `show()` takes a string and calls
`Post::find((int) $post)`, and PHP saturates an over-long digit string at `PHP_INT_MAX` — so
`/post/99999999999999999999` is a missing post rather than a Postgres integer-out-of-range 500. That
cast plus `whereNumber` is what keeps Django's matcher.

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

`partials/stats.blade.php` does the wording, with `Str::plural($word, $count)` where Django's template
had `|pluralize`. They are counted on every request rather than cached: three indexed `COUNT`s over
tables this small are cheaper than a cache that can report a member who has since left.

## Living with the Django app

The port was written to share one schema with djbb-lite, each app with its own tables. The database
it runs against now (a fresh Neon project since 2026-10-21) holds only the Laravel tables, so the
importer stands down until it is pointed at a database where the Django tables live.

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
- **Member pages therefore show different pictures.** Django's `/user/<name>/` reads the avatar from
  `board.Profile`, this app's `/user/{username}` from `users.avatar`. A Django user who uploaded a
  picture sees it there and the preset derived from their id here — `board:import-django` skips ids
  that already exist, so it never carries an upload across. Username and e-mail agree, since both come
  from the same rows.

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
│   ├── Validation/PasswordSimilarity.php  # Django's UserAttributeSimilarityValidator
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
                              # members list, footer counts, missing post, password change
```

`Post` and `Comment` set `const UPDATED_AT = null` (Django had only `created_at`), `comments()`
orders by `created_at`, and `password` / `remember_token` stay hidden on the `User` model.

## Tests

```bash
php artisan test        # 93 feature tests, 2 of which skip without the Django tables
vendor/bin/pint --test  # style
```

`tests/TestCase.php` applies `DatabaseTransactions`, so each test rolls back. **Never use
`RefreshDatabase` in this project** — the suite runs against the same Neon database as the live
board, so migrating or wiping would destroy real data. The two `ImportDjangoDataTest` cases skip
themselves when `auth_user` is absent, which is the state of the current database. The
`phpunit.xml` env block keeps sessions, cache and mail in-memory; the database connection still
comes from `.env`.

## Production notes

Nothing here is production-shaped, on purpose: `APP_DEBUG=true`, the `log` mailer, seeded weak
accounts, and `artisan serve` — a development server, which here listens on `0.0.0.0:7112` so another
machine can look at the board. The things that would actually bite:

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
- Django's common-password blocklist. `CommonPasswordValidator` consults a file of about twenty
  thousand leaked passwords; `PasswordRequest` checks length, all-digits and similarity to the
  account's own username or address, and nothing else. It is the only one of Django's four validators
  with no counterpart here.
- A search box on `/admin/users`. Django had one over two text fields; this instance has five accounts.
- Rate limiting or any spam control on comments.
- Front-end build tooling — `package.json` and Vite are gone; there is nothing to compile.

---

2026 [ ivan deus ]
