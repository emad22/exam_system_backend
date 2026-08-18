<?php

namespace App\Http\Requests\Admin\Question;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreQuestionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $questions = $this->input('questions');

        if (is_string($questions)) {
            $decoded = json_decode($questions, true);
            if (is_array($decoded)) {
                $this->merge(['questions' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'skill_id'                      => 'required|exists:skills,id',
            'exam_id'                        => 'required|exists:exams,id',
            'level_id'                       => 'nullable|integer|min:1|max:9',

            // Passage
            'passage_mode'                   => 'required|in:none,existing,new',
            'passage_id'                     => 'required_if:passage_mode,existing|exists:passages,id|nullable',
            'passage_type'                   => 'required_if:passage_mode,new|in:text,image,audio,video|nullable',
            'passage_title'                  => 'nullable|string',
            'passage_content'                => 'nullable|string',
            'passage_questions_limit'        => 'nullable|integer|min:1',
            'passage_is_random'              => 'nullable',
            'p_media_file'                   => 'nullable|file|max:20480',
            'p_audio_file'                   => 'nullable|file|max:20480',
            'p_image_file'                   => 'nullable|file|mimetypes:image/jpeg,image/png,image/gif,image/svg+xml,image/webp|max:10240',
            'p_image_width'                  => 'nullable|numeric',
            'p_image_height'                 => 'nullable|numeric',

            // Questions batch
            'questions'                      => 'required|array|min:1',
            'questions.*.type'               => 'required|in:mcq,true_false,short_answer,writing,speaking,speaking_live,upload,drag_drop,word_selection,fill_blank,matching,ordering,highlight,listening,click_word,pdf_annotation',
            'questions.*.content'            => 'nullable|string',
            'questions.*.instructions'       => 'nullable|string',
            'questions.*.general_instructions' => 'nullable|string',
            'questions.*.points'             => 'required|integer|min:1',
            'questions.*.sort_order'         => 'nullable|integer',
            'questions.*.image_width'        => 'nullable|numeric',
            'questions.*.image_height'       => 'nullable|numeric',
            'questions.*.options'            => 'nullable|array',
        ];
    }

    /**
     * Override to return JSON error response matching the original manual Validator behaviour.
     */
    protected function failedValidation(Validator $validator): never
    {
        \Illuminate\Support\Facades\Log::error('Question Store Validation Failed', [
            'errors' => $validator->errors()->toArray(),
            'input'  => $this->all(),
        ]);

        throw new HttpResponseException(
            response()->json([
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422)
        );
    }
}
