<?php

namespace Tests\Feature\Services;

use App\Jobs\CreateTenantMysqlUser;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Services\TenantRegistrationService;
use DB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Queue;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Database\Models\Domain;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;
use Tests\TestCase;

class TenantRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_global_identity_during_registration(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared',
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme',
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password_super_sicura_123',
            'planId' => $plan->id,
        ];

        app(TenantRegistrationService::class)->register($data);

        $this->assertDatabaseHas('global_identities', [
            'name' => 'Mario Rossi',
            'email' => 'mario@acme.com',
        ]);

        $identity = GlobalIdentity::where('email', 'mario@acme.com')->first();

        $this->assertNotNull($identity);
        $this->assertTrue(Hash::check('password_super_sicura_123', $identity->password));
    }

    public function test_shared_plan_creates_tenant_without_custom_db_credentials(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared',
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme',
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        app(TenantRegistrationService::class)->register($data);

        $this->assertDatabaseHas('tenants', [
            'id' => 'acme',
            'name' => 'Acme Corp',
            'plan_id' => $plan->id,
        ]);

        $tenant = Tenant::find('acme');

        $this->assertEquals('ticketing_shared', $tenant->tenancy_db_name);
        $this->assertNull($tenant->tenancy_db_username);
        $this->assertNull($tenant->tenancy_db_password);
    }

    public function test_dedicated_plan_creates_tenant_with_custom_db_credentials(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = Plan::create([
            'name' => 'Piano Enterprise Test',
            'price_month' => 99.90,
            'database_type' => 'dedicated',
        ]);

        $data = [
            'companyName' => 'Stark Industries',
            'subdomain' => 'starkjobs',
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony@stark.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        app(TenantRegistrationService::class)->register($data);

        $this->assertDatabaseHas('tenants', [
            'id' => 'starkjobs',
            'name' => 'Stark Industries',
            'plan_id' => $plan->id,
        ]);

        $tenant = Tenant::find('starkjobs');

        $this->assertEquals('tenant_starkjobs', $tenant->tenancy_db_name);
        $this->assertStringStartsWith('usr_stark', $tenant->tenancy_db_username);
        $this->assertNotEmpty($tenant->tenancy_db_password);
        $this->assertTrue(strlen($tenant->tenancy_db_password) >= 16);
    }

    public function test_shared_plan_dispatches_only_seed_job(): void
    {
        Bus::fake();

        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared',
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acmejobs',
            'adminName' => 'Mario',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        app(TenantRegistrationService::class)->register($data);

        Bus::assertDispatched(JobPipeline::class, function (JobPipeline $pipeline): bool {
            return $pipeline->jobs === [SeedDatabase::class];
        });
    }

    public function test_dedicated_plan_dispatches_full_infrastructure_jobs(): void
    {
        Bus::fake();

        $plan = Plan::create([
            'name' => 'Piano Enterprise Test',
            'price_month' => 99.90,
            'database_type' => 'dedicated',
        ]);

        $data = [
            'companyName' => 'Stark Industries',
            'subdomain' => 'starkjobs-dispatch',
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony@stark.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        app(TenantRegistrationService::class)->register($data);

        Bus::assertDispatched(JobPipeline::class, function (JobPipeline $pipeline): bool {
            return $pipeline->jobs === [
                CreateDatabase::class,
                CreateTenantMysqlUser::class,
                MigrateDatabase::class,
                SeedDatabase::class,
            ];
        });
    }

    public function test_it_creates_a_domain_during_registration(): void
    {
        // 1. Arrange
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared',
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme', // Passiamo solo il sottodominio
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password_super_sicura_123',
            'planId' => $plan->id,
        ];

        // 2. Act
        app(TenantRegistrationService::class)->register($data);

        // 3. Assert
        // Recuperiamo il dominio base esattamente come fa il Service
        $baseDomain = config('tenancy.central_domains')[0] ?? env('APP_BASE_DOMAIN', 'localhost');
        $expectedDomain = 'acme.' . $baseDomain;

        $this->assertDatabaseHas('domains', [
            'domain' => $expectedDomain,
            'tenant_id' => 'acme', // Assicuriamoci che sia collegato al tenant giusto!
        ]);
    }

    public function test_it_creates_a_membership_during_registration(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = Plan::create([
            'name' => 'Piano Base Test',
            'price_month' => 19.90,
            'database_type' => 'shared',
        ]);

        $data = [
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme', // Passiamo solo il sottodominio
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password_super_sicura_123',
            'planId' => $plan->id,
        ];

        // 2. Act
        app(TenantRegistrationService::class)->register($data);

        $identity = GlobalIdentity::where('email', 'mario@acme.com')->first();

        $this->assertDatabaseHas('tenant_memberships', [
            'global_user_id' => $identity->id,
            'tenant_id' => 'acme', // Assicuriamoci che sia collegato al tenant giusto!
            'state' => 'accepted'
        ]);
    }

    public function test_it_physically_creates_the_tenant_database_in_mysql(): void
    {
        $expectedDbName = 'tenant_starkphysical';

        // 0. PULIZIA PREVENTIVA: Se un test di ieri si è bloccato e ha lasciato il DB, piallalo prima di iniziare.
        DB::statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");

        // 1. Arrange
        $plan = Plan::create([
            'name' => 'Piano Enterprise Fisico',
            'price_month' => 99.90,
            'database_type' => 'dedicated',
        ]);

        $data = [
            'companyName' => 'Stark Physical DB',
            'subdomain' => 'starkphysical',
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tonyphysical@stark.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        // Mettiamo TUTTO il test dentro un blocco try
        try {

            // 2. Act
            $service = app(TenantRegistrationService::class);
            $service->register($data);

            // 3. Assert
            $databaseExists = DB::select(
                "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
                [$expectedDbName]
            );

            $this->assertNotEmpty($databaseExists, "Errore: Il database fisico '{$expectedDbName}' non è stato creato su MySQL!");

        } finally {
            // 1. Usciamo forzatamente dal database del tenant!
            if (tenancy()->initialized) {
                tenancy()->end();
            }

            // 2. Ora siamo tornati nel DB centrale. Possiamo eliminare in sicurezza.
            $tenant = Tenant::find('starkphysical');
            if ($tenant) {
                $tenant->delete();
            }

            // 3. Pulizia d'emergenza su MySQL
            DB::statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");
            DB::statement("DROP USER IF EXISTS 'usr_starkphysi'@'%'");
            DB::statement("DROP USER IF EXISTS 'usr_starkphysi'@'127.0.0.1'");
            DB::statement("DROP USER IF EXISTS 'usr_starkphysi'@'localhost'");
        }
    }
}