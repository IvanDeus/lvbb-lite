<?php

namespace App\Hashing;

use Illuminate\Hashing\BcryptHasher;

class DjangoHasher extends BcryptHasher
{
    private const ALGORITHMS = [
        'pbkdf2_sha256' => 'sha256',
        'pbkdf2_sha1' => 'sha1',
    ];

    public function info($hashedValue)
    {
        if ($parts = $this->djangoParts($hashedValue)) {
            return [
                'algo' => $parts['algorithm'],
                'algoName' => str_replace('_', '-', $parts['algorithm']),
                'options' => ['iterations' => $parts['iterations']],
            ];
        }

        return parent::info($hashedValue);
    }

    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = [])
    {
        $parts = $this->djangoParts($hashedValue);

        if (! $parts) {
            return parent::check($value, $hashedValue, $options);
        }

        $digest = hash_pbkdf2(
            self::ALGORITHMS[$parts['algorithm']],
            $value,
            $parts['salt'],
            $parts['iterations'],
            0,
            true
        );

        return hash_equals(base64_encode($digest), $parts['hash']);
    }

    public function needsRehash($hashedValue, array $options = [])
    {
        return (bool) $this->djangoParts($hashedValue) || parent::needsRehash($hashedValue, $options);
    }

    public function verifyConfiguration($value)
    {
        return $this->djangoParts($value) ? true : parent::verifyConfiguration($value);
    }

    /**
     * @return array{algorithm: string, iterations: int, salt: string, hash: string}|null
     */
    private function djangoParts($hashedValue): ?array
    {
        if (! is_string($hashedValue) || ! str_starts_with($hashedValue, 'pbkdf2_')) {
            return null;
        }

        $parts = explode('$', $hashedValue, 4);

        if (count($parts) !== 4 || ! isset(self::ALGORITHMS[$parts[0]])) {
            return null;
        }

        return [
            'algorithm' => $parts[0],
            'iterations' => (int) $parts[1],
            'salt' => $parts[2],
            'hash' => $parts[3],
        ];
    }
}
