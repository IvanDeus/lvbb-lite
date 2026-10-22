<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Route names follow djbb-lite's view names; the POST twin of a GET route gets a _store suffix.

Route::get('/', [PostController::class, 'index'])->name('post_list');

Route::get('/post/{post}', [PostController::class, 'show'])->whereNumber('post')->name('post_detail');

Route::get('/user/{username}', [UserController::class, 'show'])->name('user_detail');

// The roll-call behind the users figure in the footer. Not a page a reader came to browse, so
// staff is the only one who gets the link - and the only one who gets the page. The one write here
// flips a row's type; /admin/users stays read-only, which is why this lives outside that prefix.
Route::middleware(['auth', 'staff'])->group(function () {
    Route::get('/members', [UserController::class, 'index'])->name('member_list');
    Route::post('/members/{member}/role', [UserController::class, 'updateRole'])->whereNumber('member')->name('member_role_store');
});

Route::middleware('auth')->group(function () {
    Route::get('/post/new', [PostController::class, 'create'])->name('post_create');
    Route::post('/post/new', [PostController::class, 'store'])->name('post_store');

    Route::post('/post/{post}', [PostController::class, 'storeComment'])->whereNumber('post')->name('comment_store');

    // Not in djbb-lite's urls.py: the board has no other place to change a user's own data. Django
    // posts both /profile forms to one URL; here the password form gets a route of its own.
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile_store');
    Route::post('/profile/password', [ProfileController::class, 'updatePassword'])->name('password_store');

    Route::get('/post/{post}/edit', [PostController::class, 'edit'])->whereNumber('post')->name('post_update');
    Route::post('/post/{post}/edit', [PostController::class, 'update'])->whereNumber('post')->name('post_update_store');

    Route::get('/post/{post}/delete', [PostController::class, 'confirmDelete'])->whereNumber('post')->name('post_delete');
    Route::post('/post/{post}/delete', [PostController::class, 'destroy'])->whereNumber('post')->name('post_delete_store');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login_store');
Route::get('/signup', [AuthController::class, 'showSignup'])->name('signup');
Route::post('/signup', [AuthController::class, 'signup'])->name('signup_store');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth', 'staff'])->prefix('admin')->group(function () {
    Route::get('/', [AdminController::class, 'posts'])->name('admin_posts');
    Route::get('/comments', [AdminController::class, 'comments'])->name('admin_comments');
    Route::get('/users', [AdminController::class, 'users'])->name('admin_users');
    Route::get('/post/{post}', [AdminController::class, 'post'])->whereNumber('post')->name('admin_post');
    Route::post('/post/{post}/delete', [AdminController::class, 'destroyPost'])->whereNumber('post')->name('admin_post_delete');
    Route::post('/comment/{comment}/delete', [AdminController::class, 'destroyComment'])->whereNumber('comment')->name('admin_comment_delete');
});
