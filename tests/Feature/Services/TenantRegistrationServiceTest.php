<?php

namespace Tests\Feature\Services;

use App\Jobs\CreateTenantAdminUser;
use App\Jobs\CreateTenantMysqlUser;
use App\Models\Global\GlobalIdentity;
use App\Models\Global\Plan;
use App\Models\Global\Tenant;
use App\Services\TenantRegistrationService;
use DB;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseSeeded;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\CreateDatabase;
use Stancl\Tenancy\Jobs\MigrateDatabase;
use Stancl\Tenancy\Jobs\SeedDatabase;
use Tests\TestCase;

class TenantRegistrationServiceTest extends TestCase
{
    use DatabaseTruncation;

    private function createPlan(string $databaseType = 'shared'): Plan
    {
        return Plan::create([
            'name' => $databaseType === 'shared' ? 'Piano Base Test' : 'Piano Enterprise Test',
            'price_month' => $databaseType === 'shared' ? 19.90 : 99.90,
            'database_type' => $databaseType,
        ]);
    }

    private function registrationData(array $overrides = []): array
    {
        return array_merge([
            'companyName' => 'Acme Corp',
            'subdomain' => 'acme',
            'adminName' => 'Mario Rossi',
            'adminEmail' => 'mario@acme.com',
            'adminPassword' => 'password_super_sicura_123',
        ], $overrides);
    }

    private function registerTenant(array $data): void
    {
        app(TenantRegistrationService::class)->register($data);
    }

    public function test_it_creates_a_global_identity_during_registration(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = $this->createPlan('shared');
        $data = $this->registrationData(['planId' => $plan->id]);

        $this->registerTenant($data);

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

        $plan = $this->createPlan('shared');

        $this->registerTenant($this->registrationData([
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ]));

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

        $plan = $this->createPlan('dedicated');

        $this->registerTenant($this->registrationData([
            'companyName' => 'Stark Industries',
            'subdomain' => 'starkjobs',
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony@stark.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ]));

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

    public function test_shared_plan_dispatches_seed_and_admin_jobs(): void
    {
        Bus::fake();

        $plan = $this->createPlan('shared');

        $this->registerTenant($this->registrationData([
            'subdomain' => 'acmejobs',
            'adminName' => 'Mario',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ]));

        Bus::assertDispatched(JobPipeline::class, function (JobPipeline $pipeline): bool {
            return $pipeline->jobs === [
                SeedDatabase::class,
                CreateTenantAdminUser::class,
            ];
        });
    }

    public function test_dedicated_plan_dispatches_full_infrastructure_jobs(): void
    {
        Bus::fake();

        $plan = $this->createPlan('dedicated');

        $this->registerTenant($this->registrationData([
            'companyName' => 'Stark Industries',
            'subdomain' => 'starkjobs-dispatch',
            'adminName' => 'Tony Stark',
            'adminEmail' => 'tony@stark.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ]));

        Bus::assertDispatched(JobPipeline::class, function (JobPipeline $pipeline): bool {
            return $pipeline->jobs === [
                CreateDatabase::class,
                CreateTenantMysqlUser::class,
                MigrateDatabase::class,
                SeedDatabase::class,
                CreateTenantAdminUser::class, // Sempre per ultimo!
            ];
        });
    }

    public function test_it_creates_domain_and_membership_during_registration(): void
    {
        Event::fake([TenantCreated::class, TenantDeleted::class]);

        $plan = $this->createPlan('shared');
        $data = $this->registrationData(['planId' => $plan->id]);

        $this->registerTenant($data);

        $baseDomain = config('tenancy.central_domains')[0] ?? env('APP_BASE_DOMAIN', 'localhost');
        $expectedDomain = 'acme.' . $baseDomain;

        $this->assertDatabaseHas('domains', [
            'domain' => $expectedDomain,
            'tenant_id' => 'acme',
        ]);

        $identity = GlobalIdentity::where('email', 'mario@acme.com')->first();

        $this->assertDatabaseHas('tenant_memberships', [
            'global_user_id' => $identity->id,
            'tenant_id' => 'acme',
            'state' => 'accepted'
        ]);
    }

    public function test_it_physically_creates_the_tenant_database_in_mysql(): void
    {
        $expectedDbName = 'tenant_starkphysical';

        // 0. PULIZIA PREVENTIVA: Se un test di ieri si è bloccato e ha lasciato il DB, piallalo prima di iniziare.
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");

        // 1. Arrange
        $plan = $this->createPlan('dedicated');

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
            DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");
            DB::connection('mysql')->statement("DROP USER IF EXISTS 'usr_starkphysi'@'%'");
        }
    }

    public function test_it_rolls_back_data_if_dedicated_registration_fails(): void
    {
        // 1. Arrange
        $plan = $this->createPlan('dedicated');
        $expectedDbName = 'tenant_faildomain';

        $data = [
            'companyName' => 'Fail Corp',
            'subdomain' => 'faildomain',
            'adminName' => 'Mario Fail',
            'adminEmail' => 'mario@fail.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        // Pulizia preventiva
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");

        // TRUCCO: Ascoltiamo l'evento di creazione del Tenant. Non appena Laravel prova 
        // a lanciare i Job per creare il database fisico, noi sganciamo una bomba (Eccezione).
        Event::listen(TenantCreated::class, function () {
            throw new \Exception("Simulazione di un errore di sistema critico!");
        });

        // 2. Act
        try {
            $service = app(TenantRegistrationService::class);
            $service->register($data);

            // Se arriviamo a questa riga, significa che l'eccezione non è partita. Il test deve fallire!
            $this->fail('Il test avrebbe dovuto lanciare una eccezione e fermarsi prima.');

        } catch (\Exception $e) {
            // Verifichiamo che sia esattamente il nostro errore simulato
            $this->assertEquals("Simulazione di un errore di sistema critico!", $e->getMessage());
        }

        // 3. Assert (Il vero test del Rollback)

        // A. L'identità globale creata all'inizio DEVE essere stata eliminata dal rollback manuale
        $this->assertDatabaseMissing('global_identities', [
            'email' => 'mario@fail.com'
        ]);

        // B. Il record del tenant DEVE essere stato eliminato
        $this->assertDatabaseMissing('tenants', [
            'id' => 'faildomain'
        ]);

        // C. Nessun database fisico deve essere rimasto in giro
        $databaseExists = DB::select(
            "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$expectedDbName]
        );
        $this->assertEmpty($databaseExists, "Errore: Il database fisico non è stato ripulito!");

        // 4. Pulizia finale per sicurezza
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");
        DB::connection('mysql')->statement("DROP USER IF EXISTS 'usr_faildoma'@'%'");
    }

    public function test_it_rolls_back_everything_if_dedicated_seeding_fails(): void
    {
        $plan = $this->createPlan('dedicated');
        $expectedDbName = 'tenant_crashdomain';

        $data = [
            'companyName' => 'Crash Corp',
            'subdomain' => 'crashdomain',
            'adminName' => 'Mario Crash',
            'adminEmail' => 'mario@crash.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");

        // TRUCCO AVANZATO: Intercettiamo il Job 'SeedDatabase'. 
        // Lasciamo che il sistema crei il DB e le tabelle, ma quando arriva il momento di seedare, facciamo esplodere tutto.
        Event::listen(DatabaseSeeded::class, function () {
            throw new \Exception("Errore critico durante il seeding!");
        });

        try {
            $service = app(TenantRegistrationService::class);
            $service->register($data);

            $this->fail('Il test avrebbe dovuto lanciare una eccezione.');
        } catch (\Exception $e) {
            $this->assertEquals("Errore critico durante il seeding!", $e->getMessage());
        }

        // --- VERIFICHE DEL ROLLBACK MANUALE ---

        // 0. TORNAMO A CASA: Usciamo forzatamente dal database del tenant.
        // Siccome il processo è crashato a metà, Laravel è rimasto connesso al DB sbagliato!
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        // 1. Dati centrali eliminati
        $this->assertDatabaseMissing('global_identities', ['email' => 'mario@crash.com']);
        $this->assertDatabaseMissing('tenants', ['id' => 'crashdomain']);

        // 2. Database fisico eliminato
        // ATTENZIONE: Questo passa solo se in config/tenancy.php l'evento TenantDeleted 
        // è mappato al Job DeleteDatabase::class.
        $databaseExists = DB::select(
            "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ?",
            [$expectedDbName]
        );
        $this->assertEmpty($databaseExists, "Errore: Il database fisico '{$expectedDbName}' è stato lasciato orfano!");

        // Pulizia di sicurezza
        DB::connection('mysql')->statement("DROP DATABASE IF EXISTS `{$expectedDbName}`");
        DB::connection('mysql')->statement("DROP USER IF EXISTS 'usr_crashdom'@'%'");
    }

    public function test_it_rolls_back_data_if_shared_registration_fails(): void
    {
        $plan = $this->createPlan('shared');

        $data = [
            'companyName' => 'Shared Fail Corp',
            'subdomain' => 'sharedfail',
            'adminName' => 'Luigi Fail',
            'adminEmail' => 'luigi@sharedfail.com',
            'adminPassword' => 'password123',
            'planId' => $plan->id,
        ];

        // Qui facciamo fallire subito l'evento di creazione per testare la transazione di Laravel
        Event::listen(DatabaseSeeded::class, function () {
            throw new \Exception("Errore simulato su piano shared!");
        });

        try {
            $service = app(TenantRegistrationService::class);
            $service->register($data);

            $this->fail('Il test avrebbe dovuto lanciare una eccezione.');
        } catch (\Exception $e) {
            $this->assertEquals("Errore simulato su piano shared!", $e->getMessage());
        }

        // --- VERIFICHE DELLA TRANSAZIONE ---

        // 0. TORNAMO A CASA: Sganciamoci dal DB del tenant
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        // La DB::transaction() avrebbe dovuto annullare tutto in automatico!
        // 1. Aggiungiamo 'mysql' per forzare la lettura sul database centrale
        $this->assertDatabaseMissing('global_identities', [
            'email' => 'luigi@sharedfail.com'
        ], 'mysql');

        $this->assertDatabaseMissing('tenants', [
            'id' => 'sharedfail'
        ], 'mysql');

        $this->assertDatabaseMissing('domains', [
            'domain' => 'sharedfail.localhost' // O il dominio base che usi
        ], 'mysql');
    }
}