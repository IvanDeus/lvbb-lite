<?php

namespace App\Validation;

use App\Models\User;

class PasswordSimilarity
{
    /**
     * Django's UserAttributeSimilarityValidator, ported. Each attribute the account carries is
     * compared to the new password whole and in pieces split on punctuation, and the measure is the
     * character-frequency ratio Python's SequenceMatcher reports for quick_ratio() — not a substring
     * test. So "user" condemns "useruser12" but lets "12userxyzqwerty" through, and a password long
     * enough to dwarf the attribute is never compared to it at all.
     */
    public static function offendingAttribute(string $password, User $user): ?string
    {
        $password = mb_strtolower($password);

        foreach (['username' => 'username', 'email' => 'email address'] as $attribute => $verboseName) {
            $value = mb_strtolower((string) $user->{$attribute});

            foreach (array_merge(preg_split('/\W+/u', $value) ?: [], [$value]) as $part) {
                if ($part === '' || self::dwarfed($password, $part)) {
                    continue;
                }

                if (self::frequencyRatio($password, $part) >= 0.7) {
                    return $verboseName;
                }
            }
        }

        return null;
    }

    /**
     * The short-circuit Django runs before the expensive comparison: a part so small against the
     * password that it cannot reach the ratio anyway.
     */
    private static function dwarfed(string $password, string $part): bool
    {
        return strlen($password) >= 10 * strlen($part) && strlen($part) < 0.35 * strlen($password);
    }

    /** 2 * matching characters / total characters, which is what quick_ratio() computes. */
    private static function frequencyRatio(string $password, string $part): float
    {
        $counts = array_count_values(str_split($part));

        $matches = 0;
        foreach (array_count_values(str_split($password)) as $character => $inPassword) {
            $matches += min($inPassword, $counts[$character] ?? 0);
        }

        return 2 * $matches / (strlen($password) + strlen($part));
    }
}
