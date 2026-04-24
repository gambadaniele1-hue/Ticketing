<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BelongsToTenantHybrid
{
    public static function bootBelongsToTenantHybrid()
    {
        // Se non c'è un tenant inizializzato (es. siamo nel dominio centrale), non facciamo nulla
        if (!tenancy()->initialized) {
            return;
        }

        // Recuperiamo il nome del DB del tenant attuale dalla configurazione in RAM
        $currentDb = config('database.connections.tenant.database');
        $sharedDb = env('SHARED_DB_NAME', 'ticketing_shared');

        // LOGICA IBRIDA:
        // Se il database è quello condiviso, applichiamo il filtro obbligatorio
        if ($currentDb === $sharedDb) {

            // 1. Filtro automatico in SELECT (Global Scope)
            static::addGlobalScope('tenant_filter', function (Builder $builder) {
                $builder->where('tenant_id', tenant('id'));
            });

            // 2. Inserimento automatico in CREATE
            static::creating(function ($model) {
                if (!$model->getAttribute('tenant_id')) {
                    $model->setAttribute('tenant_id', tenant('id'));
                }
            });
        }

        // Se invece siamo su un DB dedicato (Enterprise), Laravel non vedrà alcuno Scope 
        // e farà query pulite perché il DB è già isolato.
    }
}