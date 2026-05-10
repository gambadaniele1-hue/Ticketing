<?php

namespace App\Models\Global;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RefreshToken extends Model
{
    use HasFactory;

    protected $connection = 'mysql';
    protected $fillable = [
        'global_identity_id',
        'token',
        'expires_at',
        'revoked',
    ];

    // Diciamo a Laravel di trattare queste colonne come veri oggetti Data o Booleani
    protected $casts = [
        'expires_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    /**
     * Il token appartiene a un utente globale.
     */
    public function identity(): BelongsTo
    {
        return $this->belongsTo(GlobalIdentity::class, 'global_identity_id');
    }
}