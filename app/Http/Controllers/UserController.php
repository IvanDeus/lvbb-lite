<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * The board's people, the one who wrote most recently first. Staff only, and the refusal is
     * split the way the admin's is: the `auth` middleware sends a guest to the login page, the
     * `staff` one answers a writer who is not staff with a 403.
     *
     * One grouped query, spelling Django's `Coalesce(Greatest(Max, Max), date_joined)`: GREATEST
     * returns NULL only when every argument is, so a writer is placed by their newest post or
     * newest reply, and the day they joined counts as their activity only while they have written
     * nothing at all. Grouping by the primary key is what lets every other `users` column through.
     */
    public function index(): View
    {
        return view('users.index', [
            'people' => User::query()
                ->select('users.*')
                ->selectRaw('COALESCE(GREATEST(MAX(posts.created_at), MAX(comments.created_at)), MAX(users.created_at)) AS last_activity')
                ->leftJoin('posts', 'posts.author_id', '=', 'users.id')
                ->leftJoin('comments', 'comments.author_id', '=', 'users.id')
                ->groupBy('users.id')
                ->orderByDesc('last_activity')
                ->get(),
        ]);
    }

    /**
     * One board member, at the size their picture was made for. Public, because the board shows
     * these faces everywhere; the e-mail address is the one thing held back for staff.
     */
    public function show(Request $request, string $username): View
    {
        return view('users.show', [
            'person' => User::where('username', $username)->firstOrFail(),
            'showEmail' => (bool) $request->user()?->is_staff,
        ]);
    }
}
