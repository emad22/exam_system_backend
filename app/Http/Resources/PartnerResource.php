<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'partner_name'         => $this->partner_name,
            'website'              => $this->website,
            'note'                 => $this->note,
            'r_date'               => $this->r_date,
            'proctoring_mode'      => $this->proctoring_mode,
            'proctoring_required'  => $this->proctoring_required,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),

            'user' => $this->whenLoaded('user', fn() => [
                'id'         => $this->user->id,
                'name'       => $this->user->name,
                'first_name' => $this->user->first_name,
                'last_name'  => $this->user->last_name,
                'email'      => $this->user->email,
                'phone'      => $this->user->phone,
                'country'    => $this->user->country,
                'is_active'  => $this->user->is_active,
            ]),
        ];
    }
}
