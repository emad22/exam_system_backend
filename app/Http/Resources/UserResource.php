<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'first_name' => $this->first_name,
            'last_name'  => $this->last_name,
            'name'       => $this->name,
            'username'   => $this->username,
            'email'      => $this->email,
            'phone'      => $this->phone,
            'gender'     => $this->gender,
            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'address'    => $this->address,
            'city'       => $this->city,
            'state'      => $this->state,
            'country'    => $this->country,
            'religion'   => $this->religion,
            'occupation' => $this->occupation,
            'role'       => $this->role,
            'is_active'  => $this->is_active,
            'avatar'     => $this->avatar,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
