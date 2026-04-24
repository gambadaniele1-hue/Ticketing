<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GlobalIdentity extends Model
{
    use SoftDeletes; // SoftDeletes [cite: 65]

    protected $fillable = [ // Mass assignment protetto [cite: 180]
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class, 'global_user_id'); // Foreign key esplicita [cite: 59]
    }
}