<?php

namespace App\View\Composers;

use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use Illuminate\View\View;

class BoardCounts
{
    /**
     * Three counts for the footer, on every page.
     *
     * A topic is a post — this board has no separate thread or category model — so the trio is
     * members, replies, topics. Counted per request rather than cached: three indexed COUNTs on
     * tables this small cost less than anything that could go stale.
     *
     * `compose` is the method View::composer() calls on a class-based composer, not `handle`.
     */
    public function compose(View $view): void
    {
        $view->with('stats', [
            'users' => User::count(),
            'comments' => Comment::count(),
            'topics' => Post::count(),
        ]);
    }
}
