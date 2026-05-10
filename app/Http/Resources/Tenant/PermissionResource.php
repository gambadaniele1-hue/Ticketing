<?php

namespace App\Http\Resources\Tenant;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            "id" => $this->id,
            'slug' => $this->slug, // Al frontend serve lo slug (es. 'tickets.create') per nascondere/mostrare bottoni
        ];
    }
}