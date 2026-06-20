<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name'            => 'Test Admin',
                'email'           => 'admin@test.com',
                'password'        => Hash::make('Password123!'),
                'role'            => 'client',
                'status'          => 'active',
                'whmcs_client_id' => 1,
            ]
        );

        $this->command->info('Test user seeded: admin@test.com / Password123!');
    }
}
