<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PassageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'content'    => $this->content,
            'type'       => $this->type,
            'media_path' => $this->media_path,
            'audio_path' => $this->audio_path,
            'image_path' => $this->image_path,
            'pdf_path'   => $this->pdf_path,
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'questions'  => QuestionResource::collection($this->whenLoaded('questions')),
        ];
    }
}
