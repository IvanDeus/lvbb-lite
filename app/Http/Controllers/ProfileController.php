<?php

namespace App\Http\Controllers;

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
}
