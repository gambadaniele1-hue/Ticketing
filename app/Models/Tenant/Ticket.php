<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenantHybrid;

class Ticket extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'user_id_author',
        'user_id_resolver',
        'team_id',
        'category_id',
        'closed_at'
    ];

    protected $casts = ['closed_at' => 'datetime'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_author');
    }
    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id_resolver');
    }
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}