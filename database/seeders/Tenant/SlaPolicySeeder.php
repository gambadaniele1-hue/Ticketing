<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;
use App\Models\Tenant\SlaPolicy;
use Illuminate\Support\Facades\DB;

class SlaPolicySeeder extends Seeder
{
    public function run(): void
    {
        $isShared = tenant('tenancy_db_name') === env('SHARED_DB_NAME', 'ticketing_shared');
        $tenantId = tenant('id');

        $slas = [
            ['name' => 'Standard', 'priority' => 1, 'response_time_hours' => 24, 'resolution_time_hours' => 72],
            ['name' => 'High', 'priority' => 2, 'response_time_hours' => 4, 'resolution_time_hours' => 24],
            ['name' => 'Urgent', 'priority' => 3, 'response_time_hours' => 1, 'resolution_time_hours' => 8],
        ];

        foreach ($slas as $sla) {
            $search = ['name' => $sla['name']];
            if ($isShared) {
                $search['tenant_id'] = $tenantId;
            }

            SlaPolicy::firstOrCreate($search, $sla);
        }
    }
}