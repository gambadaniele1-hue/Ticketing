<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\SlaPolicy;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $slas = [
            ['name' => 'Standard', 'priority' => 1, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ['name' => 'High', 'priority' => 2, 'response_time_hours' => 4, 'resolution_time_hours' => 24],
            ['name' => 'Urgent', 'priority' => 3, 'response_time_hours' => 1, 'resolution_time_hours' => 8],
        ];

        foreach ($slas as $sla) {
            SlaPolicy::firstOrCreate(['name' => $sla['name']], $sla);
        }
    }
}