<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HashingTest extends TestCase
{
    public function test_a_django_pbkdf2_hash_verifies_and_asks_for_upgrade(): void
    {
        $hash = $this->djangoHash('sha256', 's3cret');

        $this->assertTrue(Hash::check('s3cret', $hash));
        $this->assertFalse(Hash::check('wrong', $hash));
        $this->assertTrue(Hash::needsRehash($hash));
        $this->assertTrue(Hash::isHashed($hash), 'the hashed cast must not re-wrap a Django string');
        $this->assertSame('pbkdf2_sha256', Hash::info($hash)['algo']);
    }

    public function test_the_older_pbkdf2_sha1_variant_verifies_too(): void
    {
        $hash = $this->djangoHash('sha1', 'legacy');

        $this->assertTrue(Hash::check('legacy', $hash));
        $this->assertFalse(Hash::check('nope', $hash));
    }

    public function test_bcrypt_hashes_are_not_rehashed(): void
    {
        $hash = Hash::make('s3cret');

        $this->assertStringStartsWith('$2y$', $hash);
        $this->assertTrue(Hash::check('s3cret', $hash));
        $this->assertFalse(Hash::needsRehash($hash));
    }

    public function test_the_hashed_cast_passes_a_django_hash_through_untouched(): void
    {
        $hash = $this->djangoHash('sha256', 's3cret');

        $this->assertSame($hash, (new User(['username' => 'probe', 'password' => $hash]))->password);
        $this->assertStringStartsWith('$2y$', (new User(['username' => 'probe', 'password' => 'plaintext']))->password);
    }

    /**
     * Django's PBKDF2PasswordHasher: algorithm$iterations$salt$base64(digest).
     */
    private function djangoHash(string $algorithm, string $password, int $iterations = 1000): string
    {
        $salt = 'testsalt';

        return sprintf(
            'pbkdf2_%s$%d$%s$%s',
            $algorithm,
            $iterations,
            $salt,
            base64_encode(hash_pbkdf2($algorithm, $password, $salt, $iterations, 0, true))
        );
    }
}
