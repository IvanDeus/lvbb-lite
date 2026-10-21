<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['username' => 'admin', 'email' => 'admin@example.com', 'password' => 'pass123', 'avatar' => 'male-b1.png', 'is_staff' => true],
            ['username' => 'user', 'email' => 'user@example.com', 'password' => 'zxc123', 'avatar' => 'fem-a2.png', 'is_staff' => false],
        ] as $account) {
            if (User::where('username', $account['username'])->exists()) {
                $this->command?->warn("User {$account['username']} already exists, skipped.");

                continue;
            }

            User::forceCreate($account);
        }
    }
}
