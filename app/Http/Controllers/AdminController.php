<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use App\Models\Post;
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
