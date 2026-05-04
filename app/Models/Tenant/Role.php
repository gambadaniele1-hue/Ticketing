<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenantHybrid;
class Role extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = [
        'name',
        'description',
        'tenant_id',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('tenant_id'); // <--- Aggiungi questo!
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}