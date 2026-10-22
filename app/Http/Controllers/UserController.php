<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * The board's people, the one who wrote most recently first. Staff only, and the refusal is
     * split the way the admin's is: the `auth` middleware sends a guest to the login page, the
     * `staff` one answers a writer who is not staff with a 403. Ordering and the `Last action`
     * column read the same alias, so the two can never disagree — see User::withLastActivity().
     */
    public function index(): View
    {
        return view('users.index', [
            'people' => User::query()->withLastActivity()->orderByDesc('last_activity')->get(),
        ]);
    }

    /**
     * Switch one member between `member` and `admin` — the only write the roll-call offers, and
     * `is_staff` the only thing it can move. Laravel has no superuser flag, so the rule djbb-lite
     * states for itself ("never is_superuser") is structural here.
     *
     * `forceFill` because is_staff sits outside #[Fillable] on purpose: fill() would drop the key and
     * the click would look like it did nothing. The `(int)` casts are load-bearing too — with
     * ATTR_EMULATE_PREPARES on, an id read back from Postgres is a string while a freshly inserted
     * model's is an int, so `===` would hand the admin their own button. PostController::show casts
     * for the same reason.
     */
    public function updateRole(Request $request, User $member): RedirectResponse
    {
        abort_if((int) $member->id === (int) $request->user()->id, 403);

        $member->forceFill(['is_staff' => ! $member->is_staff])->save();

        $role = $member->is_staff ? 'an admin' : 'a member';

        return redirect()->route('member_list')->with('status', "{$member->username} is now {$role}.");
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
