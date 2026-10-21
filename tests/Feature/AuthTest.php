<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    public function test_the_login_form_is_public(): void
    {
        $this->get('/login')->assertOk()->assertSee('Log in', false);
        $this->get('/signup')->assertOk();
    }

    public function test_a_laravel_account_can_log_in(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_an_imported_django_account_can_log_in_and_is_upgraded(): void
    {
        $user = User::forceCreate([
            'username' => 'fromdjango',
            'email' => 'fromdjango@example.com',
            'password' => $this->djangoHash('zxc123'),
            'is_staff' => false,
        ]);
        $this->assertStringStartsWith('pbkdf2_sha256$', $user->password);

        $this->post('/login', ['username' => 'fromdjango', 'password' => 'zxc123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);

        $upgraded = $user->fresh()->password;
        $this->assertStringStartsWith('$2y$', $upgraded);
        $this->assertTrue(Hash::check('zxc123', $upgraded));

        Auth::logout();
        $this->post('/login', ['username' => 'fromdjango', 'password' => 'zxc123'])->assertRedirect('/');
    }

    public function test_a_wrong_password_is_rejected_with_the_django_wording(): void
    {
        $user = User::factory()->create();

        $this->get('/login');
        $this->post('/login', ['username' => $user->username, 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/login', ['username' => 'nobody', 'password' => 'password'])->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logging_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');

        $this->assertGuest();
        $this->get('/post/new')->assertRedirect('/login');
    }

    public function test_signup_creates_a_hashed_account_and_logs_it_in(): void
    {
        $this->post('/signup', [
            'username' => 'newbie',
            'email' => 'newbie@example.com',
            'password' => 'secret123',
        ])->assertRedirect('/');

        $user = User::firstWhere('username', 'newbie');
        $this->assertNotNull($user);
        $this->assertStringStartsWith('$2y$', $user->password);
        $this->assertTrue(Hash::check('secret123', $user->password));
        $this->assertFalse($user->is_staff);
        $this->assertAuthenticatedAs($user);
    }

    public function test_signup_rejects_a_case_variant_of_a_taken_username(): void
    {
        User::factory()->create(['username' => 'MixedCase']);

        $this->post('/signup', ['username' => 'mixedcase', 'email' => 'a@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('username');
        $this->post('/signup', ['username' => 'MIXEDCASE', 'email' => 'a@example.com', 'password' => 'secret123'])
            ->assertSessionHasErrors('username');

        $this->assertSame(1, User::whereRaw('lower(username) = ?', ['mixedcase'])->count());
    }

    public function test_signup_validates_its_fields(): void
    {
        $this->post('/signup', ['username' => '', 'email' => 'nope', 'password' => '123'])
            ->assertSessionHasErrors(['username', 'email', 'password']);

        $this->assertGuest();
    }

    public function test_two_accounts_may_share_an_email_like_django(): void
    {
        User::factory()->create(['email' => 'shared@example.com']);

        $this->post('/signup', ['username' => 'second', 'email' => 'shared@example.com', 'password' => 'secret123'])
            ->assertRedirect('/');

        $this->assertSame(2, User::where('email', 'shared@example.com')->count());
    }

    /**
     * Django's PBKDF2PasswordHasher layout: algorithm$iterations$salt$base64(digest).
     */
    private function djangoHash(string $password, int $iterations = 1000): string
    {
        $salt = 'testsalt';

        return sprintf(
            'pbkdf2_sha256$%d$%s$%s',
            $iterations,
            $salt,
            base64_encode(hash_pbkdf2('sha256', $password, $salt, $iterations, 0, true))
        );
    }
}
