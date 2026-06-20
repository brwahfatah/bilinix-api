<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServerPlan;

class ServerPlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['name' => 'Basic', 'price' => 5.00, 'cpu' => 1, 'ram' => 1024, 'storage' => 20],
            ['name' => 'Standard', 'price' => 10.00, 'cpu' => 2, 'ram' => 2048, 'storage' => 50],
            ['name' => 'Pro', 'price' => 25.00, 'cpu' => 4, 'ram' => 8192, 'storage' => 160],
        ];

        foreach ($plans as $p) {
            ServerPlan::updateOrCreate(['name' => $p['name']], $p);
        }
    }
}
