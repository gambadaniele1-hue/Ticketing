<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\BelongsToTenantHybrid;

class Permission extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = [
        'slug',
        'description',
        'tenant_id',
    ];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }
}