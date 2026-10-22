<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\RedirectResponse;

class AdminController extends Controller
{
    public function posts(): View
    {
        return view('admin.posts', [
            'posts' => Post::with('author')->withCount('comments')->latest('created_at')->get(),
        ]);
    }

    /**
     * The board's people, for looking at and not for touching. There is no add, edit or delete
     * route here to withhold: this table is the whole of what the admin offers a user row. The
     * `Last action` column is the roll-call's own, read through User::withLastActivity() rather than
     * a second copy of the SQL - and the ordering stays alphabetical, so here the column is a fact
     * about the account rather than the key its row is sorted by.
     */
    public function users(): View
    {
        return view('admin.users', ['users' => User::query()->withLastActivity()->orderBy('username')->get()]);
    }

    public function post(Post $post): View
    {
        return view('admin.post', ['post' => $post->load(['author', 'comments.author'])]);
    }

    public function comments(): View
    {
        return view('admin.comments', [
            'comments' => Comment::with(['post', 'author'])->latest('created_at')->get(),
        ]);
    }

    public function destroyPost(Post $post): RedirectResponse
    {
        $post->delete();

        return redirect()->route('admin_posts');
    }

    public function destroyComment(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return redirect()->route('admin_comments');
    }
}
