<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials)) {
            return back()
                ->withInput($request->only('username'))
                ->withErrors(['username' => 'Wrong username or password.']);
        }

        $request->session()->regenerate();

        $this->upgradeLegacyHash($request->user(), $credentials['password']);

        return redirect()->intended(route('post_list'));
    }

    /**
     * Rows imported from djbb-lite carry Django's pbkdf2_sha256 hashes; the custom hasher
     * verifies them, and the first successful login replaces them with bcrypt.
     */
    private function upgradeLegacyHash(User $user, string $password): void
    {
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }
    }

    public function showSignup(): View
    {
        return view('auth.signup');
    }

    public function signup(RegisterRequest $request): RedirectResponse
    {
        Auth::login(User::create($request->validated()));

        return redirect()->route('post_list');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('post_list');
    }
}
