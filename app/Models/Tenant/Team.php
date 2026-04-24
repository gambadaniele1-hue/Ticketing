<?php
namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\BelongsToTenantHybrid;

class Team extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = ['name'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('team_role_id')
            ->withTimestamps();
    }
}