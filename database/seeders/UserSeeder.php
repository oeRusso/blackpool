<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Demo accounts, one per role. All of them use the password "password".
     */
    public const DEMO_USERS = [
        RoleName::Admin->value => ['name' => 'Ada Admin', 'email' => 'admin@blackpool.test'],
        RoleName::Editor->value => ['name' => 'Eddie Editor', 'email' => 'editor@blackpool.test'],
        RoleName::Premium->value => ['name' => 'Penny Premium', 'email' => 'premium@blackpool.test'],
        RoleName::Student->value => ['name' => 'Sam Student', 'email' => 'student@blackpool.test'],
    ];

    public function run(): void
    {
        foreach (self::DEMO_USERS as $role => $attributes) {
            User::factory()
                ->create($attributes)
                ->syncRoles([$role]);
        }
    }
}
