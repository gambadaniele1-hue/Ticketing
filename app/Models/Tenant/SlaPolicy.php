<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToTenantHybrid;
class SlaPolicy extends Model
{
    use BelongsToTenantHybrid;
    // Specifica il nome della tabella se Laravel non lo deduce correttamente (opzionale ma consigliato per policy/policies)
    protected $table = 'sla_policies';

    protected $fillable = [
        'name',
        'priority',
        'response_time_hours',
        'resolution_time_hours',
    ];
}