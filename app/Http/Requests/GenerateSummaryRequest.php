<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateSummaryRequest extends FormRequest
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
            'summary_type' => ['required', 'in:concise,detailed,bullet_points,abstract'],
            'language' => ['sometimes', 'in:en,id'],
            'custom_prompt' => ['sometimes', 'string', 'max:500'],
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
            'summary_type.required' => 'Summary type is required',
            'summary_type.in' => 'Summary type must be one of: concise, detailed, bullet_points, abstract',
            'language.in' => 'Language must be either en or id',
            'custom_prompt.max' => 'Custom prompt must not exceed 500 characters',
        ];
    }
}
