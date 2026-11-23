<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            /** admin | teacher | guidance */
            'role' => $this->role->value,
            'is_active' => $this->is_active,
            'locked_until' => $this->locked_until?->toIso8601String(),
        ];
    }
}
