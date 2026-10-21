<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
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
