<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenantHybrid;

class Category extends Model
{
    use BelongsToTenantHybrid;
    protected $fillable = ['name', 'parent_category_id'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_category_id');
    }

    public function subcategories(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_category_id');
    }
}