<?php

namespace App\Http\Resources\Global;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'plan_id' => $this->plan_id,
            'description' => $this->description,
            // Aggiungi qui altri campi del tenant se servono al frontend (es. logo, dominio)
        ];
    }
}