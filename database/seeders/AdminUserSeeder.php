<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@beeliin.test'],
            [
                'name'            => 'Beeliin Admin',
                'email'           => 'admin@beeliin.test',
                'password'        => Hash::make('Password123!'),
                'role'            => 'admin',
                'status'          => 'active',
                'whmcs_client_id' => 1,   // Matches FakeWhmcsService clientid/userid
            ]
        );

        $this->command->info('Admin user seeded: admin@beeliin.test / Password123!');
    }
}
