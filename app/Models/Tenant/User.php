<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenantHybrid;

class User extends Model
{
    use SoftDeletes, BelongsToTenantHybrid;

    protected $fillable = [
        'global_user_id',
        'role_id',
        'tenant_id'
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    // Metodo helper utile per verificare i permessi
    public function hasPermission(string $permissionSlug): bool
    {
        if (!$this->role) {
            return false;
        }

        return $this->role->permissions()->where('slug', $permissionSlug)->exists();
    }
}