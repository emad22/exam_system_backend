<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'question_id' => $this->question_id,
            'option_text' => $this->option_text,
            'is_correct'  => (bool) $this->is_correct,
            'sort_order'  => (int) $this->sort_order,
            'image_path'  => $this->image_path,
            'image_url'   => $this->image_url,
            'sound_path'  => $this->sound_path,
            'sound_url'   => $this->sound_url,
            'dir'         => $this->dir,
            'font_size'   => $this->font_size,
        ];
    }
}
