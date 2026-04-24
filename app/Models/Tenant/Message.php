<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToTenantHybrid;
class Message extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = ['ticket_id', 'user_id', 'body', 'is_internal', 'macro_id'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}