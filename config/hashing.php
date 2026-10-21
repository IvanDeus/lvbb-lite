<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Hashing Driver
    |--------------------------------------------------------------------------
    |
    | "django" verifies Django's pbkdf2_sha256 hashes as well as Laravel's bcrypt
    | ones, and reports Django hashes as needing a rehash, so rows imported from
    | djbb-lite keep logging in and are transparently upgraded to bcrypt.
    |
    */

    'driver' => env('HASH_DRIVER', 'django'),

    'bcrypt' => [
        'rounds' => env('BCRYPT_ROUNDS', 12),
        'verify' => env('HASH_VERIFY', false),
        'limit' => env('HASH_LIMIT', null),
    ],

    'argon' => [
        'memory' => env('ARGON_MEMORY', 65536),
        'threads' => env('ARGON_THREADS', 1),
        'time' => env('ARGON_TIME', 4),
        'verify' => env('ARGON_VERIFY', false),
    ],

];
