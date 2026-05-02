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

    /**
     * Forza questo modello a usare SEMPRE e SOLO la connessione centrale.
     * Impedisce il "Connection Bleed" quando siamo dentro un tenant.
     */
    public function getConnectionName()
    {
        return config('tenancy.database.central_connection');
    }

    public function refreshTokens()
    {
        return $this->hasMany(RefreshToken::class, 'global_identity_id');
    }
}