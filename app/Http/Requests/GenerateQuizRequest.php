<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateQuizRequest extends FormRequest
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
            'document_id' => ['required', 'exists:documents,id'],
            'question_count' => ['required', 'integer', 'min:5', 'max:50'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'question_type' => ['required', 'in:multiple_choice,true_false,mixed'],
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
            'document_id.required' => 'Document ID is required',
            'document_id.exists' => 'The selected document does not exist',
            'question_count.required' => 'Question count is required',
            'question_count.min' => 'Question count must be at least 5',
            'question_count.max' => 'Question count must not exceed 50',
            'difficulty.required' => 'Difficulty level is required',
            'difficulty.in' => 'Difficulty must be one of: easy, medium, hard',
            'question_type.required' => 'Question type is required',
            'question_type.in' => 'Question type must be one of: multiple_choice, true_false, mixed',
        ];
    }
}
