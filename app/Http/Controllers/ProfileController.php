<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasswordRequest;
use App\Http\Requests\ProfileRequest;
use App\Services\AvatarStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function __construct(private readonly AvatarStore $avatars) {}

    public function show(Request $request): View
    {
        return view('profile', ['user' => $request->user()]);
    }

    public function update(ProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->avatars->save(
            $user,
            $request->validated('avatar') ?? $user->avatar,
            $request->file('avatar_file'),
        );

        return redirect()->route('profile');
    }

    /**
     * Django answers both forms on one URL and lets the pressed button decide which is bound;
     * separate posts to separate routes do the same job, so a rejected password never reaches the
     * avatar store and a saved avatar never asks for the old password.
     */
    public function updatePassword(PasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        // The hashed cast writes with the configured driver, so the change lands as bcrypt and
        // finishes what the first login of an imported Django hash started.
        $user->forceFill(['password' => $request->validated('new_password1')])->save();

        // Nothing keeps the session alive, because nothing ends it: Laravel stores the user's id,
        // where Django keyed the session to the password hash and needed update_session_auth_hash.
        return redirect()->route('profile')->with('status', 'Password changed.');
    }
}
