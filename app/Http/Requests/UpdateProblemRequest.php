<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProblemRequest extends FormRequest
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
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'tags' => 'nullable|string',
            'time_limit' => 'required|integer|min:1',
            'memory_limit' => 'required|integer|min:256',
            'score' => 'required|integer|min:500',
            'test_cases' => 'nullable|file|mimes:zip',
            'note' => 'nullable|string',
            'checker_code' => 'nullable|string',

            // Kept identical to StoreProblemRequest's placeholder — see the
            // note there. Update both together once the real scale is set.
            'difficulty' => 'nullable|integer|min:1',

            'name_en' => 'required|string|max:255',
            'note_en' => 'nullable|string',
            'name_ru' => 'required|string|max:255',
            'note_ru' => 'nullable|string',
        ];
    }

    protected function withValidator($validator)
    {
        $validator->sometimes(['description', 'input', 'output', 'description_en', 'input_en', 'output_en', 'description_ru', 'input_ru', 'output_ru'], 'required|string', function ($input) {
            return !$this->route('contest')->hasAttachments();
        });
    }

    public function messages()
    {
        return [
            'description.required' => 'The description field is required when the contest is not IOI.',
            'input.required' => 'The input field is required when the contest is not IOI.',
            'output.required' => 'The output field is required when the contest is not IOI.',
        ];
    }
}
