<?php

namespace App\Http\Requests;

use App\Models\Problem;
use App\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;

class StoreSubmissionRequest extends FormRequest
{
    protected ?Problem $problem = null;

    /**
     * Retrieve and cache the target problem from the route code.
     */
    public function problem(): Problem
    {
        if (!$this->problem) {
            $this->problem = Problem::where('code', $this->route('code'))->firstOrFail();
        }

        return $this->problem;
    }

    /**
     * Authorize request using SubmissionPolicy@create
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', [
            Submission::class,
            $this->problem(),
            $this->input('language'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'language' => ['required', 'string'],
            'code'     => ['required_without:file', 'nullable', 'string', 'max:512000'],
            'file'     => ['required_without:code', 'nullable', 'file', 'max:512000'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'code.required_without' => 'Kod tapylmady',
            'file.required_without' => 'Kod tapylmady',
        ];
    }
}