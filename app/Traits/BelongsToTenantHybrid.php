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
            if (tenancy()->initialized && config('database.connections.tenant.database') === env('SHARED_DB_NAME', 'ticketing_shared')) {

                // LA MAGIA È QUI: qualifyColumn() aggiunge il nome della tabella!
                $builder->where($builder->qualifyColumn('tenant_id'), tenant('id'));
            }

        });

        // 2. Inserimento automatico in CREATE
        static::creating(function ($model) {

            if (tenancy()->initialized && config('database.connections.tenant.database') === env('SHARED_DB_NAME', 'ticketing_shared')) {
                if (!$model->getAttribute('tenant_id')) {
                    $model->setAttribute('tenant_id', tenant('id'));
                }
            }

        });
    }
}