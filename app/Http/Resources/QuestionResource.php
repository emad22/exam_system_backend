<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuestionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'skill_id'             => $this->skill_id,
            'exam_id'              => $this->exam_id,
            'level_id'             => $this->level_id,
            'passage_id'           => $this->passage_id,
            'type'                 => $this->type,
            'instructions'         => $this->instructions,
            'general_instructions' => $this->general_instructions,
            'content'              => $this->content,
            'media_path'           => $this->media_path,
            'media_url'            => $this->media_url,
            'audio_path'           => $this->audio_path,
            'audio_url'            => $this->audio_url,
            'image_path'           => $this->image_path,
            'image_url'            => $this->image_url,
            'pdf_path'             => $this->pdf_path,
            'pdf_url'              => $this->pdf_url,
            'image_width'          => $this->image_width,
            'image_height'         => $this->image_height,
            'points'               => $this->points ?? 1,
            'min_words'            => $this->min_words,
            'max_words'            => $this->max_words,
            'sort_order'           => $this->sort_order,
            'group_tag'            => $this->group_tag,
            'created_by'           => $this->created_by,
            'updated_by'           => $this->updated_by,
            'created_at'           => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'           => $this->updated_at?->format('Y-m-d H:i:s'),

            // Optional counts
            'options_count'        => $this->whenCounted('options'),

            // Relations
            'options'              => QuestionOptionResource::collection($this->whenLoaded('options')),
            'skill'                => $this->whenLoaded('skill', fn() => [
                'id'         => $this->skill->id,
                'name'       => $this->skill->name,
                'short_code' => $this->skill->short_code,
            ]),
            'exam'                 => $this->whenLoaded('exam', fn() => [
                'id'    => $this->exam->id,
                'title' => $this->exam->title,
            ]),
            'level'                => $this->whenLoaded('level', fn() => [
                'id'           => $this->level->id,
                'name'         => $this->level->name,
                'level_number' => $this->level->level_number,
            ]),
            'passage'              => $this->whenLoaded('passage', fn() => new PassageResource($this->passage)),
            'creator'              => $this->whenLoaded('creator', fn() => [
                'id'         => $this->creator->id,
                'first_name' => $this->creator->first_name,
                'last_name'  => $this->creator->last_name,
            ]),
            'updater'              => $this->whenLoaded('updater', fn() => [
                'id'         => $this->updater->id,
                'first_name' => $this->updater->first_name,
                'last_name'  => $this->updater->last_name,
            ]),
        ];
    }
}
