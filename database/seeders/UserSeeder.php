<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts, one per role. All of them use DEMO_PASSWORD.
     */
    public const DEMO_PASSWORD = 'password';

    public const DEMO_USERS = [
        RoleName::Admin->value => ['name' => 'Ada Admin', 'email' => 'admin@blackpool.test'],
        RoleName::Editor->value => ['name' => 'Eddie Editor', 'email' => 'editor@blackpool.test'],
        RoleName::Premium->value => ['name' => 'Penny Premium', 'email' => 'premium@blackpool.test'],
        RoleName::Student->value => ['name' => 'Sam Student', 'email' => 'student@blackpool.test'],
    ];

    public function run(): void
    {
        // No factories here: seeding must also work in production images without Faker.
        foreach (self::DEMO_USERS as $role => $attributes) {
            $user = User::updateOrCreate(['email' => $attributes['email']], [
                'name' => $attributes['name'],
                'password' => Hash::make(self::DEMO_PASSWORD),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->syncRoles([$role]);
        }
    }
}
