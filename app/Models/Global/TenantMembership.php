<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantMembership extends Model
{
    protected $fillable = [ // Mass assignment protetto [cite: 180]
        'global_user_id',
        'tenant_id',
        'state',
    ];

    public function globalIdentity(): BelongsTo
    {
        return $this->belongsTo(GlobalIdentity::class, 'global_user_id'); // Foreign key esplicita [cite: 59]
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}