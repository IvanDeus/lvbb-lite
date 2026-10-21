<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Route names follow djbb-lite's view names; the POST twin of a GET route gets a _store suffix.

Route::get('/', [PostController::class, 'index'])->name('post_list');

Route::get('/post/{post}', [PostController::class, 'show'])->whereNumber('post')->name('post_detail');

Route::middleware('auth')->group(function () {
    Route::get('/post/new', [PostController::class, 'create'])->name('post_create');
    Route::post('/post/new', [PostController::class, 'store'])->name('post_store');

    Route::post('/post/{post}', [PostController::class, 'storeComment'])->whereNumber('post')->name('comment_store');

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
    Route::get('/post/{post}', [AdminController::class, 'post'])->whereNumber('post')->name('admin_post');
    Route::post('/post/{post}/delete', [AdminController::class, 'destroyPost'])->whereNumber('post')->name('admin_post_delete');
    Route::post('/comment/{comment}/delete', [AdminController::class, 'destroyComment'])->whereNumber('comment')->name('admin_comment_delete');
});
