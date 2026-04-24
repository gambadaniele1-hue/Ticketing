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
}