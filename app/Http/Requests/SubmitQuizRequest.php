<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitQuizRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'attempt_id' => ['required', 'exists:quiz_attempts,id'],
            'answers' => ['required', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.answer_index' => ['required', 'integer', 'min:0'],
            'time_spent_seconds' => ['required', 'integer', 'min:0'],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'attempt_id.required' => 'Attempt ID is required',
            'attempt_id.exists' => 'The selected attempt does not exist',
            'answers.required' => 'Answers are required',
            'answers.array' => 'Answers must be an array',
            'answers.*.question_id.required' => 'Question ID is required for each answer',
            'answers.*.answer_index.required' => 'Answer index is required for each answer',
            'answers.*.answer_index.min' => 'Answer index must be at least 0',
            'time_spent_seconds.required' => 'Time spent is required',
            'time_spent_seconds.min' => 'Time spent must be at least 0',
        ];
    }
}
