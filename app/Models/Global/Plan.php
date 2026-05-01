<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [ // Mass assignment protetto [cite: 180]
        'name',
        'description',
        'price_month',
        'database_type',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    /**
     * Forza questo modello a usare SEMPRE e SOLO la connessione centrale.
     * Impedisce il "Connection Bleed" quando siamo dentro un tenant.
     */
    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }
}