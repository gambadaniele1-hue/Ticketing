<?php

declare(strict_types=1);

namespace App\Providers;

use App\Jobs\CreateTenantAdminUser;
use App\Jobs\CreateTenantMysqlUser;
use App\Models\Global\Plan;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    // By default, no namespace is used to support the callable array syntax.
    public static string $controllerNamespace = '';

    public function events()
    {
        return [
                // Tenant events
            Events\CreatingTenant::class => [],
            Events\TenantCreated::class => [
                function (Events\TenantCreated $event): void {
                    $isShared = !$this->shouldManageTenantDatabase($event->tenant->getAttribute('plan_id'));

                    $jobs = $isShared ? [
                            // Se è Shared: usiamo i dati del .env, niente utenti custom, ma creiamo l'admin tenant
                        Jobs\SeedDatabase::class,
                    ] : [
                            // Se è Dedicated: Facciamo l'infrastruttura super-sicura
                        Jobs\CreateDatabase::class,           // 1. Crea il database 'tenant_acme'
                        CreateTenantMysqlUser::class, // 2. <-- IL NOSTRO NUOVO JOB! Crea 'user_acme'
                        Jobs\MigrateDatabase::class,          // 3. Crea le tabelle
                        Jobs\SeedDatabase::class,             // 4. Inserisce i dati base
                    ];

                    $listener = JobPipeline::make($jobs)->send(function (Events\TenantCreated $innerEvent) {
                        return $innerEvent->tenant;
                    })->shouldBeQueued(true)->toListener();

                    $listener($event);
                },
            ],
            Events\SavingTenant::class => [],
            Events\TenantSaved::class => [],
            Events\UpdatingTenant::class => [],
            Events\TenantUpdated::class => [],
            Events\DeletingTenant::class => [],
            Events\TenantDeleted::class => [
                function (Events\TenantDeleted $event): void {
                    if (!$this->shouldManageTenantDatabase($event->tenant->getAttribute('plan_id'))) {
                        return;
                    }

                    $listener = JobPipeline::make([
                        Jobs\DeleteDatabase::class,
                    ])->send(function (Events\TenantDeleted $innerEvent) {
                        return $innerEvent->tenant;
                    })->shouldBeQueued(true)->toListener();

                    $listener($event);
                },
            ],

                // Domain events
            Events\CreatingDomain::class => [],
            Events\DomainCreated::class => [],
            Events\SavingDomain::class => [],
            Events\DomainSaved::class => [],
            Events\UpdatingDomain::class => [],
            Events\DomainUpdated::class => [],
            Events\DeletingDomain::class => [],
            Events\DomainDeleted::class => [],

                // Database events
            Events\DatabaseCreated::class => [],
            Events\DatabaseMigrated::class => [],
            Events\DatabaseSeeded::class => [],
            Events\DatabaseRolledBack::class => [],
            Events\DatabaseDeleted::class => [],

                // Tenancy events
            Events\InitializingTenancy::class => [],
            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],

            Events\EndingTenancy::class => [],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            Events\BootstrappingTenancy::class => [],
            Events\TenancyBootstrapped::class => [],
            Events\RevertingToCentralContext::class => [],
            Events\RevertedToCentralContext::class => [],

                // Resource syncing
            Events\SyncedResourceSaved::class => [
                Listeners\UpdateSyncedResource::class,
            ],

                // Fired only when a synced resource is changed in a different DB than the origin DB (to avoid infinite loops)
            Events\SyncedResourceChangedInForeignDatabase::class => [],
        ];
    }

    public function register()
    {
        //
    }

    public function boot()
    {
        $this->bootEvents();
        $this->mapRoutes();

        $this->makeTenancyMiddlewareHighestPriority();
    }

    protected function bootEvents()
    {
        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }

                Event::listen($event, $listener);
            }
        }
    }

    protected function mapRoutes()
    {
        $this->app->booted(function () {
            if (file_exists(base_path('routes/tenant.php'))) {
                Route::namespace(static::$controllerNamespace)
                    ->group(base_path('routes/tenant.php'));
            }
        });
    }

    protected function makeTenancyMiddlewareHighestPriority()
    {
        $tenancyMiddleware = [
                // Even higher priority than the initialization middleware
            Middleware\PreventAccessFromCentralDomains::class,

            Middleware\InitializeTenancyByDomain::class,
            Middleware\InitializeTenancyBySubdomain::class,
            Middleware\InitializeTenancyByDomainOrSubdomain::class,
            Middleware\InitializeTenancyByPath::class,
            Middleware\InitializeTenancyByRequestData::class,
        ];

        foreach (array_reverse($tenancyMiddleware) as $middleware) {
            $this->app[Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }

    private function shouldManageTenantDatabase(?int $planId): bool
    {
        if (!$planId) {
            return true;
        }

        return Plan::query()->whereKey($planId)->value('database_type') !== 'shared';
    }
}
