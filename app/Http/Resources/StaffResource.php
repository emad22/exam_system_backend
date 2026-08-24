<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StaffResource extends JsonResource
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
            'country'    => $this->country,
            'role'       => $this->role,
            'is_active'  => $this->is_active,
            'avatar'     => $this->avatar,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'partner'    => $this->whenLoaded('partner', fn() => [
                'id'               => $this->partner->id,
                'partner_name'     => $this->partner->partner_name,
                'website'          => $this->partner->website,
                'proctoring_mode'  => $this->partner->proctoring_mode,
            ]),
        ];
    }
}
