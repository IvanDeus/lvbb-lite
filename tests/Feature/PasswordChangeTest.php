<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    private const OLD_PASSWORD = 'zxc123';

    private const NEW_PASSWORD = 'gathering-rosebuds';

    private User $writer;

    protected function setUp(): void
    {
        parent::setUp();

        // The picker's disk is the docroot, and one test below keeps an uploaded avatar in place.
        Storage::fake('avatars');

        $this->writer = User::factory()->create([
            'username' => 'donne',
            'email' => 'donne@example.com',
            'password' => self::OLD_PASSWORD,
            'avatar' => 'male-b2.png',
        ]);

        $this->actingAs($this->writer);
    }

    /** @param array<string, string> $overrides */
    private function change(array $overrides = []): TestResponse
    {
        return $this->post('/profile/password', array_merge([
            'old_password' => self::OLD_PASSWORD,
            'new_password1' => self::NEW_PASSWORD,
            'new_password2' => self::NEW_PASSWORD,
        ], $overrides));
    }

    public function test_the_page_offers_both_forms(): void
    {
        $page = $this->get('/profile')->assertOk()->assertSessionHasNoErrors();

        foreach (['old_password', 'new_password1', 'new_password2', 'avatar'] as $field) {
            $page->assertSee('name="'.$field.'"', false);
        }
    }

    public function test_the_current_password_has_to_be_right(): void
    {
        $this->change(['old_password' => 'not-it'])->assertSessionHasErrors([
            'old_password' => 'Your old password was entered incorrectly. Please enter it again.',
        ]);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->writer->fresh()->password));
    }

    public function test_a_rejected_password_leaves_the_avatar_picker_alone(): void
    {
        // A rejected submission redirects back to the page the form was submitted from, where the
        // picker is rebuilt from the stored row rather than from anything the POST carried.
        $this->get('/profile');

        $this->followingRedirects()->change(['old_password' => 'not-it'])
            ->assertOk()
            ->assertSee('value="male-b2.png" checked', false);

        $this->assertSame('male-b2.png', $this->writer->fresh()->avatar);
    }

    public function test_the_two_new_passwords_have_to_match(): void
    {
        $this->change(['new_password2' => 'gathering-rosebuds?'])
            ->assertSessionHasErrors(['new_password2' => 'The two password fields didn’t match.']);

        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->writer->fresh()->password));
    }

    public function test_a_short_password_is_refused(): void
    {
        $this->change(['new_password1' => 'rose', 'new_password2' => 'rose'])->assertSessionHasErrors([
            'new_password1' => 'This password is too short. It must contain at least 8 characters.',
        ]);
    }

    public function test_an_all_numeric_password_is_refused(): void
    {
        $this->change(['new_password1' => '12345678', 'new_password2' => '12345678'])
            ->assertSessionHasErrors(['new_password1' => 'This password is entirely numeric.']);
    }

    public function test_a_password_borrowed_from_the_account_is_refused(): void
    {
        // Eight characters with five of them the username: the ratio Django's validator condemns.
        $this->change(['new_password1' => 'donne123', 'new_password2' => 'donne123'])
            ->assertSessionHasErrors(['new_password1' => 'The password is too similar to the username.']);

        $this->writer->update(['username' => 'herbert', 'email' => 'example@rose.com']);

        $this->change(['new_password1' => 'example123', 'new_password2' => 'example123'])
            ->assertSessionHasErrors(['new_password1' => 'The password is too similar to the email address.']);
    }

    public function test_a_change_is_stored_and_the_session_survives_it(): void
    {
        $this->change()->assertRedirect('/profile');

        $this->assertAuthenticatedAs($this->writer);

        $stored = $this->writer->fresh()->password;
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $stored));
        $this->assertFalse(Hash::check(self::OLD_PASSWORD, $stored));

        // Reaching /profile at all is the proof: an ended session would answer with the login page.
        $this->get('/profile')->assertOk();
    }

    public function test_an_imported_django_hash_can_be_changed_from(): void
    {
        $this->writer->forceFill(['password' => $this->djangoHash(self::OLD_PASSWORD)])->save();
        $this->assertStringStartsWith('pbkdf2_sha256$', $this->writer->fresh()->password);

        $this->change()->assertRedirect('/profile');

        $stored = $this->writer->fresh()->password;
        $this->assertStringStartsWith('$2y$', $stored);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $stored));
    }

    public function test_the_board_says_the_change_worked(): void
    {
        $this->followingRedirects()->change()->assertSee('Password changed.');
    }

    public function test_the_avatar_is_left_out_of_a_password_change(): void
    {
        $upload = 'uploads/'.$this->writer->id.'.png';
        Storage::disk('avatars')->put($upload, 'bytes');
        $this->writer->update(['avatar' => $upload]);

        $this->change()->assertRedirect('/profile');

        $this->assertSame($upload, $this->writer->fresh()->avatar);
        Storage::disk('avatars')->assertExists($upload);
    }

    public function test_saving_the_avatar_leaves_the_password_alone(): void
    {
        $this->post('/profile', ['avatar' => 'fem-a1.png'])->assertRedirect('/profile');

        $this->assertSame('fem-a1.png', $this->writer->fresh()->avatar);
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $this->writer->fresh()->password));
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
