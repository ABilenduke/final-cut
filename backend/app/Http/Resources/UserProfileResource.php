<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;

/** @mixin User */
class UserProfileResource extends UserResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'phone' => $this->phone,
            'dateOfBirth' => $this->date_of_birth?->toIso8601String(),
        ]);
    }
}
