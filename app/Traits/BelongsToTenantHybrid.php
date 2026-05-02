<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenantHybrid
{
    public static function bootBelongsToTenantHybrid()
    {
        // 1. Filtro automatico in SELECT (Global Scope)
        static::addGlobalScope('tenant_filter', function (Builder $builder) {

            // Il controllo DEVE stare qui dentro, così viene eseguito in tempo reale
            // ad ogni singola query, anche se il worker è acceso da giorni.
            if (tenancy()->initialized && config('database.connections.tenant.database') === env('SHARED_DB_NAME', 'ticketing_shared')) {
                $builder->where('tenant_id', tenant('id'));
            }

        });

        // 2. Inserimento automatico in CREATE
        static::creating(function ($model) {

            // Anche qui, controllo in tempo reale al momento del salvataggio
            if (tenancy()->initialized && config('database.connections.tenant.database') === env('SHARED_DB_NAME', 'ticketing_shared')) {
                if (!$model->getAttribute('tenant_id')) {
                    $model->setAttribute('tenant_id', tenant('id'));
                }
            }

        });
    }
}